<?php

declare(strict_types=1);

namespace App\Filament\Resources\MediaAssetResource\Pages;

use App\Contracts\Media\MediaServiceInterface;
use App\Filament\Components\FocalPointPicker;
use App\Filament\Resources\MediaAssetResource;
use App\Support\MediaUploadFailure;
use App\Support\MediaUrlResolver;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

final class BatchUploadMediaAssets extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = MediaAssetResource::class;

    protected static string $view = 'filament.resources.media-asset-resource.pages.batch-upload-media-assets';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /**
     * Images still to store, processed a few per request (see processNextChunk).
     * Locked: the browser must not be able to point this at other files.
     *
     * @var list<array{path: string, item: array<string, mixed>}>
     */
    #[Locked]
    public array $pendingUploads = [];

    #[Locked]
    public int $batchTotal = 0;

    #[Locked]
    public int $uploadedCount = 0;

    /**
     * Storing an image converts it to WebP, which takes a second or more each on
     * this host. All 50 in one request outran the PHP and proxy time limits and
     * the batch died part-way, so the work is split across requests.
     */
    private const IMAGES_PER_REQUEST = 4;

    private const TEMP_DIRECTORY = 'media-tmp/';

    private MediaServiceInterface $mediaService;

    public function boot(MediaServiceInterface $mediaService): void
    {
        $this->mediaService = $mediaService;
    }

    public function mount(): void
    {
        $this->form->fill(['files' => [], 'items' => []]);
    }

    public function getTitle(): string
    {
        return __('admin_resources.media_asset.batch_upload_images');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('admin_resources.media_asset.1_select_images'))
                    ->description(__('admin_resources.media_asset.drop_all_images_together_wait_until_every_preview_says_uploa'))
                    ->schema([
                        FileUpload::make('files')
                            ->label(__('admin_resources.media_asset.images'))
                            ->multiple()
                            ->reorderable()
                            ->appendFiles()
                            ->panelLayout('grid')
                            ->imagePreviewHeight('180')
                            ->image()
                            // What the media library accepts. iPhone HEIC photos are
                            // refused on save otherwise, after a full upload.
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                            ->helperText(__('admin_resources.media_asset.jpg_png_webp_or_gif_up_to_10_mb_each_export_iphone_heic_phot'))
                            ->orientImagesFromExif()
                            ->maxFiles(50)
                            ->maxSize(10240)
                            ->disk((string) config('filesystems.media_disk', 'public'))
                            ->directory('media-tmp')
                            ->visibility('public')
                            ->getUploadedFileNameForStorageUsing(static function (TemporaryUploadedFile $file): string {
                                $original = preg_replace('/[^\pL\pN._ -]+/u', '-', $file->getClientOriginalName()) ?: 'image';

                                return hash('sha256', $file->getFilename()).'--'.$original;
                            })
                            ->required(),
                        Actions::make([
                            Action::make('prepare_image_details')
                                ->label(__('admin_resources.media_asset.continue_to_name_and_frame_images'))
                                ->icon('heroicon-o-arrow-down')
                                ->color('primary')
                                ->extraAttributes([
                                    'wire:loading.attr' => 'disabled',
                                ])
                                ->action(function (): void {
                                    $this->prepareImageDetails();
                                }),
                        ])->alignEnd(),
                    ]),
                Section::make(__('admin_resources.media_asset.2_name_and_frame_every_image'))
                    ->description(__('admin_resources.media_asset.names_and_alternative_text_are_required_in_arabic_and_englis'))
                    ->schema([
                        Repeater::make('items')
                            ->label(__('admin_resources.media_asset.image_details'))
                            ->schema([
                                Hidden::make('upload_key')->required(),
                                Hidden::make('preview_url'),
                                Hidden::make('original_name')->required(),
                                Grid::make(2)->schema([
                                    TextInput::make('title_ar')->label(__('admin_resources.media_asset.name_ar'))->required()->maxLength(255),
                                    TextInput::make('title_en')->label(__('admin_resources.media_asset.name_en'))->required()->maxLength(255),
                                    TextInput::make('alt_text_ar')->label(__('admin_resources.media_asset.alternative_text_ar'))->required()->maxLength(500),
                                    TextInput::make('alt_text_en')->label(__('admin_resources.media_asset.alternative_text_en'))->required()->maxLength(500),
                                ]),
                                Hidden::make('display_fit')->default('cover')->required(),
                                FocalPointPicker::make('focal_x')
                                    ->label(__('admin.media_picker.focal_label'))
                                    ->imageUrl(fn (Get $get): ?string => is_string($get('preview_url')) ? $get('preview_url') : null)
                                    ->rules(['numeric', 'min:0', 'max:100'])
                                    ->default(50)
                                    ->required(),
                                Hidden::make('focal_y')->rules(['numeric', 'min:0', 'max:100'])->default(50)->required(),
                            ])
                            ->itemLabel(fn (array $state): string => (string) ($state['original_name'] ?? 'Image'))
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->collapsible()
                            ->required(),
                    ])
                    ->visible(fn (): bool => is_array($this->data['items'] ?? null) && $this->data['items'] !== []),
            ])
            ->statePath('data');
    }

    public function prepareImageDetails(): void
    {
        $uploads = array_values(is_array($this->data['files'] ?? null) ? $this->data['files'] : []);
        if ($uploads === []) {
            Notification::make()->title(__('admin_resources.media_asset.upload_at_least_one_image_first'))->warning()->send();

            return;
        }

        $existing = collect(is_array($this->data['items'] ?? null) ? $this->data['items'] : [])->keyBy('upload_key');
        $items = array_values(array_filter(array_map(function (mixed $upload, int $index) use ($existing): ?array {
            $file = $this->uploadState($upload, $index);
            if ($file === null) {
                return null;
            }

            $current = $existing->get($file['key']);
            if (is_array($current)) {
                $current['preview_url'] = $file['preview_url'];

                return $current;
            }

            $originalName = $file['original_name'];
            $name = Str::of(pathinfo($originalName, PATHINFO_FILENAME))
                ->replace(['_', '-'], ' ')
                ->squish()
                ->title()
                ->toString();

            return [
                'upload_key' => $file['key'],
                'preview_url' => $file['preview_url'],
                'original_name' => $originalName,
                'title_ar' => $name,
                'title_en' => $name,
                'alt_text_ar' => '',
                'alt_text_en' => '',
                'focal_x' => 50,
                'focal_y' => 50,
                'display_fit' => 'cover',
            ];
        }, $uploads, array_keys($uploads))));

        if (count($items) !== count($uploads)) {
            Notification::make()
                ->title(__('admin_resources.media_asset.some_images_are_still_uploading'))
                ->body(__('admin_resources.media_asset.wait_until_every_image_says_upload_complete_then_continue_ag'))
                ->warning()
                ->send();

            return;
        }

        $this->data['items'] = $items;
    }

    public function storeBatch(): void
    {
        if ($this->pendingUploads !== []) {
            return;
        }

        $data = $this->form->getState();
        $items = array_values(is_array($data['items'] ?? null) ? $data['items'] : []);
        $files = array_values(array_filter(
            is_array($data['files'] ?? null) ? $data['files'] : [],
            static fn (mixed $path): bool => is_string($path) && $path !== '',
        ));

        if ($files === [] || count($files) !== count($items)) {
            Notification::make()
                ->title(__('admin_resources.media_asset.batch_upload_stopped'))
                ->body(__('admin_resources.media_asset.every_uploaded_image_must_have_a_matching_details_card_conti'))
                ->danger()
                ->send();

            return;
        }

        $this->pendingUploads = array_map(
            static fn (string $path, mixed $item): array => ['path' => $path, 'item' => is_array($item) ? $item : []],
            $files,
            $items,
        );
        $this->batchTotal = count($this->pendingUploads);
        $this->uploadedCount = 0;

        $this->processNextChunk();
    }

    public function continueBatch(): void
    {
        if ($this->pendingUploads !== []) {
            $this->processNextChunk();
        }
    }

    private function processNextChunk(): void
    {
        // In tests this would cap the whole PHPUnit process, not one request.
        if (! app()->runningUnitTests()) {
            @set_time_limit(120);
        }

        $disk = Storage::disk((string) config('filesystems.media_disk', 'public'));
        $chunk = array_splice($this->pendingUploads, 0, self::IMAGES_PER_REQUEST);

        try {
            foreach ($chunk as $entry) {
                $this->storeOne($disk, $entry['path'], $entry['item']);
                $this->uploadedCount++;
            }
        } catch (Throwable $exception) {
            MediaUploadFailure::record($exception, 'batch upload');
            $this->pendingUploads = [];
            Notification::make()
                ->title(__('admin_resources.media_asset.batch_upload_stopped'))
                ->body($this->uploadedCount.' of '.$this->batchTotal.' image(s) were uploaded before an error occurred. '.MediaUploadFailure::message($exception))
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        if ($this->pendingUploads !== []) {
            // Next chunk in a fresh request, so no single request runs long.
            $this->js('$wire.continueBatch()');

            return;
        }

        Notification::make()->title($this->uploadedCount.' images uploaded successfully')->success()->send();
        $this->redirect(MediaAssetResource::getUrl('index'));
    }

    /** @param array<string, mixed> $item */
    private function storeOne(Filesystem $disk, string $path, array $item): void
    {
        if (! str_starts_with($path, self::TEMP_DIRECTORY) || str_contains($path, '..')) {
            throw new \RuntimeException('Only images uploaded on this page can be stored.');
        }

        $fullPath = $disk->path($path);

        if (! is_file($fullPath)) {
            throw new \RuntimeException('A temporary image could not be read. Please upload it again.');
        }

        $mimeType = $disk->mimeType($path) ?: null;
        $originalName = (string) ($item['original_name'] ?? basename($path));

        $file = new UploadedFile(
            $fullPath,
            // Named by its real type: a PNG saved as photo.jpg was refused as an
            // "extension does not match its detected file type" upload.
            $this->nameForType($originalName, $mimeType),
            $mimeType,
            null,
            true,
        );

        $this->mediaService->upload([
            'file' => $file,
            'original_name' => $item['original_name'] ?? null,
            'directory' => 'media/image/'.now()->format('Y/m'),
            'title_ar' => $item['title_ar'] ?? null,
            'title_en' => $item['title_en'] ?? null,
            'alt_text_ar' => $item['alt_text_ar'] ?? null,
            'alt_text_en' => $item['alt_text_en'] ?? null,
            'focal_x' => $item['focal_x'] ?? 50,
            'focal_y' => $item['focal_y'] ?? 50,
            'display_fit' => $item['display_fit'] ?? 'cover',
            'require_alt_text' => true,
            'uploaded_by' => (int) auth()->id(),
        ]);

        $disk->delete($path);
    }

    private function nameForType(string $originalName, ?string $mimeType): string
    {
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => null,
        };

        return $extension === null
            ? $originalName
            : pathinfo($originalName, PATHINFO_FILENAME).'.'.$extension;
    }

    /** @return array{key: string, original_name: string, preview_url: string|null}|null */
    private function uploadState(mixed $upload, int $index): ?array
    {
        if ($upload instanceof TemporaryUploadedFile) {
            try {
                $previewUrl = $upload->temporaryUrl();
            } catch (Throwable) {
                $previewUrl = null;
            }

            return [
                'key' => 'file:'.hash('sha256', $upload->getFilename()),
                'original_name' => $upload->getClientOriginalName(),
                'preview_url' => $previewUrl,
            ];
        }

        if ($upload instanceof UploadedFile) {
            return [
                'key' => 'upload:'.$index.':'.$upload->getClientOriginalName(),
                'original_name' => $upload->getClientOriginalName(),
                'preview_url' => null,
            ];
        }

        if (! is_string($upload) || $upload === '') {
            return null;
        }

        $filename = basename($upload);
        $separator = strpos($filename, '--');
        $originalName = $separator === false ? $filename : substr($filename, $separator + 2);
        $uploadKey = $separator === false ? hash('sha256', $upload) : substr($filename, 0, $separator);

        return [
            'key' => 'file:'.$uploadKey,
            'original_name' => $originalName,
            'preview_url' => MediaUrlResolver::resolve($upload, (string) config('filesystems.media_disk', 'public')),
        ];
    }
}
