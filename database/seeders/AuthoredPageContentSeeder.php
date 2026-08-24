<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\AdmissionsPageServiceInterface;
use App\Contracts\Page\CampusLifePageServiceInterface;
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

    /*
     * E-Services is deliberately absent, and it needs its own migration pass.
     *
     * Two separate mechanisms are at work there:
     *
     *  - The landing (getContent) still falls back to the legacy settings group
     *    "e_services_page", but only until a CmsTargetContent row exists for the
     *    "e_services" key. Publishing that key alone therefore cuts the landing's
     *    fallback and blanks it.
     *  - The detail pages (getDetailPage) read published CMS payload only. Their
     *    former source, the per-slug settings groups named
     *    "e_services_{slug_with_underscores}_page", is no longer read by any code
     *    path, so they 404 until their targets are published.
     *
     * A correct migration publishes "e_services" and all four
     * "e_services.{slug}" targets together, sourcing each from its settings group
     * so nothing is invented. That needs the production settings rows to read
     * from, so it is not attempted blind from here.
     */

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
