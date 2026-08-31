<?php

declare(strict_types=1);

namespace App\Observers;

use App\Contracts\Search\SearchIndexServiceInterface;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyPage;
use App\Models\Faculty\FacultyPageTranslation;
use App\Models\Faculty\FacultyTranslation;
use App\Models\News\NewsArticle;
use App\Models\News\NewsArticleTranslation;
use App\Models\Page\Page;
use App\Models\Page\PageTranslation;
use App\Models\Person\FacultyMember;
use App\Models\Person\FacultyMemberTranslation;
use App\Models\Person\Person;
use App\Models\Person\PersonTranslation;
use App\Models\Research\ResearchPublication;
use App\Models\Research\ResearchPublicationTranslation;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Keeps the derived search index in step with content writes.
 *
 * Publishing, unpublishing, editing and deleting all end in a model write, so
 * observing the content models covers every editorial path — the admin panel,
 * the CMS publish workflow and the legacy importers alike — without each of
 * those services having to remember to call the indexer.
 *
 * Two deliberate properties:
 *
 *  - Indexing failures are swallowed. A broken index must never turn a
 *    successful editorial save into a 500; `php artisan search:index` reconciles.
 *  - Seeders run under WithoutModelEvents, so bulk seeding does not pay for
 *    per-row indexing. Seeded environments are indexed by the command.
 *
 * Cascading visibility changes — disabling a whole faculty, which should also
 * hide its subpages and its staff — are handled for Faculty here; any other
 * indirect change is reconciled by the next `search:index` run.
 */
final class SearchIndexObserver
{
    /**
     * Model class => [source key, foreign key holding the source record id].
     * A null foreign key means the model *is* the source record.
     *
     * @var array<class-string<Model>, array{0: string, 1: string|null}>
     */
    private const SOURCE_MAP = [
        NewsArticle::class => ['news', null],
        NewsArticleTranslation::class => ['news', 'news_article_id'],
        ResearchPublication::class => ['research', null],
        ResearchPublicationTranslation::class => ['research', 'research_publication_id'],
        Page::class => ['pages', null],
        PageTranslation::class => ['pages', 'page_id'],
        FacultyMember::class => ['faculty-members', null],
        FacultyMemberTranslation::class => ['faculty-members', 'faculty_member_id'],
        Person::class => ['persons', null],
        PersonTranslation::class => ['persons', 'person_id'],
        Faculty::class => ['faculties', null],
        FacultyTranslation::class => ['faculties', 'faculty_id'],
        FacultyPage::class => ['faculty-pages', null],
        FacultyPageTranslation::class => ['faculty-pages', 'faculty_page_id'],
    ];

    /**
     * Models whose observers should be registered.
     *
     * @return list<class-string<Model>>
     */
    public static function observedModels(): array
    {
        return array_keys(self::SOURCE_MAP);
    }

    public function saved(Model $model): void
    {
        $this->sync($model);
    }

    public function deleted(Model $model): void
    {
        $this->sync($model);
    }

    public function restored(Model $model): void
    {
        $this->sync($model);
    }

    public function forceDeleted(Model $model): void
    {
        $this->sync($model);
    }

    private function sync(Model $model): void
    {
        $mapping = self::SOURCE_MAP[$model::class] ?? null;

        if ($mapping === null) {
            return;
        }

        [$source, $foreignKey] = $mapping;
        $recordId = $foreignKey === null ? $model->getKey() : $model->getAttribute($foreignKey);

        if (! is_numeric($recordId)) {
            return;
        }

        try {
            $indexer = app(SearchIndexServiceInterface::class);

            if (! $indexer->isAvailable()) {
                return;
            }

            $indexer->syncRecord($source, (int) $recordId);

            // Disabling a faculty must also retire its subpages and its staff,
            // neither of which receives a write of its own.
            if ($model instanceof Faculty) {
                $this->syncFacultyDependents($indexer, $model);
            }
        } catch (Throwable) {
            // Search indexing is derived data and never blocks a content write.
        }
    }

    private function syncFacultyDependents(SearchIndexServiceInterface $indexer, Faculty $faculty): void
    {
        foreach ($faculty->pages()->pluck('id') as $pageId) {
            $indexer->syncRecord('faculty-pages', (int) $pageId);
        }

        foreach ($faculty->members()->pluck('id') as $memberId) {
            $indexer->syncRecord('faculty-members', (int) $memberId);
        }
    }
}
