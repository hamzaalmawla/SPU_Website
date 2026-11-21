<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Contracts\Chatbot\ChatbotUsageServiceInterface;
use App\Contracts\Dashboard\AdminDashboardServiceInterface;
use App\Filament\Pages\ManageChatbot;
use App\Filament\Resources\CenterStaffRequestResource;
use App\Filament\Resources\ContactMessageResource;
use App\Filament\Resources\DynamicFormSubmissionResource;
use App\Filament\Resources\StaffIntakeRequestResource;
use App\Filament\Resources\UserResource;
use Filament\Widgets\Widget;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * The dashboard's work queue: inbox items, staff requests, unpublished and
 * scheduled pages, missing pages, failed jobs and locked accounts, each only
 * for a user who can act on it. Inbox counts use each screen's own query, so
 * the count matches the list its link opens.
 */
final class NeedsAttention extends Widget
{
    /** Older drafts are left work, not the queue. */
    private const DRAFT_DAYS = 60;

    protected static ?int $sort = 2;

    protected static string $view = 'filament.widgets.needs-attention';

    protected int|string|array $columnSpan = 'full';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $user = auth()->user();

        if (! $user instanceof Authenticatable) {
            return ['items' => [], 'drafts' => collect(), 'scheduled' => collect(), 'missingPages' => collect()];
        }

        $dashboard = app(AdminDashboardServiceInterface::class);
        $drafts = $dashboard->unpublishedDrafts($user, self::DRAFT_DAYS);
        $scheduled = $dashboard->scheduledPublishing($user);
        $missingPages = $dashboard->missingPages($user);
        $items = array_values(array_filter([
            $this->inbox(DynamicFormSubmissionResource::class, 'new', 'form_submissions', 'heroicon-o-inbox-arrow-down'),
            $this->inbox(ContactMessageResource::class, 'new', 'contact_messages', 'heroicon-o-envelope'),
            $this->inbox(StaffIntakeRequestResource::class, 'pending', 'staff_intake', 'heroicon-o-identification'),
            $this->inbox(CenterStaffRequestResource::class, 'pending', 'center_staff_intake', 'heroicon-o-identification'),
            $this->item('drafts', $drafts->count(), 'heroicon-o-pencil-square', 'warning', label: __('admin_dashboard.attention.drafts', ['days' => self::DRAFT_DAYS])),
            $this->item('scheduled', $scheduled->count(), 'heroicon-o-clock', 'info'),
            $this->item('missing_pages', $dashboard->missingPagesCount($user), 'heroicon-o-link-slash', 'warning', hint: __('admin_dashboard.attention.missing_pages_hint')),
            $this->chatbotFailures(),
            $this->item('failed_jobs', $dashboard->failedJobsCount($user), 'heroicon-o-exclamation-triangle', 'danger', hint: __('admin_dashboard.attention.failed_jobs_hint')),
            $this->item('locked_accounts', $dashboard->lockedAccountsCount($user), 'heroicon-o-lock-closed', 'danger', UserResource::canViewAny() ? UserResource::getUrl() : null),
        ]));

        return [
            'items' => $items,
            'drafts' => $drafts,
            'scheduled' => $scheduled,
            'missingPages' => $missingPages,
        ];
    }

    /**
     * New items in one inbox screen the user can open.
     *
     * @param  class-string<\Filament\Resources\Resource>  $resource
     * @return array{key: string, label: string, count: int, icon: string, tone: string, url: ?string, hint: ?string}|null
     */
    private function inbox(string $resource, string $status, string $key, string $icon): ?array
    {
        try {
            if (! $resource::canViewAny()) {
                return null;
            }

            $count = $resource::getEloquentQuery()->where('status', $status)->count();

            return $this->item($key, $count, $icon, 'primary', $resource::getUrl('index', ['tableFilters' => ['status' => ['value' => $status]]]));
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /** @return array{key: string, label: string, count: int, icon: string, tone: string, url: ?string, hint: ?string}|null */
    private function chatbotFailures(): ?array
    {
        if (! Gate::allows('chatbot.view')) {
            return null;
        }

        return $this->item('chatbot_failures', app(ChatbotUsageServiceInterface::class)->summary()->failuresToday, 'heroicon-o-chat-bubble-bottom-center-text', 'danger', ManageChatbot::getUrl());
    }

    /** @return array{key: string, label: string, count: int, icon: string, tone: string, url: ?string, hint: ?string}|null */
    private function item(string $key, int $count, string $icon, string $tone, ?string $url = null, ?string $hint = null, ?string $label = null): ?array
    {
        return $count > 0 ? [
            'key' => $key,
            'label' => $label ?? __('admin_dashboard.attention.'.$key),
            'count' => $count,
            'icon' => $icon,
            'tone' => $tone,
            'url' => $url,
            'hint' => $hint,
        ] : null;
    }
}
