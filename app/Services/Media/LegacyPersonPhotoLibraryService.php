<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Contracts\Media\LegacyPersonPhotoLibraryServiceInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\DTOs\Media\LegacyPersonPhotoLibraryReportDTO;
use App\Models\Career\Alumni;
use App\Models\Career\HonorStudent;
use App\Models\Faculty\Faculty;
use App\Models\Media\MediaAsset;
use App\Models\Person\FacultyMember;
use App\Models\Person\Person;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

/**
 * People were imported with their photo as a bare legacy file name such as
 * `1637398791_74758571.jpg`. Those files sit in the old document root
 * (served through the `_legacy` mount as /downloads/files/...) but in no media
 * library, so the picker's legacy search could never offer them, and nobody
 * searches for a timestamp anyway.
 *
 * Each photo becomes one legacy media asset titled with the AR and EN names of
 * everyone it shows. The asset points at the same public path the site already
 * renders, on the public disk like the rest of the legacy library.
 */
final class LegacyPersonPhotoLibraryService implements LegacyPersonPhotoLibraryServiceInterface
{
    /** Approved legacy static trees, in search order. */
    private const DIRECTORIES = [
        'downloads/files',
        'downloads/files/thumb',
        'downloads/files2',
        'images',
    ];

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    private const NAME_SEPARATOR = ' / ';

    public function __construct(
        private readonly AuditServiceInterface $auditService,
    ) {}

    public function register(string $legacyRoot, bool $write, ?int $userId = null): LegacyPersonPhotoLibraryReportDTO
    {
        $root = rtrim(str_replace('\\', '/', $legacyRoot), '/');
        $referencesBySource = [];
        $missing = [];
        $notImage = 0;

        /** @var array<string, array{ar: list<string>, en: list<string>, scopes: array<string, true>, unscoped: bool}> $photos */
        $photos = [];

        foreach ($this->sources() as $source => [$query, $nameColumn, $scopeResolver]) {
            $referencesBySource[$source] = 0;

            $query->chunkById(500, function (EloquentCollection $records) use ($source, $nameColumn, $scopeResolver, $root, &$referencesBySource, &$missing, &$notImage, &$photos): void {
                foreach ($records as $record) {
                    $value = trim((string) $record->getAttribute('legacy_photo_path'));

                    if ($value === '') {
                        continue;
                    }

                    $referencesBySource[$source]++;
                    $relative = $this->relativePath($value);

                    if ($relative === null || ! $this->hasImageExtension($relative)) {
                        $notImage++;

                        continue;
                    }

                    $found = $this->locate($root, $relative);

                    if ($found === null) {
                        $missing[$value] = $value;

                        continue;
                    }

                    $photo = $photos[$found] ?? ['ar' => [], 'en' => [], 'scopes' => [], 'unscoped' => false];

                    foreach (['ar', 'en'] as $locale) {
                        $name = $this->name($record, $nameColumn, $locale);

                        if ($name !== null && ! in_array($name, $photo[$locale], true)) {
                            $photo[$locale][] = $name;
                        }
                    }

                    $scope = $scopeResolver($record);

                    if ($scope === null) {
                        $photo['unscoped'] = true;
                    } else {
                        $photo['scopes'][$scope] = true;
                    }

                    $photos[$found] = $photo;
                }
            });
        }

        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $inMainLibrary = 0;

        ksort($photos);

        foreach ($photos as $relative => $photo) {
            $publicPath = '/'.$relative;
            $existing = MediaAsset::withTrashed()
                ->where(fn (Builder $query) => $query->where('path', $publicPath)->orWhere('source_path', $publicPath))
                ->orderByRaw("case when library_scope = 'legacy' then 0 else 1 end")
                ->orderBy('id')
                ->first();
            $titleAr = $this->joinNames($photo['ar']);
            $titleEn = $this->joinNames($photo['en']);

            if ($existing instanceof MediaAsset && $existing->library_scope !== 'legacy') {
                $inMainLibrary++;

                continue;
            }

            if ($existing instanceof MediaAsset) {
                // An editor deleted it or already named it: keep their decision.
                $changes = $existing->trashed() ? [] : $this->missingMetadata($existing, $titleAr, $titleEn);

                if ($changes === []) {
                    $unchanged++;

                    continue;
                }

                if ($write) {
                    $existing->forceFill($changes)->save();
                }

                $updated++;

                continue;
            }

            $file = $this->fileDetails($root.'/'.$relative);

            if ($file === null) {
                $notImage++;

                continue;
            }

            if ($write) {
                MediaAsset::query()->create([
                    'disk' => 'public',
                    'directory' => dirname($relative),
                    'filename' => basename($relative),
                    'original_name' => basename($relative),
                    'mime_type' => $file['mime_type'],
                    'extension' => strtolower(pathinfo($relative, PATHINFO_EXTENSION)),
                    'size_bytes' => $file['size_bytes'],
                    'checksum' => $file['checksum'],
                    'media_type' => 'image',
                    'library_scope' => 'legacy',
                    'metadata_status' => $titleAr !== null || $titleEn !== null ? 'auto_generated' : 'missing',
                    'width' => $file['width'],
                    'height' => $file['height'],
                    'title_ar' => $titleAr,
                    'title_en' => $titleEn,
                    'alt_text_ar' => $titleAr,
                    'alt_text_en' => $titleEn,
                    'path' => $publicPath,
                    'source_path' => $publicPath,
                    'faculty_scope_slug' => ! $photo['unscoped'] && count($photo['scopes']) === 1 ? array_key_first($photo['scopes']) : null,
                    'uploaded_by' => $userId,
                ]);
            }

            $created++;
        }

        $missing = array_values($missing);
        sort($missing);

        if ($write && $userId !== null && ($created > 0 || $updated > 0)) {
            $this->auditService->log('media.legacy_person_photos_registered', $userId, MediaAsset::class, null, [
                'created' => $created,
                'updated' => $updated,
            ]);
        }

        return new LegacyPersonPhotoLibraryReportDTO(
            written: $write,
            referencesBySource: $referencesBySource,
            photoCount: count($photos),
            createdCount: $created,
            updatedCount: $updated,
            unchangedCount: $unchanged,
            inMainLibraryCount: $inMainLibrary,
            notImageCount: $notImage,
            missingFiles: $missing,
        );
    }

