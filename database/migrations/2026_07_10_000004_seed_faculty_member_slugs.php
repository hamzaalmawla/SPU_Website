<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $members = DB::table('faculty_members')
            ->join('faculty_member_translations', 'faculty_members.id', '=', 'faculty_member_translations.faculty_member_id')
            ->where('faculty_member_translations.locale', 'ar')
            ->select('faculty_members.id', 'faculty_member_translations.full_name')
            ->get();

        foreach ($members as $member) {
            $slug = Str::slug($member->full_name);
            $originalSlug = $slug;
            $counter = 1;

            while (DB::table('faculty_members')->where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            DB::table('faculty_members')->where('id', $member->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        DB::table('faculty_members')->update(['slug' => null]);
    }
};
