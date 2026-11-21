<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $page = DB::table('about_pages')->where('slug', 'partnerships')->first(['id']);

        if ($page === null) {
            return;
        }

        $goals = [
            'ar' => [
                ['icon' => '+', 'title' => 'تبادل الخبرات', 'body' => 'تبادل الخبرات التعليمية والبحثية مع مؤسسات مرموقة.'],
                ['icon' => '◇', 'title' => 'تطوير الكوادر', 'body' => 'الاستفادة من أساتذة ومحاضرين ذوي خبرة في التعليم والبحث.'],
                ['icon' => '✓', 'title' => 'مسارات الدراسات العليا', 'body' => 'دعم الخريجين الساعين إلى مسارات الماجستير والدكتوراه.'],
                ['icon' => '○', 'title' => 'مواءمة مجتمعية', 'body' => 'ربط مخرجات الجامعة باحتياجات المجتمع والتنمية الاقتصادية والاجتماعية.'],
            ],
            'en' => [
                ['icon' => '+', 'title' => 'Experience Exchange', 'body' => 'Exchange teaching and research experience with reputable institutions.'],
                ['icon' => '◇', 'title' => 'Faculty Development', 'body' => 'Benefit from experienced professors and lecturers in teaching and research.'],
                ['icon' => '✓', 'title' => 'Postgraduate Pathways', 'body' => "Support graduates seeking master's and doctoral study pathways."],
                ['icon' => '○', 'title' => 'Community Alignment', 'body' => 'Link university outputs with community, economic, and social development needs.'],
            ],
        ];

        foreach ($goals as $locale => $content) {
            $translation = DB::table('about_page_translations')
                ->where('about_page_id', $page->id)
                ->where('locale', $locale)
                ->first(['id', 'sections_json']);

            if ($translation === null || $this->hasContent($translation->sections_json)) {
                continue;
            }

            DB::table('about_page_translations')
                ->where('id', $translation->id)
                ->update(['sections_json' => json_encode($content, JSON_THROW_ON_ERROR)]);
        }

        $leadershipCopy = [
            'ar' => [
                ['key' => 'rector_quote', 'title' => '', 'body' => 'تتمثل رؤيتنا في بناء بيئة أكاديمية لا تكتفي بالسعي إلى التميز في البحث والتعليم، بل تساهم في التنمية المستدامة للمجتمع وتمكين طلابنا من قيادة المستقبل.'],
                ['key' => 'vice_presidents_title', 'title' => 'نواب رئيس الجامعة', 'body' => ''],
                ['key' => 'deans_title', 'title' => 'عمداء الكليات', 'body' => ''],
            ],
            'en' => [
                ['key' => 'rector_quote', 'title' => '', 'body' => 'Our vision is to foster an academic environment that not only pursues excellence in research and education but also actively contributes to the sustainable development of our society. We are committed to empowering our students to become the leaders and innovators of tomorrow.'],
                ['key' => 'vice_presidents_title', 'title' => 'Vice Presidents', 'body' => ''],
                ['key' => 'deans_title', 'title' => 'Faculty Deans', 'body' => ''],
            ],
        ];

        foreach ($leadershipCopy as $locale => $content) {
            $leadershipPage = DB::table('about_pages')->where('slug', 'leadership')->first(['id']);

            if ($leadershipPage === null) {
                continue;
            }

            $translation = DB::table('about_page_translations')
                ->where('about_page_id', $leadershipPage->id)
                ->where('locale', $locale)
                ->first(['id', 'sections_json']);

            if ($translation === null || $this->hasContent($translation->sections_json)) {
                continue;
            }

            DB::table('about_page_translations')
                ->where('id', $translation->id)
                ->update(['sections_json' => json_encode($content, JSON_THROW_ON_ERROR)]);
        }
    }

    public function down(): void
    {
        // Do not remove edited CMS content during rollback.
    }

    private function hasContent(mixed $sections): bool
    {
        if (is_array($sections)) {
            return $sections !== [];
        }

        if (! is_string($sections) || trim($sections) === '') {
            return false;
        }

        $decoded = json_decode($sections, true);

        return is_array($decoded) && $decoded !== [];
    }
};
