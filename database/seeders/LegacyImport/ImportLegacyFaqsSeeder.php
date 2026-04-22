<?php

declare(strict_types=1);

namespace Database\Seeders\LegacyImport;

use Illuminate\Support\Facades\DB;

class ImportLegacyFaqsSeeder extends BaseLegacyImportSeeder
{
    public function run(): void
    {
        $module = 'faqs';
        $batch = $this->batchName($module);
        $rows = $this->legacyRows('jx_faqs');

        if ($rows->isEmpty()) {
            $this->command?->warn('No rows found in jx_faqs.');

            return;
        }

        $this->command?->info("Starting FAQs import: {$rows->count()} rows found.");

        $this->ensureDefaultCategory();
        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $sourceId = $this->normalizedInteger($this->rowValue($row, 'id'));

            if ($this->alreadyImported('jx_faqs', $sourceId, 'faqs')) {
                $skipped++;

                continue;
            }

            $legacyLanguageCode = $this->rowValue($row, 'lang');
            $locale = $this->legacyLocaleFromLanguageCode($legacyLanguageCode);

            if ($locale === null) {
                $this->snapshotLegacyRow(
                    $module,
                    $batch,
                    'jx_faqs',
                    $sourceId,
                    null,
                    'unsupported_locale',
                    null,
                    [
                        'lang' => $legacyLanguageCode,
                        'subject' => $this->rowValue($row, 'subject'),
                        'question' => $this->rowValue($row, 'question'),
                        'answer' => $this->rowValue($row, 'answer'),
                    ],
                );
                $this->reject($module, 'jx_faqs', $sourceId, 'unsupported_locale', 'FAQ locale is not supported for import.', [
                    'lang' => $legacyLanguageCode,
                ]);
                $this->logSkip($module, $batch, 'jx_faqs', $sourceId, 'faqs', 'Skipped FAQ with unsupported locale.', [
                    'lang' => $legacyLanguageCode,
                ]);
                $skipped++;

                continue;
            }

            $questionBody = $this->htmlSanitizer()->sanitize((string) $this->rowValue($row, 'question', ''));
            $questionText = $this->cleanedString($row, 'subject')
                ?? $this->cleanedString(['value' => strip_tags((string) $questionBody)], 'value');

            if ($questionText === null || $questionText === '') {
                $this->reject($module, 'jx_faqs', $sourceId, 'unknown_mapping', 'FAQ has no usable question text.');
                $this->logSkip($module, $batch, 'jx_faqs', $sourceId, 'faqs', 'No question text found.');
                $skipped++;

                continue;
            }

            if (mb_strlen($questionText) > 255) {
                $this->reject($module, 'jx_faqs', $sourceId, 'unknown_mapping', 'FAQ question exceeds target length limit.', [
                    'question_length' => mb_strlen($questionText),
                ]);
                $this->logSkip($module, $batch, 'jx_faqs', $sourceId, 'faqs', 'Question exceeds length limit.');
                $skipped++;

                continue;
            }

            $answerText = $this->htmlSanitizer()->sanitize((string) $this->rowValue($row, 'answer', ''));

            $legacyCatId = $this->normalizedInteger($this->rowValue($row, ['dep_id', 'category_id', 'cat_id', 'service_type']));
            $faqCategoryId = $this->resolveFaqCategory($legacyCatId);

            $sortOrder = $this->normalizedInteger($this->rowValue($row, ['faq_order', 'order', 'sort_order', 'record_order'])) ?? 0;
            $isEnabled = $this->normalizedLegacyVisibility($row, true);
            $isFeatured = $this->normalizedBoolean($this->rowValue($row, ['is_featured', 'featured']), false);

            try {
                $faqId = DB::transaction(function () use ($row, $faqCategoryId, $sortOrder, $isEnabled, $isFeatured, $locale, $questionText, $answerText): int {
                    $faqId = DB::table('faqs')->insertGetId([
                        'faq_category_id' => $faqCategoryId,
                        'sort_order' => $sortOrder,
                        'is_enabled' => $isEnabled,
                        'is_featured' => $isFeatured,
                        'created_at' => $this->dateNormalizer()->normalize($this->rowValue($row, ['post_date', 'created_at', 'date_added', 'reg_date']))?->toDateTimeString() ?? now()->toDateTimeString(),
                        'updated_at' => now(),
                    ]);

                    DB::table('faq_translations')->insert([
                        'faq_id' => $faqId,
                        'locale' => $locale,
                        'question' => $questionText,
                        'answer' => $answerText !== null && $answerText !== ''
                            ? $answerText
                            : ($locale === 'ar' ? 'لم يتم توفير إجابة بعد.' : 'No answer provided yet.'),
                        'keywords' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    return $faqId;
                });

                $this->migrationLogger()->log(
                    $module, $batch, 'jx_faqs', $sourceId, 'faqs', $faqId,
                    'success', 'Imported FAQ.', ['faq_category_id' => $faqCategoryId],
                );
                $imported++;
            } catch (\Throwable $e) {
                $this->reject($module, 'jx_faqs', $sourceId, 'unknown_mapping', $e->getMessage());
                $this->logSkip($module, $batch, 'jx_faqs', $sourceId, 'faqs', 'Insert failed: '.$e->getMessage());
                $skipped++;
            }
        }

        $this->command?->info("FAQs import complete. Imported: {$imported}, Skipped: {$skipped}");
    }

    private function ensureDefaultCategory(): int
    {
        $existing = DB::table('faq_categories')->where('slug', 'general')->first();

        if ($existing !== null) {
            return (int) $existing->id;
        }

        $catId = DB::table('faq_categories')->insertGetId([
            'slug' => 'general',
            'icon' => null,
            'sort_order' => 0,
            'is_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('faq_category_translations')->insert([
            [
                'faq_category_id' => $catId,
                'locale' => 'ar',
                'name' => 'عام',
                'description' => 'أسئلة عامة',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'faq_category_id' => $catId,
                'locale' => 'en',
                'name' => 'General',
                'description' => 'General questions',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        return (int) $catId;
    }

    private function resolveFaqCategory(?int $legacyCatId): ?int
    {
        if ($legacyCatId === null) {
            return DB::table('faq_categories')->where('slug', 'general')->value('id');
        }

        $mapped = $this->targetIdResolver()->resolve('jx_categories', $legacyCatId, 'faq_categories');

        if ($mapped !== null) {
            return $mapped;
        }

        return DB::table('faq_categories')->where('slug', 'general')->value('id');
    }
}
