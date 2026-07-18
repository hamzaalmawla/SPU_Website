<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Contracts\Media\MediaServiceInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Media\MediaUploadResultDTO;
use App\DTOs\Media\PublicMediaAssetDTO;
use App\DTOs\Shared\PaginatedResultDTO;
use App\Models\Media\MediaAsset;
use App\Models\User\User;
use App\Support\MediaUrlResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Real media library service handling upload, metadata, listing, and soft-delete.
 */
final class MediaService implements MediaServiceInterface
{
    private readonly Filesystem $disk;

    private readonly string $diskName;

    public function __construct(
        private readonly AuditServiceInterface $auditService,
        private readonly MediaFileValidator $fileValidator,
        private readonly CacheServiceInterface $cacheService,
    ) {
        $this->diskName = (string) config('filesystems.media_disk', 'public');
        $this->disk = Storage::disk($this->diskName);
    }

    /**
     * @param  array<string, mixed>  $payload  Expects 'file' (UploadedFile), optional 'directory', 'title_ar', 'title_en', 'alt_text_ar', 'alt_text_en', 'caption_ar', 'caption_en', 'uploaded_by', 'require_alt_text'
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

        $this->fileValidator->validate($file);

        $checksum = hash_file('sha256', $file->getRealPath());
        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType() ?? 'application/octet-stream';
        $extension = $this->fileValidator->primaryExtensionForMime($mimeType);
        $mediaType = $this->mediaTypeForMime($mimeType);
        $facultyScope = $this->resolveFacultyScopeForUpload($payload);

        $this->validateCleanUploadMetadata($mediaType, $payload);

        $existing = $this->findReusableAsset($checksum, $uploaderId, $facultyScope);
        if ($existing instanceof MediaAsset) {
            $this->auditService->log(
                action: 'media.reused',
                userId: $uploaderId,
                entityType: MediaAsset::class,
                entityId: (int) $existing->getKey(),
                metadata: ['checksum' => $checksum],
            );

            return $this->toDto($existing);
        }

        $directory = trim((string) ($payload['directory'] ?? $this->defaultDirectory($mediaType)), '/');
        $filename = substr($checksum, 0, 40).'.'.$extension;

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
            'checksum' => $checksum,
            'media_type' => $mediaType,
            'library_scope' => 'main',
            'metadata_status' => $this->metadataStatusForPayload($mediaType, $payload),
            'width' => $width,
            'height' => $height,
            'alt_text_ar' => $payload['alt_text_ar'] ?? null,
            'alt_text_en' => $payload['alt_text_en'] ?? null,
            'caption_ar' => $payload['caption_ar'] ?? null,
            'caption_en' => $payload['caption_en'] ?? null,
            'title_ar' => $payload['title_ar'] ?? null,
            'title_en' => $payload['title_en'] ?? null,
            'path' => $storedPath,
            'source_path' => null,
            'uploaded_by' => $uploaderId,
            'faculty_scope_slug' => $facultyScope,
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
            $this->invalidatePublicMediaCache();
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

        $allowed = ['title_ar', 'title_en', 'alt_text_ar', 'alt_text_en', 'caption_ar', 'caption_en', 'metadata_status', 'faculty_scope_slug'];
        $filtered = array_intersect_key($metadata, array_flip($allowed));
        $filtered = $this->filterAllowedMetadataScope($filtered, $asset, $userId);

        if ($filtered === []) {
            return true;
        }

        if (isset($filtered['metadata_status']) && ! $this->isValidMetadataStatus($filtered['metadata_status'])) {
            throw ValidationException::withMessages([
                'metadata_status' => ['The selected metadata status is invalid.'],
            ]);
        }

        if (! isset($filtered['metadata_status'])) {
            $merged = array_merge($asset->only(['title_ar', 'title_en', 'alt_text_ar', 'alt_text_en']), $filtered);
            $filtered['metadata_status'] = $this->metadataStatusForPayload(
                is_string($asset->media_type) ? $asset->media_type : $this->mediaTypeForMime($asset->mime_type),
                $merged,
            );
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
            $this->invalidatePublicMediaCache();
        }

        return $updated;
    }

    public function resolvePublicImages(array $mediaIds, string $locale): Collection
    {
        $ids = array_values(array_unique(array_filter($mediaIds, static fn (mixed $id): bool => is_int($id) && $id > 0)));

        if ($ids === []) {
            return collect();
        }

        $assets = MediaAsset::query()
            ->whereIn('id', $ids)
            ->where('library_scope', 'main')
            ->where('media_type', 'image')
            ->where('metadata_status', 'reviewed')
            ->get()
            ->keyBy(fn (MediaAsset $asset): int => (int) $asset->getKey());

        return collect($ids)
            ->map(function (int $id) use ($assets, $locale): ?PublicMediaAssetDTO {
                $asset = $assets->get($id);

                if (! $asset instanceof MediaAsset) {
                    return null;
                }

                $requestedSuffix = $locale === 'ar' ? 'ar' : 'en';
                $fallbackSuffix = $requestedSuffix === 'ar' ? 'en' : 'ar';
                $title = $this->localizedMediaValue($asset, 'title', $requestedSuffix, $fallbackSuffix);
                $altText = $this->localizedMediaValue($asset, 'alt_text', $requestedSuffix, $fallbackSuffix);

                if ($title === null || $altText === null) {
                    return null;
                }

                $path = is_string($asset->webp_path) && $asset->webp_path !== '' ? $asset->webp_path : $asset->path;
                $url = MediaUrlResolver::resolve($path, $asset->disk);

                return is_string($url) && $url !== '' ? new PublicMediaAssetDTO(
                    mediaId: $id,
                    url: $url,
                    title: $title,
                    altText: $altText,
                    caption: $this->localizedMediaValue($asset, 'caption', $requestedSuffix, $fallbackSuffix),
                    width: is_int($asset->width) ? $asset->width : null,
                    height: is_int($asset->height) ? $asset->height : null,
                    srcset: is_array($asset->srcset_json) ? $asset->srcset_json : [],
                ) : null;
            })
            ->filter(fn (mixed $asset): bool => $asset instanceof PublicMediaAssetDTO)
            ->values();
    }

    public function publicImagesArePublishable(array $mediaIds): bool
    {
        $ids = array_values(array_unique(array_filter($mediaIds, static fn (mixed $id): bool => is_int($id) && $id > 0)));

        if ($ids === []) {
            return false;
        }

        return MediaAsset::query()
            ->whereIn('id', $ids)
            ->where('library_scope', 'main')
            ->where('media_type', 'image')
            ->where('metadata_status', 'reviewed')
            ->whereNotNull('title_ar')
            ->where('title_ar', '<>', '')
            ->whereNotNull('title_en')
            ->where('title_en', '<>', '')
            ->whereNotNull('alt_text_ar')
            ->where('alt_text_ar', '<>', '')
            ->whereNotNull('alt_text_en')
            ->where('alt_text_en', '<>', '')
            ->count() === count($ids);
    }

    public function find(int|string $mediaId, int $userId): ?MediaUploadResultDTO
    {
        $asset = MediaAsset::query()->find($mediaId);

        if (! $asset instanceof MediaAsset) {
            return null;
        }

        $this->authorizeMediaWrite($userId, 'view', $asset);

        return $this->toDto($asset);
    }

    public function importPublicAsset(string $publicRelativePath, ?int $userId = null): ?MediaUploadResultDTO
    {
        $relativePath = trim(str_replace('\\', '/', $publicRelativePath), '/');

        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return null;
        }

        $fullPath = public_path($relativePath);

        if (! is_file($fullPath)) {
            return null;
        }

        $mimeType = File::mimeType($fullPath) ?: 'application/octet-stream';

        try {
            $extension = $this->fileValidator->primaryExtensionForMime($mimeType);
        } catch (ValidationException) {
            return null;
        }

        $checksum = hash_file('sha256', $fullPath);
        $libraryScope = $this->libraryScopeForPublicPath($relativePath);
        $existing = MediaAsset::query()
            ->where('checksum', $checksum)
            ->where('library_scope', $libraryScope)
            ->first();

        if ($existing instanceof MediaAsset) {
            return $this->toDto($existing);
        }

        $width = null;
        $height = null;
        if (str_starts_with($mimeType, 'image/') && $mimeType !== 'image/svg+xml') {
            $dimensions = @getimagesize($fullPath);
            if (is_array($dimensions)) {
                $width = $dimensions[0];
                $height = $dimensions[1];
            }
        }

        $asset = MediaAsset::query()->create([
            'disk' => $this->diskName,
            'directory' => dirname($relativePath) !== '.' ? dirname($relativePath) : null,
            'filename' => basename($relativePath),
            'original_name' => basename($relativePath),
            'mime_type' => $mimeType,
            'extension' => $extension,
            'size_bytes' => filesize($fullPath) ?: 0,
            'checksum' => $checksum,
            'media_type' => $this->mediaTypeForMime($mimeType),
            'library_scope' => $libraryScope,
            'metadata_status' => 'missing',
            'width' => $width,
            'height' => $height,
            'path' => '/'.$relativePath,
            'source_path' => $libraryScope === 'legacy' ? '/'.$relativePath : null,
            'uploaded_by' => $userId,
        ]);

        if (is_int($userId)) {
            $this->auditService->log(
                action: 'media.imported',
                userId: $userId,
                entityType: MediaAsset::class,
                entityId: (int) $asset->getKey(),
                metadata: ['path' => '/'.$relativePath],
            );
        }

        return $this->toDto($asset);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function promoteLegacyAsset(int|string $mediaId, array $metadata, int $userId): MediaUploadResultDTO
    {
        $legacyAsset = MediaAsset::query()->find($mediaId);

        if (! $legacyAsset instanceof MediaAsset || $legacyAsset->library_scope !== 'legacy') {
            throw ValidationException::withMessages([
                'media_id' => ['A legacy media asset is required for promotion.'],
            ]);
        }

        $this->authorizeMediaWrite($userId, 'view', $legacyAsset);
        $this->authorizeMediaClassWrite($userId, 'create');

        if (isset($metadata['metadata_status']) && ! $this->isValidMetadataStatus($metadata['metadata_status'])) {
            throw ValidationException::withMessages([
                'metadata_status' => ['The selected metadata status is invalid.'],
            ]);
        }

        $promotionMetadata = $this->promotionMetadata($legacyAsset, $metadata);

        if (is_string($legacyAsset->checksum) && $legacyAsset->checksum !== '') {
            $existing = MediaAsset::query()
                ->where('library_scope', 'main')
                ->where('checksum', $legacyAsset->checksum)
                ->first();

            if ($existing instanceof MediaAsset) {
                $this->auditService->log(
                    action: 'media.promotion_reused',
                    userId: $userId,
                    entityType: MediaAsset::class,
                    entityId: (int) $existing->getKey(),
                    metadata: [
                        'legacy_media_id' => (int) $legacyAsset->getKey(),
                        'checksum' => $legacyAsset->checksum,
                    ],
                );

                return $this->toDto($existing);
            }
        }

        $promoted = MediaAsset::query()->create(array_merge([
            'disk' => $legacyAsset->disk,
            'directory' => $legacyAsset->directory,
            'filename' => $legacyAsset->filename,
            'original_name' => $legacyAsset->original_name,
            'mime_type' => $legacyAsset->mime_type,
            'extension' => $legacyAsset->extension,
            'size_bytes' => $legacyAsset->size_bytes,
            'checksum' => $legacyAsset->checksum,
            'media_type' => is_string($legacyAsset->media_type) ? $legacyAsset->media_type : $this->mediaTypeForMime($legacyAsset->mime_type),
            'library_scope' => 'main',
            'metadata_status' => $this->promotionMetadataStatus($metadata),
            'promoted_from_media_id' => (int) $legacyAsset->getKey(),
            'width' => $legacyAsset->width,
            'height' => $legacyAsset->height,
            'path' => $legacyAsset->path,
            'source_path' => $legacyAsset->source_path ?: $legacyAsset->path,
            'webp_path' => $legacyAsset->webp_path,
            'srcset_json' => $legacyAsset->srcset_json,
            'uploaded_by' => $userId,
            'faculty_scope_slug' => $legacyAsset->faculty_scope_slug,
            'reviewed_at' => ($metadata['metadata_status'] ?? null) === 'reviewed' ? now() : null,
            'reviewed_by' => ($metadata['metadata_status'] ?? null) === 'reviewed' ? $userId : null,
        ], $promotionMetadata));

        $this->auditService->log(
            action: 'media.promoted',
            userId: $userId,
            entityType: MediaAsset::class,
            entityId: (int) $promoted->getKey(),
            metadata: [
                'legacy_media_id' => (int) $legacyAsset->getKey(),
                'source_path' => $promoted->source_path,
            ],
        );

        return $this->toDto($promoted);
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
        $libraryScope = isset($filters['library_scope']) && is_string($filters['library_scope'])
            ? $filters['library_scope']
            : 'main';

        if (in_array($libraryScope, ['main', 'legacy'], true)) {
            $query->where('library_scope', $libraryScope);
        } else {
            $query->where('library_scope', 'main');
        }

        if (isset($filters['mime_type']) && is_string($filters['mime_type'])) {
            $query->where('mime_type', 'like', $filters['mime_type'].'%');
        }

        if (isset($filters['media_type']) && is_string($filters['media_type']) && $filters['media_type'] !== '') {
            $query->where('media_type', $filters['media_type']);
        }

        if (isset($filters['metadata_status']) && is_string($filters['metadata_status']) && $filters['metadata_status'] !== '') {
            $query->where('metadata_status', $filters['metadata_status']);
        }

        if (isset($filters['search']) && is_string($filters['search']) && $filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term): void {
                $q->where('filename', 'like', $term)
                    ->orWhere('original_name', 'like', $term)
                    ->orWhere('title_ar', 'like', $term)
                    ->orWhere('title_en', 'like', $term)
                    ->orWhere('path', 'like', $term);
            });
        }

        if (($filters['missing_title'] ?? false) === true) {
            $query->whereNull('title_ar')->whereNull('title_en');
        }

        if (($filters['missing_image_alt'] ?? false) === true) {
            $query->where('media_type', 'image')
                ->whereNull('alt_text_ar')
                ->whereNull('alt_text_en');
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

    private function toDto(MediaAsset $asset): MediaUploadResultDTO
    {
        return new MediaUploadResultDTO(
            mediaId: (int) $asset->id,
            disk: $asset->disk,
            path: $asset->path,
            url: MediaUrlResolver::resolve($asset->path, $asset->disk),
            mimeType: $asset->mime_type,
            size: (int) $asset->size_bytes,
            originalName: $asset->original_name,
            title: $asset->title_ar ?? $asset->title_en,
            altText: $asset->alt_text_ar ?? $asset->alt_text_en,
            caption: $asset->caption_ar ?? $asset->caption_en,
            checksum: is_string($asset->checksum) ? $asset->checksum : null,
            mediaType: is_string($asset->media_type) ? $asset->media_type : $this->mediaTypeForMime($asset->mime_type),
            libraryScope: is_string($asset->library_scope) ? $asset->library_scope : 'main',
            metadataStatus: is_string($asset->metadata_status) ? $asset->metadata_status : 'missing',
            promotedFromMediaId: is_numeric($asset->promoted_from_media_id) ? (int) $asset->promoted_from_media_id : null,
            sourcePath: is_string($asset->source_path) ? $asset->source_path : null,
        );
    }

    private function findReusableAsset(string $checksum, int $userId, ?string $facultyScope): ?MediaAsset
    {
        $user = $this->authorizedListUser($userId);
        $query = MediaAsset::query()
            ->where('checksum', $checksum)
            ->where('library_scope', 'main');

        if ($user->role_slug === 'faculty_editor') {
            $query->where('faculty_scope_slug', $facultyScope);
        }

        return $query->first();
    }

    private function defaultDirectory(string $mediaType): string
    {
        return 'media/'.$mediaType.'/'.now()->format('Y/m');
    }

    private function mediaTypeForMime(string $mimeType): string
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => $mimeType === 'image/svg+xml' ? 'icon' : 'image',
            $mimeType === 'application/pdf' => 'pdf',
            str_starts_with($mimeType, 'video/') => 'video',
            str_starts_with($mimeType, 'application/') => 'document',
            default => 'other',
        };
    }

    private function libraryScopeForPublicPath(string $relativePath): string
    {
        $path = trim(str_replace('\\', '/', $relativePath), '/');

        return str_starts_with($path, 'news/images/') || str_starts_with($path, 'news/files/')
            ? 'legacy'
            : 'main';
    }

    /** @param array<string, mixed> $payload */
    private function validateCleanUploadMetadata(string $mediaType, array $payload): void
    {
        if (! $this->filledString($payload['title_ar'] ?? null) && ! $this->filledString($payload['title_en'] ?? null)) {
            throw ValidationException::withMessages([
                'title' => ['A title is required for main media uploads.'],
            ]);
        }

        if ($mediaType === 'image' && ($payload['require_alt_text'] ?? false) === true) {
            if (! $this->filledString($payload['alt_text_ar'] ?? null) && ! $this->filledString($payload['alt_text_en'] ?? null)) {
                throw ValidationException::withMessages([
                    'alt_text' => ['Alt text is required for CMS image uploads.'],
                ]);
            }
        }
    }

