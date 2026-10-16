<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Support\MediaPicker;
use App\Models\Media\MediaAsset;
use App\Models\User\User;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The picker used by every editor (homepage faculty logos, stat icons, page
 * images) did not understand a fresh upload until its modal was submitted:
 * the framing tool stayed empty and the title and alt text were only checked
 * by the media service after the modal had closed.
 */
final class MediaPickerUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role_slug' => 'super_admin']), 'web');
    }

    public function test_a_fresh_upload_is_named_framed_and_saved_to_the_library(): void
    {
        $component = Livewire::test(MediaPickerHarness::class)
            ->call('mountFormComponentAction', self::pickerKey(), 'choose_or_upload_media')
            ->assertSet('mountedFormComponentActions', ['choose_or_upload_media'])
            ->set('mountedFormComponentActionsData.0.file', [UploadedFile::fake()->image('Faculty_Logo.png', 400, 400)])
            ->assertSet('mountedFormComponentActionsData.0.title_en', 'Faculty Logo')
            ->assertSet('mountedFormComponentActionsData.0.title_ar', 'Faculty Logo')
            ->assertSet('mountedFormComponentActionsData.0.original_name', 'Faculty_Logo.png')
            ->assertSee(__('admin.media_picker.focal_instructions'))
            ->assertDontSee(__('admin.media_picker.choose_image_first'));

        // Alt text is required as soon as a file is chosen, so the modal stays open.
        $component->callMountedFormComponentAction()
            ->assertHasFormComponentActionErrors(['alt_text_ar']);
        $this->assertSame(0, MediaAsset::query()->count());

        $component
            ->set('mountedFormComponentActionsData.0.alt_text_en', 'Faculty logo')
            ->callMountedFormComponentAction()
            ->assertHasNoFormComponentActionErrors();

        $asset = MediaAsset::query()->sole();
        $this->assertSame('Faculty_Logo.png', $asset->original_name);
        $this->assertSame((int) $asset->getKey(), (int) $component->get('data.logoMediaId'));
        $this->assertIsString($component->get('data.logo'));
    }

    /**
     * The picker sets its own component key; Filament's mountFormComponentAction
     * test helper would prefix it with the form state path.
     */
    private static function pickerKey(): string
    {
        return 'media-picker-'.sha1('logo|Logo|image');
    }
}

final class MediaPickerHarness extends Component implements HasForms
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
        return $form->schema([MediaPicker::lightImage('logo', 'Logo')])->statePath('data');
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
