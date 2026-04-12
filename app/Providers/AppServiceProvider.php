<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\AuditServiceInterface;
use App\Contracts\AuthServiceInterface;
use App\Contracts\CacheServiceInterface;
use App\Contracts\ContactSubmissionServiceInterface;
use App\Contracts\EventServiceInterface;
use App\Contracts\FacultyServiceInterface;
use App\Contracts\FeaturedContentServiceInterface;
use App\Contracts\HomepagePublishingServiceInterface;
use App\Contracts\HomepageSectionServiceInterface;
use App\Contracts\LeadCaptureServiceInterface;
use App\Contracts\MediaServiceInterface;
use App\Contracts\MenuServiceInterface;
use App\Contracts\NewsServiceInterface;
use App\Contracts\NotifServiceInterface;
use App\Contracts\PreviewServiceInterface;
use App\Contracts\SearchServiceInterface;
use App\Contracts\SeoMetadataServiceInterface;
use App\Contracts\SlugServiceInterface;
use App\Services\Placeholders\AuditServicePlaceholder;
use App\Services\Placeholders\AuthServicePlaceholder;
use App\Services\Placeholders\CacheServicePlaceholder;
use App\Services\Placeholders\ContactSubmissionServicePlaceholder;
use App\Services\Placeholders\EventServicePlaceholder;
use App\Services\Placeholders\FacultyServicePlaceholder;
use App\Services\Placeholders\FeaturedContentServicePlaceholder;
use App\Services\Placeholders\HomepagePublishingServicePlaceholder;
use App\Services\Placeholders\HomepageSectionServicePlaceholder;
use App\Services\Placeholders\LeadCaptureServicePlaceholder;
use App\Services\Placeholders\MediaServicePlaceholder;
use App\Services\Placeholders\MenuServicePlaceholder;
use App\Services\Placeholders\NewsServicePlaceholder;
use App\Services\Placeholders\NotifServicePlaceholder;
use App\Services\Placeholders\PreviewServicePlaceholder;
use App\Services\Placeholders\SearchServicePlaceholder;
use App\Services\Placeholders\SeoMetadataServicePlaceholder;
use App\Services\Placeholders\SlugServicePlaceholder;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FacultyServiceInterface::class, FacultyServicePlaceholder::class);
        $this->app->bind(NewsServiceInterface::class, NewsServicePlaceholder::class);
        $this->app->bind(EventServiceInterface::class, EventServicePlaceholder::class);
        $this->app->bind(MediaServiceInterface::class, MediaServicePlaceholder::class);
        $this->app->bind(CacheServiceInterface::class, CacheServicePlaceholder::class);
        $this->app->bind(AuditServiceInterface::class, AuditServicePlaceholder::class);
        $this->app->bind(AuthServiceInterface::class, AuthServicePlaceholder::class);
        $this->app->bind(MenuServiceInterface::class, MenuServicePlaceholder::class);
        $this->app->bind(NotifServiceInterface::class, NotifServicePlaceholder::class);
        $this->app->bind(SearchServiceInterface::class, SearchServicePlaceholder::class);
        $this->app->bind(SlugServiceInterface::class, SlugServicePlaceholder::class);
        $this->app->bind(SeoMetadataServiceInterface::class, SeoMetadataServicePlaceholder::class);
        $this->app->bind(LeadCaptureServiceInterface::class, LeadCaptureServicePlaceholder::class);
        $this->app->bind(ContactSubmissionServiceInterface::class, ContactSubmissionServicePlaceholder::class);
        $this->app->bind(HomepageSectionServiceInterface::class, HomepageSectionServicePlaceholder::class);
        $this->app->bind(HomepagePublishingServiceInterface::class, HomepagePublishingServicePlaceholder::class);
        $this->app->bind(PreviewServiceInterface::class, PreviewServicePlaceholder::class);
        $this->app->bind(FeaturedContentServiceInterface::class, FeaturedContentServicePlaceholder::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
