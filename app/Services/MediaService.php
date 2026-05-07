<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AuditServiceInterface;
use App\Contracts\MediaServiceInterface;
use App\DTOs\MediaUploadResultDTO;
use App\DTOs\PaginatedResultDTO;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    /** @var array<string, list<string>> */
    private const MIME_EXTENSIONS = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],
        'application/pdf' => ['pdf'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
        'application/vnd.ms-excel' => ['xls'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
        'application/vnd.ms-powerpoint' => ['ppt'],
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['pptx'],
        'video/mp4' => ['mp4'],
        'video/webm' => ['webm'],
    ];

    private const MAX_IMAGE_WIDTH = 8000;

    private const MAX_IMAGE_HEIGHT = 8000;

    private readonly Filesystem $disk;

    private readonly string $diskName;

    public function __construct(
        private readonly AuditServiceInterface $auditService,
    ) {
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

        $uploaderId = $this->requireNumericUserId($payload['uploaded_by'] ?? null);
        $this->authorizeMediaClassWrite($uploaderId, 'create');

        $this->validateFile($file);

        $directory = (string) ($payload['directory'] ?? 'media');
        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType() ?? 'application/octet-stream';
        $extension = $this->primaryExtensionForMime($mimeType);
        $filename = (string) Str::uuid().'.'.$extension;

        $storedPath = $this->disk->putFileAs($directory, $file, $filename);

        if ($storedPath === false) {
            throw ValidationException::withMessages([
                'file' => ['Failed to store the uploaded file.'],
            ]);
        }

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
            'uploaded_by' => $uploaderId,
            'faculty_scope_slug' => $this->resolveFacultyScopeForUpload($payload),
        ]);

        $this->auditService->log(
            action: 'media.uploaded',
            userId: $uploaderId,
            entityType: MediaAsset::class,
            entityId: (int) $asset->getKey(),
            metadata: [
                'mime_type' => $mimeType,
                'size_bytes' => $size,
            ],
        );

        return $this->toDto($asset);
    }

    public function delete(int|string $mediaId, int $userId): bool
    {
        $asset = MediaAsset::find($mediaId);

        if ($asset === null) {
            return false;
        }

        $this->authorizeMediaWrite($userId, 'delete', $asset);

        $deleted = (bool) $asset->delete();

        if ($deleted) {
            $this->auditService->log(
                action: 'media.deleted',
                userId: $userId,
                entityType: MediaAsset::class,
                entityId: (int) $asset->getKey(),
            );
        }

        return $deleted;
    }

    /**
     * @param  array<string, mixed>  $metadata  Accepts 'title_ar', 'title_en', 'alt_text_ar', 'alt_text_en', 'caption_ar', 'caption_en'
     */
    public function updateMetadata(int|string $mediaId, array $metadata, int $userId): bool
    {
        $asset = MediaAsset::find($mediaId);

        if ($asset === null) {
            return false;
        }

        $this->authorizeMediaWrite($userId, 'update', $asset);

        $allowed = ['title_ar', 'title_en', 'alt_text_ar', 'alt_text_en', 'caption_ar', 'caption_en', 'faculty_scope_slug'];
        $filtered = array_intersect_key($metadata, array_flip($allowed));
        $filtered = $this->filterAllowedMetadataScope($filtered, $asset, $userId);

        if ($filtered === []) {
            return true;
        }

        $updated = $asset->update($filtered);

        if ($updated) {
            $this->auditService->log(
                action: 'media.metadata_updated',
                userId: $userId,
                entityType: MediaAsset::class,
                entityId: (int) $asset->getKey(),
                metadata: [
                    'fields' => array_keys($filtered),
                ],
            );
        }

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $filters  Accepts 'mime_type', 'search', 'uploaded_by', 'per_page', 'page'
     * @return Collection<int, MediaUploadResultDTO>
     */
    public function list(int $userId, array $filters = []): Collection
    {
        $query = $this->buildListQuery($filters, $this->authorizedListUser($userId));

        return $query->get()->map(fn (MediaAsset $asset): MediaUploadResultDTO => $this->toDto($asset));
    }

    /**
     * Paginated media listing for non-Filament consumers.
     *
     * @param  array<string, mixed>  $filters  Accepts 'mime_type', 'search', 'uploaded_by'
     */
    public function listPaginated(int $userId, array $filters = [], int $page = 1, int $perPage = 20): PaginatedResultDTO
    {
        $query = $this->buildListQuery($filters, $this->authorizedListUser($userId));

        $paginator = $query->paginate(perPage: $perPage, page: $page);

        $items = collect($paginator->items())
            ->map(fn (MediaAsset $asset): MediaUploadResultDTO => $this->toDto($asset));

        return new PaginatedResultDTO(
            items: $items,
            total: $paginator->total(),
            currentPage: $paginator->currentPage(),
            perPage: $paginator->perPage(),
            lastPage: $paginator->lastPage(),
        );
    }

    /**
     * Build the base query for media listing with optional filters.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<MediaAsset>
     */
    private function buildListQuery(array $filters, User $user): Builder
    {
        $query = MediaAsset::query();

        if (isset($filters['mime_type']) && is_string($filters['mime_type'])) {
            $query->where('mime_type', 'like', $filters['mime_type'].'%');
        }

        if (isset($filters['search']) && is_string($filters['search']) && $filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';
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

        if ($user->role_slug === 'faculty_editor') {
            $query->where('faculty_scope_slug', $user->faculty_scope_slug);
        } elseif (isset($filters['faculty_scope_slug']) && is_string($filters['faculty_scope_slug']) && $filters['faculty_scope_slug'] !== '') {
            $query->where('faculty_scope_slug', $filters['faculty_scope_slug']);
        }

        $query->orderByDesc('created_at');

        return $query;
    }

    private function validateFile(UploadedFile $file): void
    {
        $mimeType = $file->getMimeType() ?? 'application/octet-stream';

        if (! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw ValidationException::withMessages([
                'file' => ["File type '{$mimeType}' is not allowed."],
            ]);
        }

        $clientExtension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = self::MIME_EXTENSIONS[$mimeType] ?? [];

        if ($allowedExtensions === [] || ($clientExtension !== '' && ! in_array($clientExtension, $allowedExtensions, true))) {
            throw ValidationException::withMessages([
                'file' => ['The uploaded file extension does not match its detected file type.'],
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
                        'file' => ["Image dimensions ({$width}x{$height}) exceed the maximum allowed (".self::MAX_IMAGE_WIDTH.'x'.self::MAX_IMAGE_HEIGHT.').'],
                    ]);
                }
            }
        }
    }

    private function primaryExtensionForMime(string $mimeType): string
    {
        $extensions = self::MIME_EXTENSIONS[$mimeType] ?? [];

        if ($extensions === []) {
            throw ValidationException::withMessages([
                'file' => ['The detected file type does not have an approved extension.'],
            ]);
        }

        return $extensions[0];
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

    private function authorizeMediaClassWrite(int $userId, string $ability): void
    {
        $user = User::query()->find($userId);

        if (! $user instanceof User || Gate::forUser($user)->denies($ability, MediaAsset::class)) {
            throw new AuthorizationException('This user is not authorized to manage media.');
        }
    }

    private function authorizeMediaWrite(int $userId, string $ability, MediaAsset $asset): void
    {
        $user = User::query()->find($userId);

        if (! $user instanceof User || Gate::forUser($user)->denies($ability, $asset)) {
            throw new AuthorizationException('This user is not authorized to modify the requested media asset.');
        }
    }

    /** @param array<string, mixed> $payload */
    private function resolveFacultyScopeForUpload(array $payload): ?string
    {
        if (! is_numeric($payload['uploaded_by'] ?? null)) {
            return null;
        }

        $user = User::query()->find((int) $payload['uploaded_by']);

        if ($user instanceof User && $user->role_slug === 'faculty_editor') {
            if (! is_string($user->faculty_scope_slug) || $user->faculty_scope_slug === '') {
                throw new AuthorizationException('Faculty editors must have a faculty scope to upload media.');
            }

            return $user->faculty_scope_slug;
        }

        if (isset($payload['faculty_scope_slug']) && is_string($payload['faculty_scope_slug']) && $payload['faculty_scope_slug'] !== '') {
            return $payload['faculty_scope_slug'];
        }

        return null;
    }

    private function requireNumericUserId(mixed $userId): int
    {
        if (! is_numeric($userId)) {
            throw new AuthorizationException('A valid authenticated user is required to manage media.');
        }

        return (int) $userId;
    }

    private function authorizedListUser(int $userId): User
    {
        $user = User::query()->find($userId);

        if (! $user instanceof User || Gate::forUser($user)->denies('viewAny', MediaAsset::class)) {
            throw new AuthorizationException('This user is not authorized to list media assets.');
        }

        if ($user->role_slug === 'faculty_editor' && (! is_string($user->faculty_scope_slug) || $user->faculty_scope_slug === '')) {
            throw new AuthorizationException('This user is not authorized to list media assets without a faculty scope.');
        }

        return $user;
    }

    /** @param array<string, mixed> $metadata */
    private function filterAllowedMetadataScope(array $metadata, MediaAsset $asset, int $userId): array
    {
        if (! array_key_exists('faculty_scope_slug', $metadata)) {
            return $metadata;
        }

        $user = User::query()->find($userId);

        if (! $user instanceof User) {
            throw new AuthorizationException('A valid authenticated user is required to manage media.');
        }

        if ($user->role_slug !== 'faculty_editor') {
            return $metadata;
        }

        if ($metadata['faculty_scope_slug'] !== $asset->faculty_scope_slug || $metadata['faculty_scope_slug'] !== $user->faculty_scope_slug) {
            throw new AuthorizationException('Faculty editors cannot change media faculty scope.');
        }

        return $metadata;
    }
}
