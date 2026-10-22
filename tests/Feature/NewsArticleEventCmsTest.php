<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Form\FormDefinitionServiceInterface;
use App\Contracts\News\NewsArticleCmsServiceInterface;
use App\Contracts\News\NewsArticleEventCmsServiceInterface;
use App\DTOs\News\NewsArticleCmsDataDTO;
use App\Enums\PublicationStatus;
use App\Filament\Resources\NewsArticleResource\Pages\CreateNewsArticle;
use App\Models\Faculty\Faculty;
use App\Models\News\NewsArticle;
use App\Models\News\NewsArticleEvent;
use App\Models\News\NewsArticleEventSpeaker;
use App\Models\News\NewsCategory;
use App\Models\Person\FacultyMember;
use App\Models\Person\Person;
use App\Models\User\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class NewsArticleEventCmsTest extends TestCase
{
    use RefreshDatabase;

    private NewsArticleCmsServiceInterface $articles;

    private CmsWorkflowServiceInterface $workflow;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->articles = app(NewsArticleCmsServiceInterface::class);
        $this->workflow = app(CmsWorkflowServiceInterface::class);
        $this->editor = User::factory()->create(['role_slug' => 'editor', 'is_locked' => false]);
    }

    public function test_migration_adds_the_events_category(): void
    {
        $category = NewsCategory::query()->with('translations')->where('slug', 'events')->firstOrFail();

        $this->assertSame('event', $category->type);
        $this->assertSame('الفعاليات', $category->translations->firstWhere('locale', 'ar')?->name);
        $this->assertSame('Events', $category->translations->firstWhere('locale', 'en')?->name);
        $this->assertContains('events', $this->articles->editorialTypeOptions());
    }

    public function test_filament_create_publishes_an_event_with_sessions_and_speakers(): void
    {
        $member = $this->facultyMember('Medicine', 'الطب', 'Rania Haddad', 'رانيا حداد');
        $this->actingAs($this->editor, 'web');

        $payload = $this->articlePayload('Digital Health Conference', $this->categoryId('events'));
        $payload['event'] = [
            'kind' => 'conference',
            'requires_registration' => true,
            'show_on_calendar' => true,
            'formId' => 'activity-registration',
            'capacity' => 120,
            'prior_registrations' => 5,
            'language' => 'both',
            'has_certificate' => true,
            'certificate_note_ar' => 'شهادة لكل من يحضر الجلسات كلها',
            'certificate_note_en' => 'Certificate for attending every session',
            'audience' => ['students', 'staff'],
            'location_ar' => 'المدرج الرئيسي',
            'location_en' => 'Main auditorium',
            'map_url' => 'https://maps.example.org/spu',
            'sessions' => [
                ['date' => '2026-11-02', 'starts_at' => '10:00', 'ends_at' => '12:00', 'title_en' => 'Closing day', 'speakers' => []],
                ['date' => '2026-10-24', 'starts_at' => '09:00', 'ends_at' => '11:30', 'title_en' => 'Opening', 'speakers' => [
                    ['source' => 'external', 'name_ar' => 'سامر خليل', 'name_en' => 'Samer Khalil', 'organization_en' => 'Damascus University'],
                ]],
                ['date' => '2026-10-31', 'starts_at' => '09:00', 'speakers' => []],
            ],
            'speakers' => [
                ['source' => 'university', 'profile' => 'faculty_member:'.$member->getKey()],
            ],
        ];

        Livewire::test(CreateNewsArticle::class)
            ->fillForm($payload)
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified(__('admin.news_article.workflow.notifications.published'));

        $article = NewsArticle::query()->where('slug', 'digital-health-conference')->firstOrFail();
        $event = NewsArticleEvent::query()->with(['sessions.speakers', 'speakers', 'translations'])->where('news_article_id', $article->getKey())->firstOrFail();

        $this->assertSame('published', $article->status);
        $this->assertSame('conference', $event->kind);
        $this->assertTrue($event->requires_registration);
        $this->assertTrue($event->show_on_calendar);
        $this->assertSame('activity-registration', $event->form_key);
        $this->assertSame(120, $event->capacity);
        $this->assertSame(5, $event->prior_registrations);
        $this->assertSame(['students', 'staff'], $event->audience);
        // Sessions are stored in date order; the bounds are Damascus times in UTC.
        $this->assertSame(['2026-10-24', '2026-10-31', '2026-11-02'], $event->sessions->map(fn ($session): string => $session->date->format('Y-m-d'))->all());
        $this->assertSame('2026-10-24 06:00:00', $event->starts_at?->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-02 09:00:00', $event->ends_at?->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('Main auditorium', $event->translations->firstWhere('locale', 'en')?->location);

        $opening = $event->sessions->first();
        $this->assertSame('Samer Khalil', $opening?->speakers->first()?->name_en);
        $this->assertCount(1, $event->speakers);
        $this->assertSame((int) $member->getKey(), $event->speakers->first()?->faculty_member_id);
        // A university speaker's details come from the profile, not the event.
        $this->assertNull($event->speakers->first()?->name_en);

        $stored = $this->articles->getStoredData('entity.news-article.'.$article->getKey());
        $this->assertSame('faculty_member:'.$member->getKey(), $stored?->payload['event']['speakers'][0]['profile'] ?? null);
        $this->assertSame('09:00', $stored?->payload['event']['sessions'][0]['starts_at'] ?? null);
        $this->assertContains('used on: Digital Health Conference', app(FormDefinitionServiceInterface::class)->usage('activity-registration', 'en'));
    }

    public function test_publish_explains_what_an_event_is_missing(): void
    {
        $prepared = $this->prepare($this->articlePayload('Incomplete event', $this->categoryId('events')) + ['event' => [
            'requires_registration' => true,
            'sessions' => [['date' => '2026-02-30', 'starts_at' => '10:00', 'ends_at' => '09:00']],
            'speakers' => [['source' => 'external', 'name_ar' => 'متحدث']],
            'map_url' => 'javascript:alert(1)',
        ]]);

        $errors = $this->articles->publishErrors((string) $prepared->targetKey, $prepared->payload);

        $this->assertArrayHasKey('event.sessions.0.date', $errors);
        $this->assertArrayHasKey('event.sessions.0.ends_at', $errors);
        $this->assertArrayHasKey('event.formId', $errors);
        $this->assertArrayHasKey('event.speakers.0.name_en', $errors);
        $this->assertArrayHasKey('event.map_url', $errors);

        $withoutSessions = $this->prepare($this->articlePayload('Event without sessions', $this->categoryId('events')) + ['event' => ['sessions' => []]]);
        $this->assertArrayHasKey('event.sessions', $this->articles->publishErrors((string) $withoutSessions->targetKey, $withoutSessions->payload));
    }

    public function test_society_events_never_take_registration_and_need_a_date_only_on_the_calendar(): void
    {
        $prepared = $this->prepare($this->articlePayload('Blood donation day', $this->categoryId('society-events')) + ['event' => [
            'kind' => 'conference',
            'requires_registration' => true,
            'formId' => 'activity-registration',
            'sessions' => [],
        ]]);

        $this->assertSame('society', $prepared->payload['event']['kind']);
        $this->assertFalse($prepared->payload['event']['requires_registration']);
        $this->assertSame([], $this->articles->publishErrors((string) $prepared->targetKey, $prepared->payload));

        $onCalendar = $prepared->payload;
        $onCalendar['event']['show_on_calendar'] = true;
        $this->assertArrayHasKey('event.sessions', $this->articles->publishErrors((string) $prepared->targetKey, $onCalendar));

        $this->workflow->saveDraft((string) $prepared->targetKey, $prepared->payload, (int) $this->editor->getKey());
        $this->assertTrue($this->workflow->publish((string) $prepared->targetKey, (int) $this->editor->getKey()));
        $event = NewsArticleEvent::query()->where('news_article_id', $prepared->articleId)->firstOrFail();
        $this->assertFalse($event->requires_registration);
        $this->assertNull($event->form_key);
    }

    public function test_moving_an_event_to_another_category_removes_its_event_details(): void
    {
        $prepared = $this->prepare($this->articlePayload('Short lived event', $this->categoryId('events')) + ['event' => [
            'sessions' => [['date' => '2026-12-01', 'starts_at' => '10:00']],
            'speakers' => [['source' => 'external', 'name_ar' => 'متحدث', 'name_en' => 'Speaker']],
        ]]);
        $this->workflow->saveDraft((string) $prepared->targetKey, $prepared->payload, (int) $this->editor->getKey());
        $this->workflow->publish((string) $prepared->targetKey, (int) $this->editor->getKey());
        $this->assertDatabaseHas('news_article_events', ['news_article_id' => $prepared->articleId]);

        $payload = $prepared->payload;
        $payload['news_category_id'] = $this->categoryId('announcements');
        $moved = $this->articles->prepareDraft(new NewsArticleCmsDataDTO($prepared->articleId, $payload), (int) $this->editor->getKey());
        $this->assertNull($moved->payload['event']);
        $this->workflow->saveDraft((string) $moved->targetKey, $moved->payload, (int) $this->editor->getKey());
        $this->workflow->publish((string) $moved->targetKey, (int) $this->editor->getKey());

        $this->assertDatabaseMissing('news_article_events', ['news_article_id' => $prepared->articleId]);
        $this->assertSame(0, NewsArticleEventSpeaker::query()->count());
    }

    public function test_faculty_editors_cannot_write_events(): void
    {
        $facultyEditor = User::factory()->create([
            'role_slug' => 'faculty_editor',
            'faculty_scopes' => ['medicine'],
            'is_locked' => false,
        ]);
        $this->actingAs($facultyEditor, 'web');

        $options = Livewire::test(CreateNewsArticle::class)->instance()->form->getComponent('data.news_category_id');
        $this->assertNotNull($options);
        $this->assertArrayNotHasKey($this->categoryId('events'), $options->getOptions());
        $this->assertArrayNotHasKey($this->categoryId('society-events'), $options->getOptions());
        $this->assertArrayHasKey($this->categoryId('announcements'), $options->getOptions());

        $this->expectException(AuthorizationException::class);
        $this->articles->prepareDraft(
            new NewsArticleCmsDataDTO(null, $this->articlePayload('Faculty event', $this->categoryId('events')) + ['faculty_scope_slug' => 'medicine']),
            (int) $facultyEditor->getKey(),
        );
    }

    public function test_speaker_profiles_are_grouped_leadership_first_then_by_faculty(): void
    {
        $leader = $this->person('president', 'Nadia Sabbagh', 'نادية صباغ');
        $linkedLeader = $this->person('vice-president', 'Omar Fares', 'عمر فارس');
        $pharmacist = $this->facultyMember('Pharmacy', 'الصيدلة', 'Lina Aziz', 'لينا عزيز');
        $linkedMember = $this->facultyMember('Medicine', 'الطب', 'Omar Fares', 'عمر فارس');
        $linkedMember->forceFill(['person_id' => $linkedLeader->getKey()])->save();
        $hidden = $this->facultyMember('Medicine', 'الطب', 'Hidden Member', 'عضو مخفي');
        $hidden->forceFill(['is_enabled' => false])->save();

        $events = app(NewsArticleEventCmsServiceInterface::class);
        $options = $events->speakerProfileOptions('en');

        $this->assertSame('University leadership', array_key_first($options));
        $this->assertSame(['person:'.$leader->getKey() => 'Nadia Sabbagh — نادية صباغ'], $options['University leadership']);
        // The linked pair is listed once, as the faculty member.
        $this->assertArrayHasKey('faculty_member:'.$linkedMember->getKey(), $options['Medicine']);
        $this->assertArrayHasKey('faculty_member:'.$pharmacist->getKey(), $options['Pharmacy']);
        $this->assertArrayNotHasKey('faculty_member:'.$hidden->getKey(), $options['Medicine']);

        // A profile hidden after it was picked stays listed so the editor sees it.
        $withHidden = $events->speakerProfileOptions('ar', 'faculty_member:'.$hidden->getKey());
        $this->assertSame(['faculty_member:'.$hidden->getKey() => 'عضو مخفي — Hidden Member'], $withHidden['ملفات مخفية']);
    }

    private function prepare(array $payload): NewsArticleCmsDataDTO
    {
        return $this->articles->prepareDraft(new NewsArticleCmsDataDTO(null, $payload), (int) $this->editor->getKey());
    }

    private function categoryId(string $slug): int
    {
        if ($slug === 'announcements' && ! NewsCategory::query()->where('slug', $slug)->exists()) {
            NewsCategory::query()->create(['slug' => 'announcements', 'type' => 'announcement', 'is_enabled' => true]);
        }

        return (int) NewsCategory::query()->where('slug', $slug)->value('id');
    }

    /** @return array<string, mixed> */
    private function articlePayload(string $title, int $categoryId): array
    {
        return [
            'news_category_id' => $categoryId,
            'is_enabled' => true,
            'is_featured' => false,
            'translations' => [
                ['locale' => 'ar', 'title' => 'فعالية عربية', 'excerpt' => 'ملخص', 'body' => '<p>محتوى عربي</p>'],
                ['locale' => 'en', 'title' => $title, 'excerpt' => 'Summary', 'body' => '<p>English body</p>'],
            ],
            'attachments' => [],
            'seoMeta' => [],
        ];
    }

    private function facultyMember(string $facultyEn, string $facultyAr, string $nameEn, string $nameAr): FacultyMember
    {
        $faculty = Faculty::query()->where('slug', strtolower($facultyEn))->first()
            ?? tap(Faculty::query()->create(['slug' => strtolower($facultyEn), 'is_enabled' => true]), function (Faculty $faculty) use ($facultyEn, $facultyAr): void {
                $faculty->translations()->createMany([
                    ['locale' => 'ar', 'name' => $facultyAr],
                    ['locale' => 'en', 'name' => $facultyEn],
                ]);
            });
        $member = FacultyMember::query()->create([
            'slug' => str($nameEn)->slug()->toString(),
            'faculty_id' => $faculty->getKey(),
            'is_enabled' => true,
            'publication_status' => PublicationStatus::Published->value,
            'published_at' => now()->subDay(),
        ]);
        $member->translations()->createMany([
            ['locale' => 'ar', 'full_name' => $nameAr],
            ['locale' => 'en', 'full_name' => $nameEn],
        ]);

        return $member;
    }

    private function person(string $slug, string $nameEn, string $nameAr): Person
    {
        $person = Person::query()->create([
            'slug' => $slug,
            'category' => 'leadership',
            'is_enabled' => true,
            'publication_status' => PublicationStatus::Published->value,
            'published_at' => now()->subDay(),
        ]);
        $person->translations()->createMany([
            ['locale' => 'ar', 'name' => $nameAr, 'role' => 'قيادة'],
            ['locale' => 'en', 'name' => $nameEn, 'role' => 'Leadership'],
        ]);

        return $person;
    }
}
