<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\MediaServiceInterface;
use App\DTOs\MediaUploadResultDTO;
use App\Models\MediaAsset;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Real media library service handling upload, metadata, listing, and soft-delete.
 */
final class MediaService implements MediaServiceInterface
{
    /** @var list<string> */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'video/mp4',
        'video/webm',
    ];

    private const MAX_FILE_SIZE_BYTES = 20 * 1024 * 1024; // 20 MB

    private const MAX_IMAGE_WIDTH = 8000;

    private const MAX_IMAGE_HEIGHT = 8000;

    private readonly Filesystem $disk;

    private readonly string $diskName;

    public function __construct()
    {
        $this->diskName = (string) config('filesystems.default', 'local');
        $this->disk = Storage::disk($this->diskName);
    }

    /**
     * @param  array<string, mixed>  $payload  Expects 'file' (UploadedFile), optional 'directory', 'title_ar', 'title_en', 'alt_text_ar', 'alt_text_en', 'caption_ar', 'caption_en', 'uploaded_by'
     */
    public function upload(array $payload): MediaUploadResultDTO
    {
        /** @var UploadedFile $file */
        $file = $payload['file'] ?? null;

        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'file' => ['A valid uploaded file is required.'],
            ]);
        }

        $this->validateFile($file);

        $directory = (string) ($payload['directory'] ?? 'media');
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension() ?: ($file->guessExtension() ?? '');
        $filename = pathinfo($originalName, PATHINFO_FILENAME) . '_' . time() . '.' . $extension;

        $storedPath = $this->disk->putFileAs($directory, $file, $filename);

        if ($storedPath === false) {
            throw ValidationException::withMessages([
                'file' => ['Failed to store the uploaded file.'],
            ]);
        }

        $mimeType = $file->getMimeType() ?? 'application/octet-stream';
        $size = $file->getSize() ?: 0;

        $width = null;
        $height = null;
        if (str_starts_with($mimeType, 'image/') && $mimeType !== 'image/svg+xml') {
            $dimensions = @getimagesize($file->getRealPath());
            if (is_array($dimensions)) {
                $width = $dimensions[0];
                $height = $dimensions[1];
            }
        }

        $asset = MediaAsset::create([
            'disk' => $this->diskName,
            'directory' => $directory,
            'filename' => $filename,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'extension' => $extension,
            'size_bytes' => $size,
            'width' => $width,
            'height' => $height,
            'alt_text_ar' => $payload['alt_text_ar'] ?? null,
            'alt_text_en' => $payload['alt_text_en'] ?? null,
            'caption_ar' => $payload['caption_ar'] ?? null,
            'caption_en' => $payload['caption_en'] ?? null,
            'title_ar' => $payload['title_ar'] ?? null,
            'title_en' => $payload['title_en'] ?? null,
            'path' => $storedPath,
            'uploaded_by' => $payload['uploaded_by'] ?? null,
        ]);

        return $this->toDto($asset);
    }

    public function delete(int|string $mediaId): bool
    {
        $asset = MediaAsset::find($mediaId);

        if ($asset === null) {
            return false;
        }

        return (bool) $asset->delete();
    }

    /**
     * @param  array<string, mixed>  $metadata  Accepts 'title_ar', 'title_en', 'alt_text_ar', 'alt_text_en', 'caption_ar', 'caption_en'
     */
    public function updateMetadata(int|string $mediaId, array $metadata): bool
    {
        $asset = MediaAsset::find($mediaId);

        if ($asset === null) {
            return false;
        }

        $allowed = ['title_ar', 'title_en', 'alt_text_ar', 'alt_text_en', 'caption_ar', 'caption_en'];
        $filtered = array_intersect_key($metadata, array_flip($allowed));

        if ($filtered === []) {
            return true;
        }

        return $asset->update($filtered);
    }

    /**
     * @param  array<string, mixed>  $filters  Accepts 'mime_type', 'search', 'uploaded_by', 'per_page', 'page'
     * @return Collection<int, MediaUploadResultDTO>
     */
    public function list(array $filters = []): Collection
    {
        $query = MediaAsset::query();

        if (isset($filters['mime_type']) && is_string($filters['mime_type'])) {
            $query->where('mime_type', 'like', $filters['mime_type'] . '%');
        }

        if (isset($filters['search']) && is_string($filters['search']) && $filters['search'] !== '') {
            $term = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($term): void {
                $q->where('filename', 'like', $term)
                    ->orWhere('original_name', 'like', $term)
                    ->orWhere('title_ar', 'like', $term)
                    ->orWhere('title_en', 'like', $term);
            });
        }

        if (isset($filters['uploaded_by'])) {
            $query->where('uploaded_by', $filters['uploaded_by']);
        }

        $query->orderByDesc('created_at');

        return $query->get()->map(fn (MediaAsset $asset): MediaUploadResultDTO => $this->toDto($asset));
    }

    private function validateFile(UploadedFile $file): void
    {
        $mimeType = $file->getMimeType() ?? 'application/octet-stream';

        if (! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw ValidationException::withMessages([
                'file' => ["File type '{$mimeType}' is not allowed."],
            ]);
        }

        $size = $file->getSize() ?: 0;
        if ($size > self::MAX_FILE_SIZE_BYTES) {
            $maxMb = self::MAX_FILE_SIZE_BYTES / (1024 * 1024);
            throw ValidationException::withMessages([
                'file' => ["File size exceeds the maximum allowed size of {$maxMb}MB."],
            ]);
        }

        if (str_starts_with($mimeType, 'image/') && $mimeType !== 'image/svg+xml') {
            $dimensions = @getimagesize($file->getRealPath());
            if (is_array($dimensions)) {
                [$width, $height] = $dimensions;
                if ($width > self::MAX_IMAGE_WIDTH || $height > self::MAX_IMAGE_HEIGHT) {
                    throw ValidationException::withMessages([
                        'file' => ["Image dimensions ({$width}x{$height}) exceed the maximum allowed (" . self::MAX_IMAGE_WIDTH . 'x' . self::MAX_IMAGE_HEIGHT . ').'],
                    ]);
                }
            }
        }
    }

    private function toDto(MediaAsset $asset): MediaUploadResultDTO
    {
        return new MediaUploadResultDTO(
            mediaId: (int) $asset->id,
            disk: $asset->disk,
            path: $asset->path,
            url: $this->disk->url($asset->path),
            mimeType: $asset->mime_type,
            size: (int) $asset->size_bytes,
            originalName: $asset->original_name,
            title: $asset->title_ar ?? $asset->title_en,
            altText: $asset->alt_text_ar ?? $asset->alt_text_en,
            caption: $asset->caption_ar ?? $asset->caption_en,
        );
    }
}
