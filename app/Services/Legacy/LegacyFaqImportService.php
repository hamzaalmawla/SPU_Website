<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyContentCleaningServiceInterface;
use App\Contracts\Legacy\LegacyFaqImportServiceInterface;
use App\DTOs\Legacy\LegacyFaqImportResultDTO;
use App\Models\Content\Faq;
use App\Models\Content\FaqCategory;
use App\Models\Content\FaqCategoryTranslation;
use App\Models\Content\FaqTranslation;
use App\Models\Shared\MigrationLog;
use App\Services\Legacy\Concerns\HandlesPrivateReviewPackets;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class LegacyFaqImportService implements LegacyFaqImportServiceInterface
{
    use HandlesPrivateReviewPackets;

    private const APPROVAL_TOKEN = 'legacy-faq-import';

    private const SOURCE_TABLE = 'jx_faqs';

    private const CATEGORY_SLUG = 'legacy-faq-review';

    /** @var list<string> */
    private const REQUIRED_HEADERS = ['source_table', 'source_id', 'locale', 'legacy_lang', 'approval_decision', 'approved_target'];

    /** @var array<string, string> */
    private const CATEGORY_NAMES = ['ar' => 'الأسئلة الشائعة الموروثة (للمراجعة)', 'en' => 'Legacy FAQs (Review)'];

    public function __construct(
        private readonly OldDatabaseConnection $oldDatabase,
        private readonly LegacyContentCleaningServiceInterface $cleaner,
    ) {}

    public function import(?string $input = null, string $disk = 'local', bool $write = false, ?string $approval = null, ?string $batch = null): LegacyFaqImportResultDTO
    {
        if ($write && $approval !== self::APPROVAL_TOKEN) {
            throw new InvalidArgumentException('Importing FAQs requires --approve='.self::APPROVAL_TOKEN.'.');
        }
        $batch = $batch !== null && trim($batch) !== '' ? trim($batch) : 'legacy-faqs-'.now()->format('Ymd_His');
        if ($input === null || trim($input) === '') {
            if ($write) {
                throw new InvalidArgumentException('Importing FAQs requires an approved private candidate CSV input.');
            }

            return new LegacyFaqImportResultDTO(false, $batch, 0, 0, 0, 0, 0, []);
        }

        $input = trim($input);
        [$scanned, $approved, $skipped, $reasons] = $this->approvedRows($input, $disk);
        $checksum = hash('sha256', Storage::disk($disk)->get($input));
        $sourceRows = $approved === [] ? collect() : $this->sourceRows(array_keys($approved));
        $prepared = [];
        $approvedQuestionCounts = [];
        foreach ($approved as $sourceId => $packet) {
            $row = $sourceRows->get($sourceId);
            if (! is_object($row)) {
                $this->skip($reasons, $skipped, 'missing_source');

                continue;
            }
            $locale = $this->locale($row->lang);
            if ($locale !== $packet['locale'] || (int) $row->lang !== $packet['lang']) {
                $this->skip($reasons, $skipped, 'source_locale_mismatch');

                continue;
            }
            if ((int) $row->is_visible !== 1) {
                $this->skip($reasons, $skipped, 'hidden_source');

                continue;
            }
            $question = $this->plainText($this->cleaner, $row->question);
            $answer = $this->plainText($this->cleaner, $row->answer);
            if ($question === null) {
                $this->skip($reasons, $skipped, 'missing_question');

                continue;
            }
            if ($answer === null) {
                $this->skip($reasons, $skipped, 'missing_answer');

                continue;
            }
            if ($this->containsContactPattern($question) || $this->containsContactPattern($answer)) {
                $this->skip($reasons, $skipped, 'content_contact_blocked');

                continue;
            }
            if (mb_strlen($question) > 255) {
                $this->skip($reasons, $skipped, 'question_too_long');

                continue;
            }
            $key = $locale.'|'.$this->normalizedReviewText($question);
            $approvedQuestionCounts[$key] = ($approvedQuestionCounts[$key] ?? 0) + 1;
            $prepared[] = compact('sourceId', 'row', 'locale', 'question', 'answer', 'key');
        }

        $currentQuestions = $this->currentQuestions();
        $eligible = [];
        foreach ($prepared as $item) {
            if (($approvedQuestionCounts[$item['key']] ?? 0) > 1) {
                $this->skip($reasons, $skipped, 'duplicate_approved_question');
            } elseif (isset($currentQuestions[$item['key']])) {
                $this->skip($reasons, $skipped, 'current_question_conflict');
            } elseif ($this->alreadyMapped($item['sourceId'])) {
                $this->skip($reasons, $skipped, 'already_mapped');
            } else {
                $eligible[] = $item;
            }
        }

        $importable = count($eligible);
        $imported = 0;
        $translations = 0;
        if ($write && $eligible !== []) {
            $category = $this->reviewCategory(true);
            if ($category === false) {
                foreach ($eligible as $_item) {
                    $this->skip($reasons, $skipped, 'review_category_conflict');
                }
                $importable = 0;
            } else {
                foreach ($eligible as $item) {
                    $this->writeFaq($item, $category, $batch, $disk, $input, $checksum);
                    $imported++;
                    $translations++;
                }
            }
        } elseif (! $write && $eligible !== [] && $this->reviewCategory(false) === false) {
            foreach ($eligible as $_item) {
                $this->skip($reasons, $skipped, 'review_category_conflict');
            }
            $importable = 0;
        }
        ksort($reasons);

        return new LegacyFaqImportResultDTO($write, $batch, $scanned, $importable, $imported, $translations, $skipped, $reasons);
    }

    /** @return array{0: int, 1: array<int, array{locale: string, lang: int}>, 2: int, 3: array<string, int>} */
    private function approvedRows(string $input, string $disk): array
    {
        if (! Storage::disk($disk)->exists($input)) {
            throw new InvalidArgumentException('FAQ candidate CSV ['.$input.'] does not exist on disk ['.$disk.'].');
        }
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, Storage::disk($disk)->get($input));
        rewind($stream);
        $headers = fgetcsv($stream);
        if (! is_array($headers)) {
            fclose($stream);
            throw new InvalidArgumentException('FAQ candidate CSV is empty.');
        }
        $headers = array_map(static fn (mixed $value): string => trim((string) $value), $headers);
        $headers[0] = ltrim($headers[0] ?? '', "\xEF\xBB\xBF");
        $missing = array_diff(self::REQUIRED_HEADERS, $headers);
        if ($missing !== [] || count($headers) !== count(array_unique($headers))) {
            fclose($stream);
            throw new InvalidArgumentException('Input is not a valid FAQ candidate CSV.'.($missing !== [] ? ' Missing: '.implode(', ', $missing).'.' : ' Duplicate headers are not allowed.'));
        }
        $rows = [];
        $scanned = 0;
        $skipped = 0;
        $reasons = [];
        while (($values = fgetcsv($stream)) !== false) {
            if ($values === [null] || $values === []) {
                continue;
            }
            $scanned++;
            if (count($values) !== count($headers)) {
                $this->skip($reasons, $skipped, 'malformed_packet_row');

                continue;
            }
            $rows[] = array_combine($headers, array_map(static fn (mixed $value): string => trim((string) $value), $values));
        }
        fclose($stream);
        $counts = [];
        foreach ($rows as $row) {
            if (ctype_digit($row['source_id']) && (int) $row['source_id'] > 0) {
                $counts[(int) $row['source_id']] = ($counts[(int) $row['source_id']] ?? 0) + 1;
            }
        }
        $approved = [];
        foreach ($rows as $row) {
            $id = ctype_digit($row['source_id']) ? (int) $row['source_id'] : 0;
            if ($id > 0 && ($counts[$id] ?? 0) > 1) {
                $this->skip($reasons, $skipped, 'duplicate_source_id');
            } elseif (Str::lower($row['approval_decision']) !== 'import') {
                $this->skip($reasons, $skipped, $row['approval_decision'] === '' ? 'blank_approval_decision' : 'approval_decision_not_import');
            } elseif (Str::lower($row['approved_target']) !== 'faqs') {
                $this->skip($reasons, $skipped, $row['approved_target'] === '' ? 'blank_approved_target' : 'approved_target_not_faqs');
            } elseif ($row['source_table'] !== self::SOURCE_TABLE) {
                $this->skip($reasons, $skipped, 'source_table_mismatch');
            } elseif ($id < 1) {
                $this->skip($reasons, $skipped, 'invalid_source_id');
            } elseif (! in_array($row['locale'], ['ar', 'en'], true) || ! ctype_digit($row['legacy_lang'])) {
                $this->skip($reasons, $skipped, 'invalid_locale_mapping');
            } elseif (($row['locale'] === 'ar' ? 1 : 2) !== (int) $row['legacy_lang']) {
                $this->skip($reasons, $skipped, 'invalid_locale_mapping');
            } else {
                $approved[$id] = ['locale' => $row['locale'], 'lang' => (int) $row['legacy_lang']];
            }
        }

        return [$scanned, $approved, $skipped, $reasons];
    }

    /** @param list<int> $ids */
    private function sourceRows(array $ids): Collection
    {
        return $this->oldDatabase->table(self::SOURCE_TABLE)->whereIn('id', $ids)
            ->select(['id', 'lang', 'is_visible', 'question', 'answer', 'faq_order', 'post_date'])
            ->selectRaw("CASE WHEN COALESCE(first_name, '') <> '' OR COALESCE(last_name, '') <> '' THEN 1 ELSE 0 END as has_submitter_name")
            ->selectRaw("CASE WHEN COALESCE(email, '') <> '' THEN 1 ELSE 0 END as has_submitter_email")
            ->selectRaw("CASE WHEN COALESCE(country, '') <> '' THEN 1 ELSE 0 END as has_submitter_country")
            ->selectRaw("CASE WHEN COALESCE(phone, '') <> '' THEN 1 ELSE 0 END as has_submitter_phone")
            ->orderBy('id')->get()->keyBy('id');
    }

    /** @return array<string, true> */
    private function currentQuestions(): array
    {
        $result = [];
        foreach (FaqTranslation::query()->get(['locale', 'question']) as $translation) {
            $normalized = $this->normalizedReviewText($this->plainText($this->cleaner, $translation->question));
            if ($normalized !== null) {
                $result[$translation->locale.'|'.$normalized] = true;
            }
        }

        return $result;
    }

    private function alreadyMapped(int $sourceId): bool
    {
        return MigrationLog::query()->where('source_table', self::SOURCE_TABLE)
            ->where('source_id', $sourceId)->where('status', 'success')->exists();
    }

    private function reviewCategory(bool $create): FaqCategory|false|null
    {
        $category = FaqCategory::withTrashed()->where('slug', self::CATEGORY_SLUG)->first();
        if ($category !== null) {
            if ($category->trashed() || $category->is_enabled || $category->sort_order !== 0 || $category->icon !== null) {
                return false;
            }
            $names = FaqCategoryTranslation::query()->where('faq_category_id', $category->getKey())->pluck('name', 'locale')->all();
            ksort($names);
            $expected = self::CATEGORY_NAMES;
            ksort($expected);

            return $names === $expected ? $category : false;
        }
        if (! $create) {
            return null;
        }

        return DB::transaction(function (): FaqCategory {
            $category = FaqCategory::query()->create(['slug' => self::CATEGORY_SLUG, 'icon' => null, 'sort_order' => 0, 'is_enabled' => false]);
            foreach (self::CATEGORY_NAMES as $locale => $name) {
                FaqCategoryTranslation::query()->create(['faq_category_id' => $category->getKey(), 'locale' => $locale, 'name' => $name, 'description' => null]);
            }

            return $category;
        });
    }

    /** @param array{sourceId: int, row: object, locale: string, question: string, answer: string, key: string} $item */
    private function writeFaq(array $item, FaqCategory $category, string $batch, string $disk, string $input, string $checksum): void
    {
        DB::transaction(function () use ($item, $category, $batch, $disk, $input, $checksum): void {
            $row = $item['row'];
            $faq = Faq::query()->create([
                'faq_category_id' => $category->getKey(), 'sort_order' => is_numeric($row->faq_order) ? max(0, (int) $row->faq_order) : $item['sourceId'],
                'is_enabled' => false, 'is_featured' => false,
            ]);
            FaqTranslation::query()->create([
                'faq_id' => $faq->getKey(), 'locale' => $item['locale'], 'question' => $item['question'], 'answer' => $item['answer'], 'keywords' => null,
            ]);
            MigrationLog::query()->create([
                'module' => 'legacy_faqs', 'batch_name' => $batch, 'source_table' => self::SOURCE_TABLE, 'source_id' => $item['sourceId'],
                'target_table' => 'faqs', 'target_id' => $faq->getKey(), 'status' => 'success',
                'message' => 'Imported approved legacy FAQ as disabled archival review data.',
                'metadata' => [
                    'approval_packet' => ['disk' => $disk, 'path' => $input, 'sha256' => $checksum],
                    'legacy_lang' => (int) $row->lang, 'legacy_visibility' => (int) $row->is_visible,
                    'legacy_post_date' => is_scalar($row->post_date) ? (string) $row->post_date : null,
                    'has_submitter_name' => (bool) $row->has_submitter_name, 'has_submitter_email' => (bool) $row->has_submitter_email,
                    'has_submitter_country' => (bool) $row->has_submitter_country, 'has_submitter_phone' => (bool) $row->has_submitter_phone,
                    'locales' => [$item['locale']], 'enabled_on_import' => false,
                ],
            ]);
        });
    }

    private function locale(mixed $lang): ?string
    {
        return (int) $lang === 1 ? 'ar' : ((int) $lang === 2 ? 'en' : null);
    }

    /** @param array<string, int> $reasons */
    private function skip(array &$reasons, int &$skipped, string $reason): void
    {
        $skipped++;
        $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
    }
}
