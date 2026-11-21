<?php

declare(strict_types=1);

namespace Database\Seeders\LegacyImport;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportLegacyComplaintsSeeder extends BaseLegacyImportSeeder
{
    public function run(): void
    {
        $module = 'complaints';

        if (! $this->shouldRunModule($module)) {
            return;
        }

        $batch = $this->batchName($module);

        $this->importComplaintCategories($module, $batch);
        $this->importComplaints($module, $batch);
    }

    private function importComplaintCategories(string $module, string $batch): void
    {
        $rows = $this->legacyRows('jx_complaint_cats');

        if ($rows->isEmpty()) {
            $this->command?->warn('No rows found in jx_complaint_cats.');

            return;
        }

        $this->command?->info("Starting complaint categories import: {$rows->count()} rows found.");

        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $sourceId = $this->normalizedInteger($this->rowValue($row, 'id'));

            if ($this->alreadyImported('jx_complaint_cats', $sourceId, 'complaint_categories')) {
                $skipped++;

                continue;
            }

            $nameAr = $this->cleanedString($row, ['ar_name', 'name_ar', 'name']);
            $nameEn = $this->cleanedString($row, ['en_name', 'name_en', 'name']);

            if (($nameAr === null || $nameAr === '') && ($nameEn === null || $nameEn === '')) {
                $this->reject($module, 'jx_complaint_cats', $sourceId, 'unknown_mapping', 'Complaint category has no name.');
                $this->logSkip($module, $batch, 'jx_complaint_cats', $sourceId, 'complaint_categories', 'No name.');
                $skipped++;

                continue;
            }

            $slugSource = $nameEn ?? $nameAr ?? 'complaint-cat-'.$sourceId;
            $slug = Str::slug($slugSource);

            if ($slug === '') {
                $slug = 'complaint-cat-'.($sourceId ?? Str::random(6));
            }

            if (DB::table('complaint_categories')->where('slug', $slug)->exists()) {
                $slug = $slug.'-'.$sourceId;
            }

            $sortOrder = $this->normalizedInteger($this->rowValue($row, ['order', 'sort_order', 'record_order'])) ?? ($sourceId ?? 0);
            $isEnabled = $this->normalizedLegacyVisibility($row, true);

            $assignedEmail = $this->cleanedString($row, ['email', 'assigned_email', 'admin_email']);
            $assignedToUserId = null;

            if ($assignedEmail !== null && $assignedEmail !== '') {
                $validatedEmail = $this->emailValidator()->normalize($assignedEmail);

                if ($validatedEmail !== null) {
                    $assignedToUserId = DB::table('users')->where('email', $validatedEmail)->value('id');
                }
            }

            try {
                $catId = DB::table('complaint_categories')->insertGetId([
                    'slug' => $slug,
                    'assigned_to_user_id' => $assignedToUserId,
                    'sort_order' => $sortOrder,
                    'is_enabled' => $isEnabled,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $translations = [];

                if ($nameAr !== null && $nameAr !== '') {
                    $translations[] = [
                        'complaint_category_id' => $catId,
                        'locale' => 'ar',
                        'name' => $nameAr,
                        'description' => $this->cleanedString($row, ['ar_description', 'description_ar']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($nameEn !== null && $nameEn !== '') {
                    $translations[] = [
                        'complaint_category_id' => $catId,
                        'locale' => 'en',
                        'name' => $nameEn,
                        'description' => $this->cleanedString($row, ['en_description', 'description_en']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($translations !== []) {
                    DB::table('complaint_category_translations')->insert($translations);
                }

                $this->migrationLogger()->log(
                    $module, $batch, 'jx_complaint_cats', $sourceId, 'complaint_categories', $catId,
                    'success', 'Imported complaint category.', ['slug' => $slug],
                );
                $imported++;
            } catch (\Throwable $e) {
                $this->reject($module, 'jx_complaint_cats', $sourceId, 'unknown_mapping', $e->getMessage());
                $this->logSkip($module, $batch, 'jx_complaint_cats', $sourceId, 'complaint_categories', 'Insert failed: '.$e->getMessage());
                $skipped++;
            }
        }

        $this->command?->info("Complaint categories import complete. Imported: {$imported}, Skipped: {$skipped}");
    }

    private function importComplaints(string $module, string $batch): void
    {
        $rows = $this->legacyRows('jx_complaints');

        if ($rows->isEmpty()) {
            $this->command?->warn('No rows found in jx_complaints.');

            return;
        }

        $this->command?->info("Starting complaints import: {$rows->count()} rows found.");

        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $sourceId = $this->normalizedInteger($this->rowValue($row, 'id'));

            if ($this->alreadyImported('jx_complaints', $sourceId, 'complaints')) {
                $skipped++;

                continue;
            }

            $questionText = $this->htmlSanitizer()->sanitize((string) $this->rowValue($row, 'question', ''));
            $subject = $this->cleanedString(['value' => strip_tags((string) $questionText)], 'value');
            $description = $questionText;

            if (($subject === null || $subject === '') && ($description === null || $description === '')) {
                $this->reject($module, 'jx_complaints', $sourceId, 'unknown_mapping', 'Complaint has no subject or description.');
                $this->logSkip($module, $batch, 'jx_complaints', $sourceId, 'complaints', 'No content.');
                $skipped++;

                continue;
            }

            $submitterEmail = $this->cleanedString($row, ['email', 'submitter_email', 'mail']);

            if ($submitterEmail !== null && $submitterEmail !== '') {
                $validatedEmail = $this->emailValidator()->normalize($submitterEmail);
                $submitterEmail = $validatedEmail;
            }

            $legacyCatId = $this->normalizedInteger($this->rowValue($row, ['complaint_cat_id', 'category_id', 'cat_id']));
            $complaintCategoryId = null;
            $assignedToUserId = null;

            if ($legacyCatId !== null) {
                $complaintCategoryId = $this->targetIdResolver()->resolve('jx_complaint_cats', $legacyCatId, 'complaint_categories');

                if ($complaintCategoryId !== null) {
                    $assignedToUserId = DB::table('complaint_categories')
                        ->where('id', $complaintCategoryId)
                        ->value('assigned_to_user_id');
                }
            }

            $ticketNumber = 'LEGACY-'.str_pad((string) ($sourceId ?? rand(1000, 9999)), 6, '0', STR_PAD_LEFT);

            if (DB::table('complaints')->where('ticket_number', $ticketNumber)->exists()) {
                $ticketNumber = $ticketNumber.'-'.Str::random(4);
            }

            $createdAt = $this->dateNormalizer()->normalize($this->rowValue($row, ['post_date', 'created_at', 'date_added', 'reg_date', 'date']));
            $resolution = $this->htmlSanitizer()->sanitize((string) $this->rowValue($row, 'answer', ''));
            $firstName = $this->cleanedString($row, 'first_name');
            $lastName = $this->cleanedString($row, 'last_name');
            $submitterName = trim(implode(' ', array_filter([$firstName, $lastName])));
            $subject = $subject !== null && $subject !== '' ? mb_substr($subject, 0, 255) : null;
            $priority = $this->normalizedBoolean($this->rowValue($row, 'is_main'), false) ? 'high' : 'medium';
            $status = $resolution !== null && $resolution !== '' ? 'resolved' : 'open';

            try {
                $complaintId = DB::table('complaints')->insertGetId([
                    'ticket_number' => $ticketNumber,
                    'complaint_category_id' => $complaintCategoryId,
                    'submitted_by_user_id' => null,
                    'assigned_to_user_id' => $assignedToUserId,
                    'submitter_name' => $submitterName !== '' ? $submitterName : null,
                    'submitter_email' => $submitterEmail,
                    'submitter_phone' => $this->cleanedString($row, ['phone', 'mobile', 'tel']),
                    'subject' => $subject ?? 'شكوى مرحّلة',
                    'description' => $description !== null && $description !== '' ? $description : ($subject ?? 'لا يوجد وصف'),
                    'priority' => $priority,
                    'status' => $status,
                    'resolution' => $resolution !== null && $resolution !== '' ? $resolution : null,
                    'resolved_at' => $resolution !== null && $resolution !== '' ? ($createdAt?->toDateTimeString() ?? now()->toDateTimeString()) : null,
                    'created_at' => $createdAt?->toDateTimeString() ?? now()->toDateTimeString(),
                    'updated_at' => now(),
                ]);

                $this->migrationLogger()->log(
                    $module, $batch, 'jx_complaints', $sourceId, 'complaints', $complaintId,
                    'success', 'Imported complaint.', [
                        'ticket' => $ticketNumber,
                        'category_id' => $complaintCategoryId,
                        'legacy_lang' => $this->rowValue($row, 'lang'),
                        'governorate' => $this->cleanedString($row, 'governorate'),
                        'country' => $this->cleanedString($row, 'country'),
                        'association_id' => $this->normalizedInteger($this->rowValue($row, 'association_id')),
                        'is_new' => $this->normalizedBoolean($this->rowValue($row, 'is_new'), false),
                        'is_main' => $this->normalizedBoolean($this->rowValue($row, 'is_main'), false),
                        'status' => $status,
                    ],
                );
                $imported++;
            } catch (\Throwable $e) {
                $this->reject($module, 'jx_complaints', $sourceId, 'unknown_mapping', $e->getMessage());
                $this->logSkip($module, $batch, 'jx_complaints', $sourceId, 'complaints', 'Insert failed: '.$e->getMessage());
                $skipped++;
            }
        }

        $this->command?->info("Complaints import complete. Imported: {$imported}, Skipped: {$skipped}");
    }
}
