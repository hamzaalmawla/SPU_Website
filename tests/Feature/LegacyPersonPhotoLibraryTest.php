<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Support\MediaPicker;
use App\Models\Career\Alumni;
use App\Models\Career\AlumniTranslation;
use App\Models\Career\HonorStudent;
use App\Models\Career\HonorStudentTranslation;
use App\Models\Faculty\Faculty;
use App\Models\Media\MediaAsset;
use App\Models\Person\FacultyMember;
use App\Models\Person\FacultyMemberTranslation;
use App\Models\User\User;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Imported people keep their photo as a bare legacy file name, which no media
 * library listed, so an editor could not find a student's or member's photo
 * in the picker, least of all by the person's name.
 */
final class LegacyPersonPhotoLibraryTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    private Faculty $faculty;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/legacy-root-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($this->root.'/downloads/files');
        File::ensureDirectoryExists($this->root.'/downloads/files2');
        $this->faculty = Faculty::query()->create(['slug' => 'medicine', 'public_slug' => 'medicine', 'faculty_scope_slug' => 'medicine']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_a_dry_run_reports_without_writing(): void
    {
        $this->photo('downloads/files/1637398791_74758571.jpg');
        $this->honor('ريم أحمد الخطيب', 'Reem Ahmad Al-Khatib', '1637398791_74758571.jpg');

        $this->artisan('legacy-import:person-photos', ['--root' => $this->root])
            ->expectsOutputToContain('Dry run')
            ->expectsOutputToContain('assets to create        : 1')
            ->assertSuccessful();

        $this->assertSame(0, MediaAsset::query()->count());
    }

    public function test_photos_become_legacy_assets_named_after_their_people(): void
    {
        $this->photo('downloads/files/1637398791_74758571.jpg');
        $this->photo('downloads/files2/1532889054_419185082.png', 'png');
        $this->photo('downloads/files/1638092877_6037971992.jpg');

        // One student over two semesters shares a photo.
        $this->honor('ريم أحمد الخطيب', 'Reem Ahmad Al-Khatib', '1637398791_74758571.jpg');
        $this->honor('ريم أحمد الخطيب', 'Reem Ahmad Al-Khatib', '1637398791_74758571.jpg');
        $this->honor('سارة علي', null, 'missing_file.jpg');
        $this->honor('لينا ديب', null, 'cv.pdf');
        $member = FacultyMember::query()->create(['faculty_id' => $this->faculty->getKey(), 'slug' => 'dr-omar', 'legacy_photo_path' => '1532889054_419185082.png']);
        FacultyMemberTranslation::query()->create(['faculty_member_id' => $member->getKey(), 'locale' => 'ar', 'full_name' => 'د. عمر  حسن']);
        FacultyMemberTranslation::query()->create(['faculty_member_id' => $member->getKey(), 'locale' => 'en', 'full_name' => 'Dr. Omar Hasan']);
        // Already prefixed, as production stores it.
        $alumni = Alumni::query()->create(['faculty_id' => $this->faculty->getKey(), 'legacy_photo_path' => 'downloads/files/1638092877_6037971992.jpg']);
        AlumniTranslation::query()->create(['alumni_id' => $alumni->getKey(), 'locale' => 'ar', 'full_name' => 'خالد يوسف']);

        $this->artisan('legacy-import:person-photos', ['--root' => $this->root, '--write' => true])
            ->expectsOutputToContain('assets created          : 3')
            ->expectsOutputToContain('file not found         : 1')
            ->expectsOutputToContain('not an image           : 1')
            ->assertSuccessful();

        $reem = MediaAsset::query()->where('path', '/downloads/files/1637398791_74758571.jpg')->sole();
        $this->assertSame('legacy', $reem->library_scope);
        $this->assertSame('public', $reem->disk);
        $this->assertSame('image/jpeg', $reem->mime_type);
        $this->assertSame('ريم أحمد الخطيب', $reem->title_ar);
        $this->assertSame('Reem Ahmad Al-Khatib', $reem->title_en);
        $this->assertSame('ريم أحمد الخطيب', $reem->alt_text_ar);
        $this->assertSame('medicine', $reem->faculty_scope_slug);
        $this->assertSame('auto_generated', $reem->metadata_status);
        $this->assertNotNull($reem->checksum);
        $this->assertSame(40, $reem->width);

        $omar = MediaAsset::query()->where('path', '/downloads/files2/1532889054_419185082.png')->sole();
        $this->assertSame('د. عمر حسن', $omar->title_ar);
        $this->assertSame('Dr. Omar Hasan', $omar->title_en);

        $khalid = MediaAsset::query()->where('path', '/downloads/files/1638092877_6037971992.jpg')->sole();
        $this->assertSame('خالد يوسف', $khalid->title_ar);
        $this->assertNull($khalid->title_en);

        // Re-running changes nothing.
        $this->artisan('legacy-import:person-photos', ['--root' => $this->root, '--write' => true])
            ->expectsOutputToContain('assets created          : 0')
            ->expectsOutputToContain('already named          : 3')
            ->assertSuccessful();
        $this->assertSame(3, MediaAsset::query()->count());
    }

    public function test_existing_assets_keep_what_editors_set(): void
    {
        $this->photo('downloads/files/1637398791_74758571.jpg');
        $this->photo('downloads/files/1638092877_6037971992.jpg');
        $this->honor('ريم أحمد الخطيب', 'Reem Ahmad Al-Khatib', '1637398791_74758571.jpg');
        $this->honor('خالد يوسف', 'Khaled Youssef', '1638092877_6037971992.jpg');
        $legacy = $this->asset('/downloads/files/1637398791_74758571.jpg', 'legacy', ['title_en' => 'Graduation photo']);
        $main = $this->asset('/downloads/files/1638092877_6037971992.jpg', 'main', ['title_en' => 'Khaled']);

        $this->artisan('legacy-import:person-photos', ['--root' => $this->root, '--write' => true])
            ->expectsOutputToContain('names filled in         : 1')
            ->expectsOutputToContain('already in main library: 1')
            ->assertSuccessful();

        $legacy->refresh();
        $this->assertSame('Graduation photo', $legacy->title_en);
        $this->assertSame('ريم أحمد الخطيب', $legacy->title_ar);
        $this->assertSame('Reem Ahmad Al-Khatib', $legacy->alt_text_en);
        $this->assertSame('Khaled', $main->refresh()->title_en);
        $this->assertNull($main->title_ar);
        $this->assertSame(2, MediaAsset::query()->count());
    }

    public function test_a_missing_legacy_root_fails(): void
    {
        $this->artisan('legacy-import:person-photos', ['--root' => $this->root.'/nope'])
            ->expectsOutputToContain('Legacy document root not found')
            ->assertFailed();
    }

    public function test_an_editor_finds_a_photo_by_the_persons_name_and_attaches_it(): void
    {
        $this->photo('downloads/files/1637398791_74758571.jpg');
        $this->honor('ريم أحمد الخطيب', 'Reem Ahmad Al-Khatib', '1637398791_74758571.jpg');
        $this->artisan('legacy-import:person-photos', ['--root' => $this->root, '--write' => true])->assertSuccessful();
        $legacyId = (int) MediaAsset::query()->sole()->getKey();

        $this->actingAs(User::factory()->create(['role_slug' => 'super_admin']), 'web');
        $component = Livewire::test(LegacyPersonPhotoPickerHarness::class)
            ->call('mountFormComponentAction', 'media-picker-'.sha1('data.photo_selection|Photo|image'), 'choose_or_upload_media');
        $legacyField = 'mountedFormComponentActionsData.0.legacy_media_id';

        foreach (['ريم', 'الخطيب', 'Reem', 'khatib'] as $search) {
            $results = collect($component->instance()->getFormSelectSearchResults($legacyField, $search));
            $this->assertSame([(string) $legacyId], $results->pluck('value')->map(fn (mixed $value): string => (string) $value)->all(), $search);
            $this->assertStringContainsString('1637398791_74758571.jpg', (string) $results->first()['label']);
        }

        $this->assertSame([], $component->instance()->getFormSelectSearchResults($legacyField, 'سارة'));

        // The photo is already named, so promoting it needs no typing.
        $component
            ->set($legacyField, $legacyId)
            ->callMountedFormComponentAction()
            ->assertHasNoFormComponentActionErrors();

        $promoted = MediaAsset::query()->where('library_scope', 'main')->sole();
        $this->assertSame($legacyId, (int) $promoted->promoted_from_media_id);
        $this->assertSame('ريم أحمد الخطيب', $promoted->title_ar);
        $this->assertSame('Reem Ahmad Al-Khatib', $promoted->alt_text_en);
        $this->assertSame((int) $promoted->getKey(), (int) $component->get('data.photoMediaId'));
        $this->assertSame('/downloads/files/1637398791_74758571.jpg', $component->get('data.photo'));
    }

    private function photo(string $relative, string $type = 'jpg'): void
    {
        $image = imagecreatetruecolor(40, 50);
        $path = $this->root.'/'.$relative;
        $type === 'png' ? imagepng($image, $path) : imagejpeg($image, $path);
        imagedestroy($image);
    }

    private function honor(string $nameAr, ?string $nameEn, string $photo): void
    {
        $student = HonorStudent::query()->create(['faculty_id' => $this->faculty->getKey(), 'academic_year' => '2021 / 1', 'legacy_photo_path' => $photo]);

        foreach (['ar' => $nameAr, 'en' => $nameEn] as $locale => $name) {
            if ($name !== null) {
                HonorStudentTranslation::query()->create(['honor_student_id' => $student->getKey(), 'locale' => $locale, 'full_name' => $name]);
            }
        }
    }

    /** @param  array<string, mixed>  $attributes */
    private function asset(string $path, string $scope, array $attributes): MediaAsset
    {
        return MediaAsset::query()->create([
            'disk' => 'public',
            'directory' => 'downloads/files',
            'filename' => basename($path),
            'original_name' => basename($path),
            'mime_type' => 'image/jpeg',
            'media_type' => 'image',
            'library_scope' => $scope,
            'path' => $path,
            'source_path' => $scope === 'legacy' ? $path : null,
            ...$attributes,
        ]);
    }
}

final class LegacyPersonPhotoPickerHarness extends Component implements HasForms
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
        return $form->schema([MediaPicker::image('photo', 'Photo')])->statePath('data');
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
