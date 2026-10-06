<?php

declare(strict_types=1);

namespace App\Filament\Resources\MediaAssetResource\Pages;

use App\Contracts\Media\MediaServiceInterface;
use App\Filament\Components\FocalPointPicker;
use App\Filament\Resources\MediaAssetResource;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

final class BatchUploadMediaAssets extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = MediaAssetResource::class;

    protected static string $view = 'filament.resources.media-asset-resource.pages.batch-upload-media-assets';

    /** @var array<string, mixed> */
    public ?array $data = [];

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
        return 'Batch Upload Images';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('1. Select images')
                    ->description('Drop all images together. Wait until every preview says upload complete, then continue to name and frame them.')
                    ->schema([
                        FileUpload::make('files')
                            ->label('Images')
                            ->multiple()
                            ->reorderable()
                            ->appendFiles()
                            ->panelLayout('grid')
                            ->imagePreviewHeight('180')
                            ->image()
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
                                ->label('Continue to name and frame images')
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
                Section::make('2. Name and frame every image')
                    ->description('Names and alternative text are required in Arabic and English so every image is ready for any public page.')
                    ->schema([
                        Repeater::make('items')
                            ->label('Image details')
                            ->schema([
                                Hidden::make('upload_key')->required(),
                                Hidden::make('preview_url'),
                                Hidden::make('original_name')->required(),
                                Grid::make(2)->schema([
                                    TextInput::make('title_ar')->label('Name (AR)')->required()->maxLength(255),
                                    TextInput::make('title_en')->label('Name (EN)')->required()->maxLength(255),
                                    TextInput::make('alt_text_ar')->label('Alternative text (AR)')->required()->maxLength(500),
                                    TextInput::make('alt_text_en')->label('Alternative text (EN)')->required()->maxLength(500),
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
            Notification::make()->title('Upload at least one image first')->warning()->send();

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
                ->title('Some images are still uploading')
                ->body('Wait until every image says upload complete, then continue again.')
                ->warning()
                ->send();

            return;
        }

        $this->data['items'] = $items;
    }

    public function upload(): void
    {
        $data = $this->form->getState();
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        $files = array_values(array_filter(
            is_array($data['files'] ?? null) ? $data['files'] : [],
            static fn (mixed $path): bool => is_string($path) && $path !== '',
        ));
        $disk = Storage::disk((string) config('filesystems.media_disk', 'public'));
        $uploaded = 0;

        try {
            if (count($files) !== count($items)) {
                throw new \RuntimeException('Every uploaded image must have a matching details card.');
            }

            foreach ($files as $index => $path) {
                $item = $items[$index] ?? null;
                if (! is_array($item)) {
                    throw new \RuntimeException('Image details are missing.');
                }

                $fullPath = $disk->path($path);
                if (! is_file($fullPath)) {
                    throw new \RuntimeException('A temporary image could not be read. Please upload it again.');
                }

                $file = new UploadedFile(
                    $fullPath,
                    (string) ($item['original_name'] ?? basename($path)),
                    $disk->mimeType($path) ?: null,
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
                $uploaded++;
            }
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()
                ->title('Batch upload stopped')
                ->body($uploaded.' image(s) were uploaded before an error occurred. Review the remaining files and try again.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()->title($uploaded.' images uploaded successfully')->success()->send();
        $this->redirect(MediaAssetResource::getUrl('index'));
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
