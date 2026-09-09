<?php

declare(strict_types=1);

use App\Models\News\NewsArticle;
use App\Models\News\NewsArticleTranslation;
use App\Models\News\NewsCategory;
use App\Models\News\NewsCategoryTranslation;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $category = NewsCategory::query()->updateOrCreate(
            ['slug' => 'agreements'],
            ['type' => 'news', 'sort_order' => 3, 'is_enabled' => true],
        );

        NewsCategoryTranslation::query()->updateOrCreate(
            ['news_category_id' => $category->getKey(), 'locale' => 'ar'],
            ['name' => 'الاتفاقيات ومذكرات التفاهم', 'description' => 'الاتفاقيات ومذكرات التفاهم التي وقعتها الجامعة السورية الخاصة.'],
        );
        NewsCategoryTranslation::query()->updateOrCreate(
            ['news_category_id' => $category->getKey(), 'locale' => 'en'],
            ['name' => 'Agreements and Memoranda of Understanding', 'description' => 'Agreements and memoranda of understanding signed by the Syrian Private University.'],
        );

        $articles = [
            ['memorandum-latakia-university', 'توقيع مذكرة تفاهم بين الجامعة السورية الخاصة وجامعة اللاذقية', 'Signing a Memorandum of Understanding between SPU and Latakia University'],
            ['scientific-cultural-manara-university', 'توقيع اتفاق تعاون علمي وثقافي بين الجامعة السورية الخاصة وجامعة المنارة', 'Signing a Scientific and Cultural Cooperation Agreement between SPU and Al-Manara University'],
            ['scientific-cultural-al-sham-university', 'اتفاقية تعاون علمي وثقافي بين الجامعة السورية الخاصة وجامعة الشام الخاصة', 'Scientific and Cultural Cooperation Agreement between SPU and Al-Sham Private University'],
            ['memorandum-planning-statistics-authority', 'توقيع مذكرة تفاهم بين الجامعة السورية الخاصة وهيئة التخطيط والإحصاء', 'Signing a Memorandum of Understanding between SPU and the Planning and Statistics Authority'],
            ['virtual-university-delegation', 'الجامعة السورية الخاصة تستقبل وفداً من الجامعة الافتراضية السورية لبحث آفاق التعاون الأكاديمي', 'SPU Receives a Delegation from the Syrian Virtual University to Discuss Academic Cooperation'],
            ['memorandum-amman-ahliya', 'توقيع مذكرة تفاهم بين الجامعة السورية الخاصة وجامعة عمّان الأهلية - المملكة الأردنية الهاشمية', 'Signing a Memorandum of Understanding between SPU and Al-Ahliyya Amman University, Jordan'],
            ['cipher-cooperation-agreement', 'توقيع اتفاقية تعاون بين الجامعة السورية الخاصة وشركة النص المشفّر للأمن السيبراني (CIPHER)', 'Signing a Cooperation Agreement between SPU and CIPHER Cybersecurity'],
            ['human-resources-management-association', 'الجامعة السورية الخاصة توقع مذكرة تفاهم مع جمعية إدارة الموارد البشرية', 'SPU Signs a Memorandum of Understanding with the Human Resources Management Association'],
            ['india-universities-agreements', 'توقيع مجموعة من الاتفاقيات العلمية والأكاديمية بين الجامعة السورية الخاصة وبعض الجامعات في الهند', 'Signing Scientific and Academic Agreements between SPU and Universities in India'],
            ['damascus-hospital-cooperation', 'اتفاقية تعاون علمي وأكاديمي بين الجامعة السورية الخاصة ومشفى دمشق', 'Scientific and Academic Cooperation Agreement between SPU and Damascus Hospital'],
            ['al-hawash-university-agreement', 'توقيع اتفاقية تعاون علمي ثقافي بين الجامعة السورية الخاصة وجامعة الحواش الخاصة', 'Signing a Scientific and Cultural Cooperation Agreement between SPU and Al-Hawash Private University'],
            ['almujtahid-hospital-agreement', 'توقيع اتفاقية تعاون علمي وأكاديمي بين الجامعة السورية الخاصة ومشفى المجتهد', 'Signing a Scientific and Academic Cooperation Agreement between SPU and Al-Mujtahid Hospital'],
            ['zahrawi-hospital-agreement', 'توقيع اتفاقية تعاون علمي وأكاديمي بين الجامعة السورية الخاصة ومشفى الزهراوي', 'Signing a Scientific and Academic Cooperation Agreement between SPU and Al-Zahrawi Hospital'],
            ['asas-human-resources-agreement', 'توقيع مذكرة تفاهم بين الجامعة السورية الخاصة وشركة أسس للموارد البشرية والتطوير المؤسساتي', 'Signing a Memorandum of Understanding between SPU and Asas for Human Resources and Institutional Development'],
            ['damascus-university-agreement', 'توقيع اتفاقية تعاون علمي وثقافي بين الجامعة السورية الخاصة وجامعة دمشق', 'Signing a Scientific and Cultural Cooperation Agreement between SPU and Damascus University'],
        ];

        foreach ($articles as $order => [$slug, $arabicTitle, $englishTitle]) {
            $article = NewsArticle::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'news_category_id' => $category->getKey(),
                    'status' => 'published',
                    'published_at' => now(),
                    'is_enabled' => true,
                    'is_featured' => false,
                    'sort_order' => $order,
                ],
            );

            foreach (['ar' => $arabicTitle, 'en' => $englishTitle] as $locale => $title) {
                NewsArticleTranslation::query()->updateOrCreate(
                    ['news_article_id' => $article->getKey(), 'locale' => $locale],
                    ['title' => $title, 'excerpt' => $title, 'body' => '<p>'.$title.'</p>'],
                );
            }
        }
    }

    public function down(): void
    {
        $category = NewsCategory::query()->where('slug', 'agreements')->first();

        if ($category === null) {
            return;
        }

        NewsArticle::query()->where('news_category_id', $category->getKey())->delete();
        NewsCategoryTranslation::query()->where('news_category_id', $category->getKey())->delete();
        $category->delete();
    }
};
