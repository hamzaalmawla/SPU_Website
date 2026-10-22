<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Event details for articles in the Events and Society's Events categories:
 * sessions on separate dates, speakers (a university profile or typed in),
 * and, for Events, an optional registration form with seats.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_article_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('news_article_id')->unique()->constrained('news_articles')->cascadeOnDelete();
            $table->string('kind', 20)->default('event')->index();
            $table->boolean('requires_registration')->default(false);
            $table->boolean('show_on_calendar')->default(false)->index();
            $table->string('form_key')->nullable()->index();
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('prior_registrations')->default(0);
            $table->timestamp('registration_closes_at')->nullable();
            // First session's start and last session's end, kept for calendar and listing queries.
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->string('language', 10)->nullable();
            $table->boolean('has_certificate')->default(false);
            $table->json('audience')->nullable();
            $table->foreignId('invitation_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('map_url')->nullable();
            $table->string('online_url')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('news_article_event_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('news_article_event_id')->constrained('news_article_events')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('location')->nullable();
            $table->string('organizer')->nullable();
            $table->text('audience_note')->nullable();
            $table->text('certificate_note')->nullable();
            $table->longText('results')->nullable();
            $table->timestamps();

            $table->unique(['news_article_event_id', 'locale'], 'naet_event_locale_unique');
        });

        Schema::create('news_article_event_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('news_article_event_id')->constrained('news_article_events')->cascadeOnDelete();
            $table->date('date')->index();
            $table->time('starts_at');
            $table->time('ends_at')->nullable();
            $table->string('title_ar')->nullable();
            $table->string('title_en')->nullable();
            $table->string('location_ar')->nullable();
            $table->string('location_en')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('news_article_event_speakers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('news_article_event_id')->constrained('news_article_events')->cascadeOnDelete();
            // Empty: the speaker belongs to the whole event.
            $table->foreignId('news_article_event_session_id')->nullable()->constrained('news_article_event_sessions')->cascadeOnDelete();
            // One of the two is set for a university speaker; neither for an outside one.
            $table->foreignId('faculty_member_id')->nullable()->constrained('faculty_members')->nullOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('persons')->nullOnDelete();
            $table->string('name_ar')->nullable();
            $table->string('name_en')->nullable();
            $table->string('title_ar')->nullable();
            $table->string('title_en')->nullable();
            $table->string('organization_ar')->nullable();
            $table->string('organization_en')->nullable();
            $table->text('bio_ar')->nullable();
            $table->text('bio_en')->nullable();
            $table->foreignId('photo_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $this->addEventsCategory();
    }

    public function down(): void
    {
        Schema::dropIfExists('news_article_event_speakers');
        Schema::dropIfExists('news_article_event_sessions');
        Schema::dropIfExists('news_article_event_translations');
        Schema::dropIfExists('news_article_events');
        // The Events category is kept: articles may already use it.
    }

    private function addEventsCategory(): void
    {
        DB::transaction(function (): void {
            $now = now();
            $category = DB::table('news_categories')->where('slug', 'events')->first();

            if ($category === null) {
                $categoryId = DB::table('news_categories')->insertGetId([
                    'slug' => 'events',
                    'type' => 'event',
                    'sort_order' => 5,
                    'is_enabled' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $categoryId = (int) $category->id;

                if ($category->type !== 'event' || $category->deleted_at !== null) {
                    throw new RuntimeException('The events slug already belongs to an incompatible or deleted news category. Deployment stopped without overwriting it.');
                }
            }

            foreach ([
                'ar' => ['الفعاليات', 'فعاليات الجامعة السورية الخاصة ومؤتمراتها وندواتها.'],
                'en' => ['Events', 'Syrian Private University events, conferences and seminars.'],
            ] as $locale => [$name, $description]) {
                if (! DB::table('news_category_translations')->where('news_category_id', $categoryId)->where('locale', $locale)->exists()) {
                    DB::table('news_category_translations')->insert([
                        'news_category_id' => $categoryId,
                        'locale' => $locale,
                        'name' => $name,
                        'description' => $description,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });
    }
};
