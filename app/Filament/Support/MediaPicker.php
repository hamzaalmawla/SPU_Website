<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Contracts\Media\MediaServiceInterface;
use App\DTOs\Media\MediaUploadResultDTO;
use App\Support\MediaUrlResolver;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Throwable;

final class MediaPicker
{
    public static function image(string $statePath, ?string $label = null, bool $required = false): Grid
    {
        return self::make($statePath, $label ?? __('admin.media_picker.image'), 'image', $required);
    }

    public static function document(string $statePath, ?string $label = null, bool $required = false): Grid
    {
        return self::make($statePath, $label ?? __('admin.media_picker.document'), 'document', $required);
    }

    public static function any(string $statePath, ?string $label = null, bool $required = false): Grid
    {
        return self::make($statePath, $label ?? __('admin.media_picker.media_file'), 'any', $required);
    }

    public static function assetImage(string $statePath, ?string $label = null, bool $required = false): Grid
    {
        return self::asset($statePath, $label ?? __('admin.media_picker.image'), 'image', $required);
    }

    public static function assetDocument(string $statePath, ?string $label = null, bool $required = false): Grid
    {
        return self::asset($statePath, $label ?? __('admin.media_picker.document'), 'document', $required);
    }

    public static function assetAny(string $statePath, ?string $label = null, bool $required = false): Grid
    {
        return self::asset($statePath, $label ?? __('admin.media_picker.media_file'), 'any', $required);
    }

    public static function icon(string $statePath, ?string $label = null, bool $required = false): Grid
    {
        return self::make($statePath, $label ?? __('admin.media_picker.icon'), 'image', $required);
    }

    public static function lightImage(string $statePath, ?string $label = null, bool $required = false): Grid
    {
        return self::light($statePath, $label ?? __('admin.media_picker.image'), 'image', $required);
    }

    public static function lightDocument(string $statePath, ?string $label = null, bool $required = false): Grid
    {
        return self::light($statePath, $label ?? __('admin.media_picker.document'), 'document', $required);
    }

    public static function lightAny(string $statePath, ?string $label = null, bool $required = false): Grid
    {
        return self::light($statePath, $label ?? __('admin.media_picker.media_file'), 'any', $required);
    }

    public static function selectedUrl(int|string|null $mediaId): ?string
    {
        if ($mediaId === null || $mediaId === '') {
            return null;
        }

        $userId = auth()->id();

        if (! is_numeric($userId)) {
            return null;
        }

        try {
            return app(MediaServiceInterface::class)->find($mediaId, (int) $userId)?->url;
        } catch (Throwable) {
            return null;
        }
    }

    private static function make(string $statePath, string $label, string $type, bool $required): Grid
    {
        $mediaIdPath = self::mediaIdPath($statePath);

        return Grid::make(1)
            ->schema([
                Hidden::make($mediaIdPath),
                Hidden::make($statePath)
                    ->required($required)
                    ->dehydrated(true),
                Placeholder::make($statePath.'_selection')
                    ->key(self::componentKey($statePath, $label, $type))
                    ->label($label)
                    ->helperText(__('admin.media_picker.existing_value_help'))
                    ->content(fn (Get $get): HtmlString|string => self::preview($get($statePath), $get($mediaIdPath)))
                    ->hintActions([
                        self::chooseOrUploadAction($statePath, $mediaIdPath, $type),
                        self::clearAction($statePath, $mediaIdPath),
                    ]),
            ]);
    }

    private static function light(string $statePath, string $label, string $type, bool $required): Grid
    {
        $mediaIdPath = self::mediaIdPath($statePath);

        return Grid::make(1)
            ->schema([
                Hidden::make($mediaIdPath),
                Hidden::make($statePath)
                    ->required($required)
                    ->dehydrated(true),
                Placeholder::make($statePath.'_selection')
                    ->key(self::componentKey($statePath, $label, $type))
                    ->label($label)
                    ->helperText(__('admin.media_picker.choose_help'))
                    ->content(fn (Get $get): HtmlString|string => self::preview($get($statePath), $get($mediaIdPath)))
                    ->hintActions([
                        self::chooseOrUploadAction($statePath, $mediaIdPath, $type),
                        self::clearAction($statePath, $mediaIdPath),
                    ]),
            ]);
    }

