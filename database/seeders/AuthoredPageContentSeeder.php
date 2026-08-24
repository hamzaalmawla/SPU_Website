<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\AdmissionsPageServiceInterface;
use App\Contracts\Page\CampusLifePageServiceInterface;
use App\Contracts\Page\EServicesPageServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use App\Models\User\User;
use Illuminate\Database\Seeder;
use Throwable;

/**
 * Publishes the Admissions and Campus Life content that has always lived in the
 * page services into the CMS, so the public site keeps rendering it after the
 * fixture/fallback removal.
 *
 * Why this exists
 * ---------------
 * The production-readiness work stopped public pages falling back to non-CMS
 * content. For Research that was right: its fallback was invented placeholder
 * material (fabricated centres, projects and publications) that should never
 * have been public.
 *
 * Admissions and Campus Life are a different case. Their payloads are
 * real, reviewed, bilingual SPU content — deliberately conservative copy that
 * points applicants at the Admissions directorate rather than inventing
 * requirements, and sections describing facilities the university actually has
 * (the university hospital, the dental clinics, the transport network). Removing
 * the fallback without moving that content anywhere took ~30 navigation links to
 * 404 and left legacy redirects pointing at dead pages.
 *
 * So the content is migrated rather than deleted: each service already exposes it
 * through getEditablePayload(), which is exactly the payload shape the CMS
 * stores. Publishing it makes the pages database-backed and editor-maintainable,
 * which is what the remediation wanted, without losing a word of it.
 *
 * Safe to re-run: a target that already has published content is skipped, so
 * this can never overwrite something an editor has since changed.
 */
class AuthoredPageContentSeeder extends Seeder
{
    /** Targets whose content must not be seeded from code. */
    private const SKIP = [
        // Virtual tour is a standalone page with its own media handling.
        'campus_life.virtual_tour',
    ];

    /**
     * E-Services detail slugs, and the legacy settings group each one came from.
     *
     * Two mechanisms are in play, which is why this group has to move as a unit:
     *
     *  - The landing (getContent) falls back to the "e_services_page" settings
     *    group, but only while no CmsTargetContent row exists for "e_services".
     *    Publishing that key alone cuts the fallback and blanks the landing.
     *  - The detail pages (getDetailPage) read published CMS payload only. Their
     *    former source, detailSettingsGroup(), was removed, so they 404 until
     *    their targets are published.
     *
     * So either the landing and all four details migrate together, or none of
     * them do. Content is read from the settings rows themselves, so nothing is
     * invented; if the landing has no bilingual settings content the whole group
     * is skipped rather than risk blanking a working page.
     *
     * Only these three have a legacy settings group; Setting::GROUP_KEYS is the
     * authority and calling getGroup() with anything else throws.
     * "suggestions-complaints" is deliberately absent: it has never been settings
     * backed and exposes its own getSuggestionsComplaintsEditablePayload().
     *
     * @var array<string, string>
     */
    private const E_SERVICES_SETTINGS_GROUPS = [
        'e_services' => 'e_services_page',
        'e_services.library' => 'e_services_library_page',
        'e_services.staff-email' => 'e_services_staff_email_page',
        'e_services.it-support' => 'e_services_it_support_page',
    ];

    public function run(): void
    {
        $actorId = $this->resolveActorId();

        if ($actorId === null) {
            $this->command?->warn('No user with publish-content permission; nothing seeded.');

            return;
        }

        $published = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($this->targets() as $targetKey => $resolver) {
            if (in_array($targetKey, self::SKIP, true)) {
                $skipped++;

                continue;
            }

            try {
                $workflow = app(CmsWorkflowServiceInterface::class);

                if ($this->alreadyPublished($workflow, $targetKey)) {
                    $skipped++;

                    continue;
                }

                $payload = $resolver($targetKey);

                if (! is_array($payload['translations']['ar'] ?? null) || ! is_array($payload['translations']['en'] ?? null)) {
                    $this->command?->warn(sprintf('  %s: no bilingual payload, skipped', $targetKey));
                    $skipped++;

                    continue;
                }

                $workflow->saveDraft($targetKey, $payload, $actorId);
                $workflow->publish($targetKey, $actorId);
                $published++;
            } catch (Throwable $e) {
                $failed++;
                $this->command?->warn(sprintf('  %s: %s', $targetKey, $e->getMessage()));
            }
        }

        $eServices = $this->publishEServices($actorId);
        $published += $eServices['published'];
        $skipped += $eServices['skipped'];

        $this->command?->info(sprintf(
            'Authored page content: %d published, %d skipped, %d failed.',
            $published,
            $skipped,
            $failed,
        ));
    }

