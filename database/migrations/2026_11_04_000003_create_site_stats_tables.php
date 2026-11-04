<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * First-party visitor statistics for the admin dashboard (config/site_stats.php).
 * Daily totals only: no IP address, cookie or user agent is stored. A visitor
 * hash lives two days, just long enough to count each visitor once a day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_traffic_days', function (Blueprint $table): void {
            $table->id();
            $table->date('day')->unique();
            $table->unsignedInteger('visitors')->default(0);
            $table->unsignedInteger('page_views')->default(0);
            // Visitors who opened a second page that day.
            $table->unsignedInteger('engaged_visitors')->default(0);
            // Visitors who asked the AI assistant at least one question.
            $table->unsignedInteger('chatbot_visitors')->default(0);
            $table->unsignedInteger('desktop_visitors')->default(0);
            $table->unsignedInteger('tablet_visitors')->default(0);
            $table->unsignedInteger('mobile_visitors')->default(0);
            $table->unsignedInteger('arabic_visitors')->default(0);
            $table->unsignedInteger('english_visitors')->default(0);
            // Where each visitor's first page of the day came from.
            $table->unsignedInteger('direct_visitors')->default(0);
            $table->unsignedInteger('search_visitors')->default(0);
            $table->unsignedInteger('social_visitors')->default(0);
            $table->unsignedInteger('referral_visitors')->default(0);
            $table->timestamps();
        });

        Schema::create('site_visitor_days', function (Blueprint $table): void {
            $table->id();
            $table->date('day');
            // HMAC of the day, the kind of visit, IP address and user agent.
            $table->char('visitor', 64);
            $table->unsignedInteger('page_views')->default(0);
            $table->timestamp('last_seen_at')->nullable();

            $table->unique(['day', 'visitor'], 'uniq_site_visitor_days_day_visitor');
            $table->index(['day', 'last_seen_at'], 'idx_site_visitor_days_seen');
        });

        Schema::create('site_page_days', function (Blueprint $table): void {
            $table->id();
            $table->date('day');
            $table->string('path', 255);
            $table->unsignedInteger('views')->default(0);

            $table->unique(['day', 'path'], 'uniq_site_page_days_day_path');
        });

        Schema::create('site_referrer_days', function (Blueprint $table): void {
            $table->id();
            $table->date('day');
            $table->string('host', 191);
            $table->unsignedInteger('visits')->default(0);

            $table->unique(['day', 'host'], 'uniq_site_referrer_days_day_host');
        });

        Schema::create('site_search_days', function (Blueprint $table): void {
            $table->id();
            $table->date('day');
            $table->string('locale', 5);
            $table->string('query', 120);
            $table->unsignedInteger('searches')->default(0);
            // Results the latest search for this term found.
            $table->unsignedInteger('results')->default(0);

            $table->unique(['day', 'locale', 'query'], 'uniq_site_search_days_term');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_search_days');
        Schema::dropIfExists('site_referrer_days');
        Schema::dropIfExists('site_page_days');
        Schema::dropIfExists('site_visitor_days');
        Schema::dropIfExists('site_traffic_days');
    }
};