    /** @param array<string, mixed> $payload */
    private function metadataStatusForPayload(string $mediaType, array $payload): string
    {
        $hasTitle = $this->filledString($payload['title_ar'] ?? null) || $this->filledString($payload['title_en'] ?? null);

        if (! $hasTitle) {
            return 'missing';
        }

        if ($mediaType === 'image') {
            $hasAlt = $this->filledString($payload['alt_text_ar'] ?? null) || $this->filledString($payload['alt_text_en'] ?? null);

            return $hasAlt ? 'reviewed' : 'missing';
        }

        return 'reviewed';
    }

    /** @param array<string, mixed> $metadata */
    private function promotionMetadata(MediaAsset $legacyAsset, array $metadata): array
    {
        $titleAr = $this->stringOrNull($metadata['title_ar'] ?? null) ?? $legacyAsset->title_ar;
        $titleEn = $this->stringOrNull($metadata['title_en'] ?? null) ?? $legacyAsset->title_en;

        if (! $this->filledString($titleAr) && ! $this->filledString($titleEn)) {
            throw ValidationException::withMessages([
                'title' => ['A title is required to promote a legacy media asset.'],
            ]);
        }

        $altTextAr = $this->stringOrNull($metadata['alt_text_ar'] ?? null) ?? $legacyAsset->alt_text_ar;
        $altTextEn = $this->stringOrNull($metadata['alt_text_en'] ?? null) ?? $legacyAsset->alt_text_en;

        $mediaType = is_string($legacyAsset->media_type) ? $legacyAsset->media_type : $this->mediaTypeForMime($legacyAsset->mime_type);

        if ($mediaType === 'image' && ! $this->filledString($altTextAr) && ! $this->filledString($altTextEn)) {
            throw ValidationException::withMessages([
                'alt_text' => ['Alt text is required to promote a legacy image asset.'],
            ]);
        }

        return [
            'title_ar' => $titleAr,
            'title_en' => $titleEn,
            'alt_text_ar' => $altTextAr,
            'alt_text_en' => $altTextEn,
            'caption_ar' => $this->stringOrNull($metadata['caption_ar'] ?? null) ?? $legacyAsset->caption_ar,
            'caption_en' => $this->stringOrNull($metadata['caption_en'] ?? null) ?? $legacyAsset->caption_en,
        ];
    }

    /** @param array<string, mixed> $metadata */
    private function promotionMetadataStatus(array $metadata): string
    {
        $status = $metadata['metadata_status'] ?? null;

        if ($status === 'reviewed') {
            return 'reviewed';
        }

        if ($status === 'auto_generated') {
            return 'auto_generated';
        }

        return 'auto_generated';
    }

    private function isValidMetadataStatus(mixed $status): bool
    {
        return in_array($status, ['missing', 'auto_generated', 'reviewed'], true);
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private function filledString(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private function localizedMediaValue(MediaAsset $asset, string $field, string $requestedSuffix, string $fallbackSuffix): ?string
    {
        return $this->stringOrNull($asset->getAttribute($field.'_'.$requestedSuffix))
            ?? $this->stringOrNull($asset->getAttribute($field.'_'.$fallbackSuffix));
    }

    private function invalidatePublicMediaCache(): void
    {
        if (! $this->cacheService->flushTags(['media', 'news', 'public-pages', 'public-shell', 'seo'])) {
            $this->cacheService->flushAll();
        }
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