    private static function chooseOrUploadAction(string $statePath, string $mediaIdPath, string $type): FormAction
    {
        return FormAction::make('choose_or_upload_media')
            ->label(__('admin.media_picker.choose_upload'))
            ->icon('heroicon-o-photo')
            ->modalHeading(__('admin.media_picker.modal_heading'))
            ->form([
                Select::make('media_id')
                    ->label(__('admin.media_picker.choose_existing'))
                    ->helperText(__('admin.media_picker.choose_existing_help'))
                    ->live()
                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                        if (is_numeric($state)) {
                            $set('legacy_media_id', null);
                            $set('file', null);
                        }
                    })
                    ->searchable()
                    ->native(false)
                    ->preload(false)
                    ->options(fn (): array => self::options($type))
                    ->getSearchResultsUsing(fn (string $search): array => self::options($type, $search))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => self::optionLabel($value)),
                Select::make('legacy_media_id')
                    ->label(__('admin.media_picker.promote_legacy'))
                    ->helperText(__('admin.media_picker.promote_legacy_help'))
                    ->live()
                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                        if (is_numeric($state)) {
                            $set('media_id', null);
                            $set('file', null);
                        }
                    })
                    ->searchable()
                    ->native(false)
                    ->preload(false)
                    ->options(fn (): array => [])
                    ->getSearchResultsUsing(fn (string $search): array => self::options($type, $search, 'legacy'))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => self::optionLabel($value)),
                FileUpload::make('file')
                    ->label(__('admin.media_picker.upload_new'))
                    ->helperText(__('admin.media_picker.upload_new_help'))
                    ->live()
                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                        if (self::uploadedPath($state) !== null) {
                            $set('media_id', null);
                            $set('legacy_media_id', null);
                        }
                    })
                    ->disk((string) config('filesystems.media_disk', 'public'))
                    ->directory('media-tmp')
                    ->visibility('public')
                    ->acceptedFileTypes(self::acceptedFileTypes($type))
                    ->maxSize(20480),
                TextInput::make('title_ar')
                    ->label(__('admin.media_picker.title_ar'))
                    ->maxLength(255)
                    ->required(fn (Get $get): bool => self::requiresUploadOrPromotionMetadata($get) && ! self::filledString($get('title_en'))),
                TextInput::make('title_en')
                    ->label(__('admin.media_picker.title_en'))
                    ->maxLength(255)
                    ->required(fn (Get $get): bool => self::requiresUploadOrPromotionMetadata($get) && ! self::filledString($get('title_ar'))),
                TextInput::make('alt_text_ar')
                    ->label(__('admin.media_picker.alt_ar'))
                    ->maxLength(500)
                    ->visible(self::isImageType($type))
                    ->required(fn (Get $get): bool => self::isImageType($type) && self::requiresUploadOrPromotionMetadata($get) && ! self::filledString($get('alt_text_en'))),
                TextInput::make('alt_text_en')
                    ->label(__('admin.media_picker.alt_en'))
                    ->maxLength(500)
                    ->visible(self::isImageType($type))
                    ->required(fn (Get $get): bool => self::isImageType($type) && self::requiresUploadOrPromotionMetadata($get) && ! self::filledString($get('alt_text_ar'))),
            ])
            ->action(function (array $data, Set $set) use ($statePath, $mediaIdPath, $type): void {
                try {
                    $mediaId = null;

                    if (self::uploadedPath($data['file'] ?? null) !== null) {
                        $mediaId = self::uploadOption($data, $type);
                    } elseif (is_numeric($data['media_id'] ?? null)) {
                        $mediaId = (int) $data['media_id'];
                    } elseif (is_numeric($data['legacy_media_id'] ?? null)) {
                        $mediaId = self::promoteLegacyOption((int) $data['legacy_media_id'], $data);
                    }

                    if ($mediaId === null) {
                        Notification::make()->title(__('admin.media_picker.selection_required'))->warning()->send();

                        return;
                    }

                    $url = self::selectedUrl($mediaId);

                    if ($url === null) {
                        throw new \RuntimeException('The selected media URL could not be resolved.');
                    }

                    $set($mediaIdPath, $mediaId);
                    $set($statePath, $url);
                } catch (Throwable $exception) {
                    report($exception);
                    Notification::make()->title(__('admin.media_picker.action_failed'))->body(__('admin.media_picker.safe_error'))->danger()->send();
                }
            });
    }

    private static function asset(string $statePath, string $label, string $type, bool $required): Grid
    {
        return Grid::make(1)
            ->schema([
                self::select($statePath, $label, $type)
                    ->required($required),
                Placeholder::make($statePath.'_preview')
                    ->label(__('admin.media_picker.selected_file'))
                    ->content(fn (Get $get): HtmlString|string => self::preview(self::selectedUrl($get($statePath)), $get($statePath))),
            ]);
    }

    private static function select(string $statePath, string $label, string $type): Select
    {
        return Select::make($statePath)
            ->label($label)
            ->helperText(__('admin.media_picker.asset_help'))
            ->nullable()
            ->searchable()
            ->native(false)
            ->preload(false)
            ->options(fn (): array => self::options($type))
            ->getSearchResultsUsing(fn (string $search): array => self::options($type, $search))
            ->getOptionLabelUsing(fn (mixed $value): ?string => self::optionLabel($value))
            ->createOptionForm([
                FileUpload::make('file')
                    ->label(__('admin.media_picker.upload_device'))
                    ->required()
                    ->disk((string) config('filesystems.media_disk', 'public'))
                    ->directory('media-tmp')
                    ->visibility('public')
                    ->acceptedFileTypes(self::acceptedFileTypes($type))
                    ->maxSize(20480),
                TextInput::make('title_ar')
                    ->label(__('admin.media_picker.title_ar'))
                    ->maxLength(255)
                    ->required(fn (Get $get): bool => ! self::filledString($get('title_en'))),
                TextInput::make('title_en')
                    ->label(__('admin.media_picker.title_en'))
                    ->maxLength(255)
                    ->required(fn (Get $get): bool => ! self::filledString($get('title_ar'))),
                TextInput::make('alt_text_ar')
                    ->label(__('admin.media_picker.alt_ar'))
                    ->maxLength(500)
                    ->visible(self::isImageType($type))
                    ->required(fn (Get $get): bool => self::isImageType($type) && ! self::filledString($get('alt_text_en'))),
                TextInput::make('alt_text_en')
                    ->label(__('admin.media_picker.alt_en'))
                    ->maxLength(500)
                    ->visible(self::isImageType($type))
                    ->required(fn (Get $get): bool => self::isImageType($type) && ! self::filledString($get('alt_text_ar'))),
            ])
            ->createOptionUsing(function (array $data) use ($type): int {
                try {
                    return self::uploadOption($data, $type);
                } catch (Throwable $exception) {
                    report($exception);

                    throw ValidationException::withMessages([
                        'file' => __('admin.media_picker.safe_error'),
                    ]);
                }
            })
            ->dehydrated(true);
    }

    private static function clearAction(string $statePath, string $mediaIdPath): FormAction
    {
        return FormAction::make('clear_media')
            ->label(__('admin.media_picker.clear'))
            ->icon('heroicon-o-x-mark')
            ->color('gray')
            ->action(function (Set $set) use ($statePath, $mediaIdPath): void {
                $set($mediaIdPath, null);
                $set($statePath, null);
            });
    }

    /** @return array<int|string, string> */
    private static function options(string $type, string $search = '', string $libraryScope = 'main'): array
    {
        $userId = auth()->id();

        if (! is_numeric($userId)) {
            return [];
        }

        if ($libraryScope === 'legacy' && trim($search) === '') {
            return [];
        }

        $filters = [
            'library_scope' => $libraryScope,
            'per_page' => 50,
        ];

        if ($search !== '') {
            $filters['search'] = $search;
        }

        if (self::isImageType($type)) {
            $filters['mime_type'] = 'image/';
        } elseif ($type === 'document') {
            $filters['mime_type'] = 'application/';
        }

        try {
            return app(MediaServiceInterface::class)
                ->list((int) $userId, $filters)
                ->take(50)
                ->mapWithKeys(fn (MediaUploadResultDTO $media): array => [$media->mediaId => self::labelFor($media)])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private static function optionLabel(mixed $mediaId): ?string
    {
        $userId = auth()->id();

        if (! is_numeric($userId) || (! is_int($mediaId) && ! is_string($mediaId))) {
            return null;
        }

        try {
            $media = app(MediaServiceInterface::class)->find($mediaId, (int) $userId);

            return $media instanceof MediaUploadResultDTO ? self::labelFor($media) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $data */
    private static function uploadOption(array $data, string $type): int
    {
        $filePath = self::uploadedPath($data['file'] ?? null);
        $userId = auth()->id();

        if ($filePath === null || ! is_numeric($userId)) {
            throw new \RuntimeException('A valid uploaded file and authenticated user are required.');
        }

        $disk = Storage::disk((string) config('filesystems.media_disk', 'public'));
        $fullPath = $disk->path($filePath);

        if (! is_file($fullPath)) {
            throw new \RuntimeException('The uploaded file could not be read.');
        }

        $file = new UploadedFile(
            $fullPath,
            basename($filePath),
            $disk->mimeType($filePath) ?: null,
            null,
            true,
        );

        $result = app(MediaServiceInterface::class)->upload([
            'file' => $file,
            'title_ar' => $data['title_ar'] ?? null,
            'title_en' => $data['title_en'] ?? null,
            'alt_text_ar' => $data['alt_text_ar'] ?? null,
            'alt_text_en' => $data['alt_text_en'] ?? null,
            'require_alt_text' => self::isImageType($type),
            'uploaded_by' => (int) $userId,
        ]);

        $disk->delete($filePath);

        return $result->mediaId;
    }

    /** @param array<string, mixed> $data */
    private static function promoteLegacyOption(int $mediaId, array $data): int
    {
        $userId = auth()->id();

        if (! is_numeric($userId)) {
            throw new \RuntimeException('An authenticated user is required to promote legacy media.');
        }

        $result = app(MediaServiceInterface::class)->promoteLegacyAsset($mediaId, [
            'title_ar' => $data['title_ar'] ?? null,
            'title_en' => $data['title_en'] ?? null,
            'alt_text_ar' => $data['alt_text_ar'] ?? null,
            'alt_text_en' => $data['alt_text_en'] ?? null,
            'metadata_status' => 'reviewed',
        ], (int) $userId);

        return $result->mediaId;
    }

    private static function uploadedPath(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (is_array($value)) {
            return array_values(array_filter($value, static fn (mixed $item): bool => is_string($item) && $item !== ''))[0] ?? null;
        }

        return null;
    }

    private static function requiresUploadOrPromotionMetadata(Get $get): bool
    {
        return self::uploadedPath($get('file')) !== null || is_numeric($get('legacy_media_id'));
    }

    private static function isImageType(string $type): bool
    {
        return in_array($type, ['image', 'icon'], true);
    }

    private static function filledString(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private static function labelFor(MediaUploadResultDTO $media): string
    {
        $title = $media->title !== null && $media->title !== '' ? $media->title.' - ' : '';

        return $title.$media->originalName.' ('.strtoupper($media->mediaType).', '.self::formatFileSize($media->size).')';
    }

    private static function preview(mixed $value, mixed $mediaId = null): HtmlString|string
    {
        $url = is_string($value) ? MediaUrlResolver::resolve($value) : null;

        if ($url === null || $url === '') {
            return __('admin.media_picker.no_file_selected');
        }

        $escapedUrl = e($url);
        $filename = self::filenameFromUrl($url);
        $escapedFilename = e($filename);

        if (self::isImageMedia($mediaId) || preg_match('/\.(jpe?g|png|gif|webp)(\?.*)?$/i', $url) === 1) {
            $alt = e(__('admin.media_picker.preview_alt', ['file' => $filename]));

            return new HtmlString('<span class="spu-media-preview"><img src="'.$escapedUrl.'" alt="'.$alt.'" /><span>'.$escapedFilename.'</span></span>');
        }

        $ariaLabel = e(__('admin.media_picker.open_file', ['file' => $filename]));

        return new HtmlString('<a class="spu-media-preview spu-media-preview--link" href="'.$escapedUrl.'" target="_blank" rel="noopener noreferrer" aria-label="'.$ariaLabel.'">'.$escapedFilename.'</a>');
    }

    private static function isImageMedia(mixed $mediaId): bool
    {
        $userId = auth()->id();

        if (! is_numeric($userId) || (! is_int($mediaId) && ! is_string($mediaId)) || ! is_numeric($mediaId)) {
            return false;
        }

        try {
            $media = app(MediaServiceInterface::class)->find($mediaId, (int) $userId);

            return $media instanceof MediaUploadResultDTO && str_starts_with($media->mimeType, 'image/');
        } catch (Throwable) {
            return false;
        }
    }

    private static function filenameFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $filename = is_string($path) ? rawurldecode(basename($path)) : '';

        return $filename !== '' ? $filename : __('admin.media_picker.selected_file');
    }

    /** @return list<string> */
    private static function acceptedFileTypes(string $type): array
    {
        $images = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $documents = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];

        return match ($type) {
            'image', 'icon' => $images,
            'document' => $documents,
            default => [...$images, ...$documents, 'video/mp4', 'video/webm'],
        };
    }

    private static function mediaIdPath(string $statePath): string
    {
        $segments = explode('.', $statePath);
        $last = array_pop($segments) ?: $statePath;
        $segments[] = $last.'MediaId';

        return implode('.', $segments);
    }

    private static function componentKey(string $statePath, string $label, string $type): string
    {
        return 'media-picker-'.sha1($statePath.'|'.$label.'|'.$type);
    }

    private static function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}
