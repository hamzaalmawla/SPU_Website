<?php

declare(strict_types=1);

namespace App\Filament\Resources\DynamicFormSubmissionResource\Pages;

use App\Enums\FormSubmissionInbox;
use App\Filament\Resources\DynamicFormSubmissionResource;
use App\Models\User\User;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListDynamicFormSubmissions extends ListRecords
{
    protected static string $resource = DynamicFormSubmissionResource::class;

    protected static string $view = 'filament.resources.dynamic-form-submission-resource.pages.list-dynamic-form-submissions';

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        $tabs = [];

        foreach ($this->visibleInboxes() as $inbox) {
            $tabs[$inbox->value] = Tab::make(__('form_submissions.inboxes.'.$inbox->value))
                ->icon($this->inboxIcon($inbox))
                ->badge(fn (): int => $this->inboxCount($inbox))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('form_id', $inbox->formIds()));
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string
    {
        return $this->visibleInboxes()[0]->value;
    }

    public function getBreadcrumb(): string
    {
        return __('form_submissions.resource.plural_model');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'inboxTasks' => collect($this->visibleInboxes())
                ->map(fn (FormSubmissionInbox $inbox): array => [
                    'label' => __('form_submissions.inboxes.'.$inbox->value),
                    'description' => __('form_submissions.workspace.tasks.'.$inbox->value),
                    'count' => $this->inboxCount($inbox),
                    'url' => DynamicFormSubmissionResource::getUrl('index', ['activeTab' => $inbox->value]),
                    'active' => $this->activeTab === $inbox->value,
                ])
                ->all(),
        ];
    }

    private function inboxCount(FormSubmissionInbox $inbox): int
    {
        return DynamicFormSubmissionResource::getEloquentQuery()
            ->whereIn('form_id', $inbox->formIds())
            ->whereNull('read_at')
            ->count();
    }

    private function inboxIcon(FormSubmissionInbox $inbox): string
    {
        return match ($inbox) {
            FormSubmissionInbox::EVENT_REGISTRATIONS => 'heroicon-o-calendar-days',
            FormSubmissionInbox::JOBS => 'heroicon-o-briefcase',
            FormSubmissionInbox::ADMISSIONS => 'heroicon-o-academic-cap',
            FormSubmissionInbox::SUGGESTIONS => 'heroicon-o-chat-bubble-left-right',
        };
    }

    /** @return list<FormSubmissionInbox> */
    private function visibleInboxes(): array
    {
        $user = auth()->user();

        return $user instanceof User && $user->role_slug === 'hr'
            ? [FormSubmissionInbox::JOBS]
            : FormSubmissionInbox::cases();
    }
}
