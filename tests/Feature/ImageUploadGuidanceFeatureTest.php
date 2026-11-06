<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\MediaAssetResource;
use App\Filament\Resources\MediaAssetResource\Pages\BatchUploadMediaAssets;
use App\Filament\Support\MediaPicker;
use App\Models\User\User;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

final class ImageUploadGuidanceFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role_slug' => 'super_admin']), 'web');
    }

    public function test_media_picker_displays_compact_dimensions_in_form_placeholder(): void
    {
        Livewire::test(ImageGuidanceHeroHarness::class)
            ->assertSee('spu-image-hint-pill', escape: false)
            ->assertSee('1920 × 1080 px')
            ->assertSee('16:9');
    }

    public function test_media_picker_icon_displays_square_dimensions_in_form_placeholder(): void
    {
        Livewire::test(ImageGuidanceIconHarness::class)
            ->assertSee('spu-image-hint-pill', escape: false)
            ->assertSee('512 × 512 px')
            ->assertSee('1:1');
    }

    public function test_media_picker_upload_modal_includes_image_guidance_card(): void
    {
        $harness = Livewire::test(ImageGuidanceHeroHarness::class)
            ->call('mountFormComponentAction', 'media-picker-'.sha1('data.hero_image_selection|Hero Image|image'), 'choose_or_upload_media')
            ->assertSet('mountedFormComponentActions', ['choose_or_upload_media'])
            ->assertSee('spu-image-guide', escape: false)
            ->assertSee('1920 × 1080 px')
            ->assertSee(__('admin.image_guidance.recommended_size'))
            ->assertSee(__('admin.image_guidance.aspect_ratio'))
            ->assertSee(__('admin.image_guidance.pro_tip'));
    }

    public function test_media_asset_create_form_includes_library_specifications_guide(): void
    {
        Livewire::test(MediaAssetResource\Pages\CreateMediaAsset::class)
            ->assertSee('spu-image-guide', escape: false)
            ->assertSee(__('admin.image_guidance.library_title'))
            ->assertSee('1920 × 1080 px');
    }

    public function test_batch_upload_page_includes_batch_upload_guidance(): void
    {
        Livewire::test(BatchUploadMediaAssets::class)
            ->assertSee('spu-image-guide', escape: false)
            ->assertSee(__('admin.image_guidance.batch_title'));
    }
}

final class ImageGuidanceHeroHarness extends Component implements HasForms
{
    use InteractsWithForms;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form->schema([MediaPicker::image('hero_image', 'Hero Image')])->statePath('data');
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                {{ $this->form }}
                <x-filament-actions::modals />
            </div>
        BLADE;
    }
}

final class ImageGuidanceIconHarness extends Component implements HasForms
{
    use InteractsWithForms;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form->schema([MediaPicker::icon('logo', 'Logo')])->statePath('data');
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                {{ $this->form }}
                <x-filament-actions::modals />
            </div>
        BLADE;
    }
}
