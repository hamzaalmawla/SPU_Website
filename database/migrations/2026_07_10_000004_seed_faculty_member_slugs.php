<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $members = DB::table('faculty_members')
            ->whereNull('slug')
            ->select('id')
            ->get();

        foreach ($members as $member) {
            $name = DB::table('faculty_member_translations')
                ->where('faculty_member_id', $member->id)
                ->orderByRaw("CASE locale WHEN 'ar' THEN 0 WHEN 'en' THEN 1 ELSE 2 END")
                ->value('full_name');

            $slug = Str::slug(is_string($name) ? $name : '');
            if ($slug === '') {
                $slug = 'faculty-member-'.$member->id;
            }

            $originalSlug = $slug;
            $counter = 1;

            while (DB::table('faculty_members')->where('slug', $slug)->exists()) {
                $slug = $originalSlug.'-'.$counter;
                $counter++;
            }

            DB::table('faculty_members')->where('id', $member->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        // Data backfills are intentionally retained during rollback.
    }
};
