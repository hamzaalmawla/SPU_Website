<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Contracts\Auth\PermissionCatalogInterface;
use App\DTOs\Cms\CmsTargetDTO;

final class PermissionCatalog implements PermissionCatalogInterface
{
    private const SCREEN = ['view', 'update', 'publish'];

    private const RECORDS = ['view', 'create', 'update', 'delete'];

    private const PUBLISHED_RECORDS = ['view', 'create', 'update', 'delete', 'publish'];

    /** Faculty public slugs, in the order of the admin menu. */
    private const FACULTIES = [
        'medicine',
        'dentistry',
        'pharmacy',
        'artificial-intelligence',
        'building-construction-engineering',
        'petroleum',
        'business-administration',
    ];

    /** Fixed CMS page areas and the component that owns them. */
    private const AREA_COMPONENTS = [
        'homepage' => 'homepage',
        'about' => 'about',
        'admissions' => 'admissions',
        'campus_life' => 'campus_life',
        'contact' => 'contact_page',
        'e_services' => 'e_services',
        'news' => 'news_pages',
        'research' => 'research_pages',
    ];

    /** Records edited through the CMS workflow, by target key prefix. */
    private const ENTITY_COMPONENTS = [
        'person' => 'people',
        'faculty-member' => 'faculty_members',
        'directorate' => 'directorates',
        'partnership' => 'partnerships',
        'news-article' => 'news_articles',
        'research-publication' => 'research_publications',
    ];

    public function groups(): array
    {
        $faculties = array_fill_keys($this->facultyComponents(), self::SCREEN);

        return [
            'site_pages' => [
                'homepage' => self::SCREEN,
                'about' => self::SCREEN,
                'admissions' => self::SCREEN,
                'campus_life' => self::SCREEN,
                'contact_page' => self::SCREEN,
                'e_services' => self::SCREEN,
                'news_pages' => self::SCREEN,
                'events' => self::SCREEN,
                'research_pages' => self::SCREEN,
                'faculties_hub' => self::SCREEN,
                'pages' => self::PUBLISHED_RECORDS,
            ],
            'faculties' => [
                ...$faculties,
                'faculty_members' => self::PUBLISHED_RECORDS,
                'faculty_subpage_cards' => self::PUBLISHED_RECORDS,
                // update: rotate or revoke a link.
                'staff_intake_links' => ['view', 'create', 'update'],
                // update: approve or reject what staff sent.
                'staff_intake_requests' => ['view', 'update'],
            ],
            'news_media' => [
                'news_articles' => self::PUBLISHED_RECORDS,
                'news_categories' => self::RECORDS,
                'media' => self::RECORDS,
            ],
            'about_people' => [
                'about_pages' => self::RECORDS,
                'about_cards' => self::RECORDS,
                'directorates' => self::PUBLISHED_RECORDS,
                'partnerships' => self::PUBLISHED_RECORDS,
                'people' => self::PUBLISHED_RECORDS,
                'achievements' => self::RECORDS,
                'achievement_categories' => self::RECORDS,
            ],
            'research' => [
                'research_publications' => self::PUBLISHED_RECORDS,
                'researchers' => self::RECORDS,
            ],
            'forms_inbox' => [
                'forms' => self::RECORDS,
                'form_submissions' => ['view', 'update'],
                'job_applications' => ['view', 'update'],
                'contact_messages' => ['view', 'update', 'delete'],
            ],
            'jobs' => [
                'job_board' => self::SCREEN,
            ],
            'site_settings' => [
                'menu' => ['view', 'update'],
                'settings' => ['view', 'update'],
                'audit_log' => ['view'],
            ],
        ];
    }

    public function all(): array
    {
        $permissions = [];

        foreach ($this->groups() as $components) {
            foreach ($components as $component => $actions) {
                foreach ($actions as $action) {
                    $permissions[] = $component.'.'.$action;
                }
            }
        }

        return $permissions;
    }

    public function exists(string $permission): bool
    {
        return in_array($permission, $this->all(), true);
    }

    public function options(): array
    {
        $options = [];

        foreach ($this->groups() as $group => $components) {
            $groupLabel = __('admin_permissions.groups.'.$group);

            foreach ($components as $component => $actions) {
                foreach ($actions as $action) {
                    $options[$groupLabel][$component.'.'.$action] = $this->label($component.'.'.$action);
                }
            }
        }

        return $options;
    }

    public function label(string $permission): string
    {
        [$component, $action] = array_pad(explode('.', $permission, 2), 2, '');

        return __('admin_permissions.components.'.$component).' › '.__('admin_permissions.actions.'.$action);
    }

    public function componentForTarget(CmsTargetDTO $target): ?string
    {
        $key = $target->key;

        if (preg_match('/^entity\.([a-z-]+)\.\d+$/', $key, $matches) === 1) {
            return self::ENTITY_COMPONENTS[$matches[1]] ?? null;
        }

        if ($key === 'facilities.landing') {
            return 'faculties_hub';
        }

        if ($target->facultyScopeSlug !== null) {
            return $this->componentForFaculty($target->facultyScopeSlug);
        }

        return match (true) {
            $key === 'campus_life.jobs' => 'job_board',
            $key === 'news.events' => 'events',
            $key === 'news.article' => 'news_articles',
            $key === 'research.publication' => 'research_publications',
            default => self::AREA_COMPONENTS[$target->area] ?? null,
        };
    }

    public function facultyComponents(): array
    {
        return array_map(fn (string $faculty): string => $this->componentForFaculty($faculty), self::FACULTIES);
    }

    public function componentForFaculty(string $facultySlug): string
    {
        return 'faculty_'.str_replace('-', '_', $facultySlug);
    }
}
