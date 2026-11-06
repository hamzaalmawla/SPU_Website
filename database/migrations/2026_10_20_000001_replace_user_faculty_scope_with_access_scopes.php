<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A limited editor can now be given several faculties and individual pages,
 * so the single users.faculty_scope_slug becomes two lists. Existing values
 * are carried over as the user's one faculty, written as the faculty's public
 * slug (the value every content table compares against).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('faculty_scopes')->nullable()->after('locked_at');
            $table->json('page_scopes')->nullable()->after('faculty_scopes');
        });

        if (Schema::hasColumn('users', 'faculty_scope_slug')) {
            DB::table('users')
                ->whereNotNull('faculty_scope_slug')
                ->where('faculty_scope_slug', '!=', '')
                ->orderBy('id')
                ->get(['id', 'faculty_scope_slug'])
                ->each(function (object $user): void {
                    DB::table('users')->where('id', $user->id)->update([
                        'faculty_scopes' => json_encode([$this->publicSlug((string) $user->faculty_scope_slug)]),
                    ]);
                });

            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex(['faculty_scope_slug']);
            });

            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('faculty_scope_slug');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('faculty_scope_slug')->nullable()->after('locked_at')->index();
        });

        DB::table('users')
            ->whereNotNull('faculty_scopes')
            ->orderBy('id')
            ->get(['id', 'faculty_scopes'])
            ->each(function (object $user): void {
                $scopes = json_decode((string) $user->faculty_scopes, true);
                $first = is_array($scopes) ? reset($scopes) : false;

                if (is_string($first) && $first !== '') {
                    DB::table('users')->where('id', $user->id)->update(['faculty_scope_slug' => $first]);
                }
            });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['faculty_scopes', 'page_scopes']);
        });
    }

    private function publicSlug(string $scope): string
    {
        $scope = trim($scope);

        if (Schema::hasTable('faculties') && Schema::hasColumn('faculties', 'public_slug')) {
            $publicSlug = DB::table('faculties')
                ->where('public_slug', $scope)
                ->orWhere('slug', $scope)
                ->orWhere('faculty_scope_slug', $scope)
                ->value('public_slug');

            if (is_string($publicSlug) && $publicSlug !== '') {
                return $publicSlug;
            }
        }

        return match ($scope) {
            'ai', 'ai-engineering' => 'artificial-intelligence',
            'construction' => 'building-construction-engineering',
            'business' => 'business-administration',
            default => $scope,
        };
    }
};
