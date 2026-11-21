<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The university's own terms (2026-11): "أعضاء الهيئة التدريسية" (teaching staff)
 * instead of "أعضاء الهيئة الأكاديمية", and "Teaching Staff Directory" in the
 * English menu. Only these exact words change, in stored page titles, card titles,
 * menu labels and the faculty CMS pages (published and drafts); nothing else is
 * touched. Data migration: deploy with data-migrations.approved (Docs/DEPLOYING.md).
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const PHRASES = [
        'الهيئة الأكاديمية' => 'الهيئة التدريسية',
        'هيئة أكاديمية' => 'هيئة تدريسية',
        'Academic Staff Directory' => 'Teaching Staff Directory',
    ];

    public function up(): void
    {
        $this->replace(self::PHRASES);
    }

    public function down(): void
    {
        $this->replace(array_flip(self::PHRASES));
    }

    /** @param array<string, string> $phrases */
    private function replace(array $phrases): void
    {
        foreach ($phrases as $from => $to) {
            foreach ([['faculty_page_translations', 'title'], ['faculty_page_translations', 'summary'], ['faculty_subpage_cards', 'title_override_ar'], ['faculty_subpage_cards', 'title_override_en'], ['menu_items', 'label']] as [$table, $column]) {
                DB::update("update {$table} set {$column} = replace({$column}, ?, ?) where {$column} like ?", [$from, $to, '%'.$from.'%']);
            }

            // CMS payloads hold the words as written or JSON-escaped (ا...).
            foreach ([[$from, $to], [$this->escaped($from), $this->escaped($to)]] as [$old, $new]) {
                foreach (['cms_target_contents', 'cms_drafts'] as $table) {
                    DB::update("update {$table} set payload_json = replace(payload_json, ?, ?) where target_key like ?", [$old, $new, 'facilities.%']);
                }
            }
        }
    }

    private function escaped(string $text): string
    {
        return trim((string) json_encode($text), '"');
    }
};
