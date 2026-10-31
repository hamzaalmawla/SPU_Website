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
            ->where(function ($query): void {
                $query->whereNull('slug')->orWhereRaw("TRIM(slug) = ''");
            })
            ->select('id')
            ->get();

        foreach ($members as $member) {
            $name = DB::table('faculty_member_translations')
                ->where('faculty_member_id', $member->id)
                ->orderByRaw("CASE locale WHEN 'ar' THEN 0 WHEN 'en' THEN 1 ELSE 2 END")
                ->value('full_name');

            $base = Str::slug(is_string($name) ? $name : '');
            $base = $base !== '' ? $base : 'faculty-member-'.$member->id;
            $slug = $base;
            $counter = 1;

            while (DB::table('faculty_members')->where('slug', $slug)->exists()) {
                $slug = $base.'-'.$counter;
                $counter++;
            }

            DB::table('faculty_members')->where('id', $member->id)->update(['slug' => $slug]);
        }

        Schema::table('faculty_members', function (Blueprint $table): void {
            $table->string('slug')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('faculty_members', function (Blueprint $table): void {
            $table->string('slug')->nullable()->change();
        });
    }
};
