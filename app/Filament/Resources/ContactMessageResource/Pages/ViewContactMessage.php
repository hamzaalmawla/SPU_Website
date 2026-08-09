<?php

declare(strict_types=1);

namespace App\Filament\Resources\ContactMessageResource\Pages;

use App\Contracts\Form\ContactMessageReviewServiceInterface;
use App\Enums\ContactMessageStatus;
use App\Exceptions\ConflictException;
use App\Filament\Resources\ContactMessageResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use DomainException;
use Throwable;

class ViewContactMessage extends ViewRecord
{
    protected static string $resource = ContactMessageResource::class;

    private ContactMessageReviewServiceInterface $reviewService;

    public function boot(ContactMessageReviewServiceInterface $reviewService): void
    {
        $this->reviewService = $reviewService;
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->authorizeAccess();
        $this->reviewService->markAsRead((int) $this->getRecord()->getKey(), (int) auth()->id());
        $this->record = $this->resolveRecord($record);
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        $message = $this->getRecord();
        $status = ContactMessageStatus::tryFrom((string) $message->status) ?? ContactMessageStatus::NEW;
        $actions = [];

        if ($message->read_at !== null) {
            $actions[] = Action::make('mark_unread')
                ->label(__('contact_messages.actions.mark_unread'))
                ->icon('heroicon-o-envelope')
                ->color('gray')
                ->action(function (): void {
                    $this->reviewService->markAsUnread((int) $this->getRecord()->getKey(), (int) auth()->id());
                    $this->record = $this->resolveRecord((string) $this->getRecord()->getKey());
                    Notification::make()->title(__('contact_messages.notifications.marked_unread'))->success()->send();
                });
        }

        $actions[] = Action::make('assign_to_me')
            ->label(__('contact_messages.actions.assign_to_me'))
            ->icon('heroicon-o-user-plus')
            ->color('gray')
            ->action(function (): void {
                $this->reviewService->assign((int) $this->getRecord()->getKey(), (int) auth()->id(), (int) auth()->id());
                $this->record = $this->resolveRecord((string) $this->getRecord()->getKey());
                Notification::make()->title(__('contact_messages.notifications.assigned'))->success()->send();
            });
        $actions[] = Action::make('internal_notes')
            ->label(__('contact_messages.actions.internal_notes'))
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->form([Textarea::make('notes')->label(__('contact_messages.fields.internal_notes'))->rows(5)])
            ->fillForm(['notes' => $message->internal_notes])
            ->action(function (array $data): void {
                $this->reviewService->updateInternalNotes((int) $this->getRecord()->getKey(), is_string($data['notes'] ?? null) ? $data['notes'] : null, (int) auth()->id());
                $this->record = $this->resolveRecord((string) $this->getRecord()->getKey());
                Notification::make()->title(__('contact_messages.notifications.notes_saved'))->success()->send();
            });

        foreach ($status->legalTransitions() as $next) {
            $actions[] = Action::make('transition_'.$next->value)
                ->label($this->actionLabel($next))
                ->color($next === ContactMessageStatus::CLOSED ? 'gray' : ($next === ContactMessageStatus::IN_REVIEW ? 'warning' : 'success'))
                ->requiresConfirmation()
                ->form([Textarea::make('reason')->label(__('form_submissions.detail.reason'))->rows(4)])
                ->action(fn (array $data): mixed => $this->transition($status, $next, is_string($data['reason'] ?? null) ? $data['reason'] : null));
        }

        return $actions;
    }

    private function actionLabel(ContactMessageStatus $status): string
    {
        return match ($status) {
            ContactMessageStatus::IN_REVIEW => __('contact_messages.actions.start_review'),
            ContactMessageStatus::RESOLVED => __('contact_messages.actions.resolve'),
            ContactMessageStatus::CLOSED => __('contact_messages.actions.close'),
            ContactMessageStatus::NEW => __('contact_messages.actions.start_review'),
        };
    }

    private function transition(ContactMessageStatus $expected, ContactMessageStatus $next, ?string $reason): mixed
    {
        try {
            $this->reviewService->transitionStatus((int) $this->getRecord()->getKey(), $expected, $next, (int) auth()->id(), $reason);
            $this->record = $this->resolveRecord((string) $this->getRecord()->getKey());
            Notification::make()->title(__('contact_messages.notifications.transitioned'))->success()->send();
            return null;
        } catch (ConflictException|DomainException) {
            Notification::make()->title(__('contact_messages.notifications.failed'))->danger()->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title(__('contact_messages.notifications.failed'))->danger()->send();
        }

        return null;
    }
}