    /**
     * @return array<string, callable(string): array<string, mixed>>
     */
    private function targets(): array
    {
        $admissions = app(AdmissionsPageServiceInterface::class);
        $campusLife = app(CampusLifePageServiceInterface::class);

        $targets = [
            'admissions.landing' => fn (string $k): array => $admissions->getEditablePayload($k),
        ];

        foreach (['requirements', 'tuition', 'how-to-apply', 'faq', 'calendar', 'documents', 'transfer', 'filling-vacancies', 'graduation-exams'] as $slug) {
            $targets['admissions.'.$slug] = fn (string $k): array => $admissions->getEditablePayload($k);
        }

        $targets['campus_life.landing'] = fn (string $k): array => $campusLife->getEditablePayload($k);

        foreach (['services', 'transport', 'clubs-activities', 'career-development', 'dental', 'hospital', 'health-insurance', 'damascus-research-pub', 'rules-regulations', 'general-rules', 'exam-instructions', 'exam-penalties', 'jobs'] as $slug) {
            $targets['campus_life.'.$slug] = fn (string $k): array => $campusLife->getEditablePayload($k);
        }

        return $targets;
    }

    private function alreadyPublished(CmsWorkflowServiceInterface $workflow, string $targetKey): bool
    {
        $published = $workflow->getPublishedPayload($targetKey);

        return is_array($published['translations']['ar'] ?? null)
            && is_array($published['translations']['en'] ?? null);
    }

    /**
     * Migrate the E-Services landing and its four detail pages together.
     *
     * @return array{published: int, skipped: int}
     */
    private function publishEServices(int $actorId): array
    {
        $workflow = app(CmsWorkflowServiceInterface::class);
        $settings = app(SettingsServiceInterface::class);

        $group = static fn (string $g, string $locale): ?array => collect(
            $settings->getGroup($g, $locale)->values
        )->firstWhere('key', 'content')?->jsonValue;

        $payloads = [];
        $sources = self::E_SERVICES_SETTINGS_GROUPS;

        foreach ($sources as $targetKey => $settingsGroup) {
            $ar = $group($settingsGroup, 'ar');
            $en = $group($settingsGroup, 'en');

            if (is_array($ar) && $ar !== [] && is_array($en) && $en !== []) {
                $payloads[$targetKey] = ['translations' => ['ar' => $ar, 'en' => $en]];
            }
        }

        // The landing is the gate: without it, publishing anything here would cut
        // the settings fallback and leave the section worse than before.
        if (! isset($payloads['e_services'])) {
            $this->command?->warn('  e_services: no bilingual settings content for the landing; group skipped.');

            return ['published' => 0, 'skipped' => count($sources)];
        }

        // Suggestions & complaints keeps its own payload source rather than a
        // settings group, so it is added here once the landing has cleared.
        $suggestions = app(EServicesPageServiceInterface::class)->getSuggestionsComplaintsEditablePayload();

        if (is_array($suggestions['translations']['ar'] ?? null) && is_array($suggestions['translations']['en'] ?? null)) {
            $payloads['e_services.suggestions-complaints'] = $suggestions;
        }

        $published = 0;
        $skipped = 0;

        foreach ($payloads as $targetKey => $payload) {
            if ($this->alreadyPublished($workflow, $targetKey)) {
                $skipped++;

                continue;
            }

            try {
                $workflow->saveDraft($targetKey, $payload, $actorId);
                $workflow->publish($targetKey, $actorId);
                $published++;
            } catch (Throwable $e) {
                $this->command?->warn(sprintf('  %s: %s', $targetKey, $e->getMessage()));
            }
        }

        return ['published' => $published, 'skipped' => $skipped + (count($sources) - count($payloads))];
    }

    /**
     * Publishing runs through the CMS workflow, which requires an actor holding
     * publish-content. That gate grants to the editor role, not super_admin.
     */
    private function resolveActorId(): ?int
    {
        $user = User::query()
            ->whereIn('role_slug', ['editor'])
            ->where('is_locked', false)
            ->orderBy('id')
            ->first();

        return $user instanceof User ? (int) $user->getKey() : null;
    }
}
