<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roles become editable sets of permissions (`<component>.<action>` keys
 * from App\Services\Auth\PermissionCatalog). A user holds one or more roles
 * plus optional extra permissions.
 *
 * The four existing roles are filled with exactly the access they had when
 * access was hard-coded per role, so nobody gains or loses anything here.
 * The lists below are a snapshot on purpose: later catalog changes must not
 * rewrite what this migration granted.
 */
return new class extends Migration
{
    private const SCREEN = ['view', 'update', 'publish'];

    private const FACULTY_COMPONENTS = [
        'faculty_medicine',
        'faculty_dentistry',
        'faculty_pharmacy',
        'faculty_artificial_intelligence',
        'faculty_building_construction_engineering',
        'faculty_petroleum',
        'faculty_business_administration',
    ];

    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('permission', 150);
            $table->timestamps();
            $table->unique(['role_id', 'permission']);
        });

        Schema::create('user_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('permission', 150);
            $table->timestamps();
            $table->unique(['user_id', 'permission']);
        });

        Schema::create('role_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'role_id']);
        });

        $now = now();

        foreach (['super_admin' => 'Super Admin', 'editor' => 'Editor', 'faculty_editor' => 'Faculty Editor', 'hr' => 'Human Resources'] as $slug => $name) {
            if (! DB::table('roles')->where('slug', $slug)->exists()) {
                DB::table('roles')->insert(['name' => $name, 'slug' => $slug, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        foreach ($this->defaultPermissions() as $slug => $permissions) {
            $roleId = DB::table('roles')->where('slug', $slug)->value('id');

            DB::table('role_permissions')->insert(array_map(
                static fn (string $permission): array => ['role_id' => $roleId, 'permission' => $permission, 'created_at' => $now, 'updated_at' => $now],
                $permissions,
            ));
        }

        $roleIds = DB::table('roles')->pluck('id', 'slug');

        DB::table('users')->orderBy('id')->get(['id', 'role_id', 'role_slug', 'page_scopes'])->each(function (object $user) use ($roleIds, $now): void {
            $roleId = $user->role_id ?? ($roleIds[(string) $user->role_slug] ?? null);

            if ($roleId !== null) {
                DB::table('role_user')->insert(['user_id' => $user->id, 'role_id' => $roleId, 'created_at' => $now, 'updated_at' => $now]);
            }

            // A page grant used to open its admin screen by itself; now the
            // screen needs its own permission, so the user keeps it as an extra.
            $pages = json_decode((string) $user->page_scopes, true);

            if (! is_array($pages)) {
                return;
            }

            $permissions = [];

            foreach ($pages as $page) {
                $component = is_string($page) ? $this->componentForPage($page) : null;

                if ($component !== null) {
                    $permissions[$component.'.view'] = true;
                    $permissions[$component.'.update'] = true;
                }
            }

            DB::table('user_permissions')->insert(array_map(
                static fn (string $permission): array => ['user_id' => $user->id, 'permission' => $permission, 'created_at' => $now, 'updated_at' => $now],
                array_keys($permissions),
            ));
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('role_permissions');
    }

    /** @return array<string, list<string>> */
    private function defaultPermissions(): array
    {
        $facultyScreens = [];

        foreach (self::FACULTY_COMPONENTS as $component) {
            $facultyScreens[] = $component.'.view';
            $facultyScreens[] = $component.'.update';
        }

        $editor = [];

        foreach ([
            'homepage' => self::SCREEN, 'about' => self::SCREEN, 'admissions' => self::SCREEN, 'campus_life' => self::SCREEN,
            'contact_page' => self::SCREEN, 'e_services' => self::SCREEN, 'news_pages' => self::SCREEN, 'events' => self::SCREEN,
            'research_pages' => self::SCREEN, 'faculties_hub' => self::SCREEN, 'pages' => ['view', 'create', 'update', 'delete', 'publish'],
            ...array_fill_keys(self::FACULTY_COMPONENTS, self::SCREEN),
            'faculty_members' => ['view', 'create', 'update', 'delete', 'publish'],
            'faculty_subpage_cards' => ['view', 'create', 'update', 'delete', 'publish'],
            'news_articles' => ['view', 'create', 'update', 'delete', 'publish'],
            'news_categories' => ['view', 'create', 'update', 'delete'],
            'media' => ['view', 'create', 'update', 'delete'],
            'about_pages' => ['view', 'create', 'update', 'delete'],
            'about_cards' => ['view', 'create', 'update', 'delete'],
            'directorates' => ['view', 'create', 'update', 'delete', 'publish'],
            'partnerships' => ['view', 'create', 'update', 'delete', 'publish'],
            'people' => ['view', 'create', 'update', 'delete', 'publish'],
            'achievements' => ['view', 'create', 'update', 'delete'],
            'achievement_categories' => ['view', 'create', 'update', 'delete'],
            'research_publications' => ['view', 'create', 'update', 'delete', 'publish'],
            'researchers' => ['view', 'create', 'update', 'delete'],
            'forms' => ['view', 'create', 'update', 'delete'],
            'form_submissions' => ['view', 'update'],
            'job_applications' => ['view', 'update'],
            'contact_messages' => ['view', 'update', 'delete'],
            'job_board' => self::SCREEN,
            'menu' => ['view', 'update'],
            'settings' => ['view', 'update'],
        ] as $component => $actions) {
            foreach ($actions as $action) {
                $editor[] = $component.'.'.$action;
            }
        }

        return [
            'editor' => $editor,
            'faculty_editor' => [
                ...$facultyScreens,
                'faculty_members.view', 'faculty_members.create', 'faculty_members.update', 'faculty_members.delete',
                'faculty_subpage_cards.view', 'faculty_subpage_cards.create', 'faculty_subpage_cards.update', 'faculty_subpage_cards.delete',
                'news_articles.view', 'news_articles.create', 'news_articles.update', 'news_articles.delete',
                'news_categories.view',
                'media.view', 'media.create', 'media.update',
                'pages.view', 'pages.update', 'pages.delete',
                'research_publications.view', 'research_publications.create', 'research_publications.update', 'research_publications.delete',
                'researchers.view', 'researchers.create', 'researchers.update', 'researchers.delete',
            ],
            'hr' => ['job_board.view', 'job_board.update', 'job_board.publish', 'job_applications.view', 'job_applications.update'],
        ];
    }

    private function componentForPage(string $page): ?string
    {
        return match (true) {
            $page === 'facilities.landing' => 'faculties_hub',
            $page === 'news.events' => 'events',
            $page === 'contact' => 'contact_page',
            default => [
                'homepage' => 'homepage',
                'about' => 'about',
                'admissions' => 'admissions',
                'campus_life' => 'campus_life',
                'e_services' => 'e_services',
                'news' => 'news_pages',
                'research' => 'research_pages',
            ][explode('.', $page)[0]] ?? null,
        };
    }
};