    /**
     * Each source: its query, the translation column holding the name, and how
     * to read the faculty scope a faculty editor would need to see the photo.
     *
     * @return array<string, array{0: Builder<Model>, 1: string, 2: callable(Model): ?string}>
     */
    private function sources(): array
    {
        $facultyScope = static function (Model $record): ?string {
            $faculty = $record->getRelationValue('faculty');
            $scope = $faculty instanceof Faculty ? trim((string) $faculty->faculty_scope_slug) : '';

            return $scope !== '' ? $scope : null;
        };
        $withPhoto = static fn (Builder $query): Builder => $query
            ->whereNotNull('legacy_photo_path')
            ->where('legacy_photo_path', '<>', '');

        /** @var array<string, array{0: Builder<Model>, 1: string, 2: callable(Model): ?string}> */
        return [
            'honor_students' => [$withPhoto(HonorStudent::query()->with(['translations', 'faculty'])), 'full_name', $facultyScope],
            'alumni' => [$withPhoto(Alumni::query()->with(['translations', 'faculty'])), 'full_name', $facultyScope],
            'faculty_members' => [$withPhoto(FacultyMember::query()->with(['translations', 'faculty'])), 'full_name', $facultyScope],
            'persons' => [$withPhoto(Person::query()->with('translations')), 'name', static function (Model $record): ?string {
                $scope = trim((string) $record->getAttribute('faculty_scope_slug'));

                return $scope !== '' ? $scope : null;
            }],
        ];
    }

    private function name(Model $record, string $column, string $locale): ?string
    {
        $translations = $record->getRelationValue('translations');

        if (! $translations instanceof EloquentCollection) {
            return null;
        }

        $translation = $translations->first(fn (Model $translation): bool => $translation->getAttribute('locale') === $locale);
        $name = $translation instanceof Model ? trim((string) preg_replace('/\s+/u', ' ', (string) $translation->getAttribute($column))) : '';

        return $name !== '' ? $name : null;
    }

    /**
     * The host-less path of a legacy value, or null when it cannot be one.
     */
    private function relativePath(string $value): ?string
    {
        $value = str_replace('\\', '/', $value);

        if (preg_match('#^(https?:)?//#i', $value) === 1) {
            $path = parse_url($value, PHP_URL_PATH);
            $value = is_string($path) ? $path : '';
        }

        $path = ltrim(rawurldecode($value), '/');

        return $path === '' || str_contains($path, '..') ? null : $path;
    }

    private function hasImageExtension(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::IMAGE_EXTENSIONS, true);
    }

    /**
     * Where the file is under the legacy root. A bare file name is looked up in
     * each legacy tree, retrying case variants for a case-sensitive filesystem.
     */
    private function locate(string $root, string $relative): ?string
    {
        if (str_contains($relative, '/')) {
            return is_file($root.'/'.$relative) ? $relative : null;
        }

        foreach ([$relative, mb_strtolower($relative), mb_strtoupper($relative)] as $candidate) {
            foreach (self::DIRECTORIES as $directory) {
                if (is_file($root.'/'.$directory.'/'.$candidate)) {
                    return $directory.'/'.$candidate;
                }
            }
        }

        return null;
    }

    /**
     * @return array{mime_type: string, size_bytes: int, checksum: string|null, width: int|null, height: int|null}|null
     */
    private function fileDetails(string $absolutePath): ?array
    {
        $mimeType = File::mimeType($absolutePath);

        if (! is_string($mimeType) || ! str_starts_with($mimeType, 'image/') || $mimeType === 'image/svg+xml') {
            return null;
        }

        $dimensions = @getimagesize($absolutePath);
        $checksum = @hash_file('sha256', $absolutePath);

        return [
            'mime_type' => $mimeType,
            'size_bytes' => (int) (@filesize($absolutePath) ?: 0),
            'checksum' => is_string($checksum) ? $checksum : null,
            'width' => is_array($dimensions) ? (int) $dimensions[0] : null,
            'height' => is_array($dimensions) ? (int) $dimensions[1] : null,
        ];
    }

    /**
     * Only the fields still empty on an existing legacy asset.
     *
     * @return array<string, string>
     */
    private function missingMetadata(MediaAsset $asset, ?string $titleAr, ?string $titleEn): array
    {
        $changes = [];

        foreach (['title_ar' => $titleAr, 'alt_text_ar' => $titleAr, 'title_en' => $titleEn, 'alt_text_en' => $titleEn] as $field => $value) {
            if ($value !== null && trim((string) $asset->getAttribute($field)) === '') {
                $changes[$field] = $value;
            }
        }

        if ($changes !== [] && $asset->metadata_status === 'missing') {
            $changes['metadata_status'] = 'auto_generated';
        }

        return $changes;
    }

    /** @param  list<string>  $names */
    private function joinNames(array $names): ?string
    {
        if ($names === []) {
            return null;
        }

        $joined = implode(self::NAME_SEPARATOR, $names);

        return mb_strlen($joined) > 255 ? rtrim(mb_substr($joined, 0, 254)).'…' : $joined;
    }
}
