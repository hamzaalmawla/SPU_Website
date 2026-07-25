<?php

declare(strict_types=1);

namespace App\Filament\Resources\DynamicFormSubmissionResource\Pages;

use App\Contracts\Form\DynamicFormSubmissionReviewServiceInterface;
use App\DTOs\Form\DynamicFormSubmissionDetailDTO;
use App\Enums\FormSubmissionStatus;
use App\Exceptions\ConflictException;
use App\Filament\Resources\DynamicFormSubmissionResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Throwable;

class ViewDynamicFormSubmission extends ViewRecord
{
    protected static string $resource = DynamicFormSubmissionResource::class;

    protected static string $view = 'filament.resources.dynamic-form-submission-resource.pages.view-dynamic-form-submission';

    private DynamicFormSubmissionReviewServiceInterface $reviewService;

    private ?DynamicFormSubmissionDetailDTO $details = null;

    public function boot(DynamicFormSubmissionReviewServiceInterface $reviewService): void
    {
        $this->reviewService = $reviewService;
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->authorizeAccess();
    }

    public function getTitle(): string|Htmlable
    {
        return __('form_submissions.detail.title', [
            'applicant' => $this->details()->applicantName
                ?: $this->details()->applicantEmail
                ?: __('form_submissions.values.unknown_applicant'),
        ]);
    }

    public function getBreadcrumb(): string
    {
        return __('form_submissions.actions.review');
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        $details = $this->details();
        $actions = [
            Action::make('back_to_inbox')
                ->label(__('form_submissions.actions.back_to_inbox'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(DynamicFormSubmissionResource::getUrl('index', [
                    'activeTab' => $details->inbox?->value,
                ])),
        ];

        if (! $details->status instanceof FormSubmissionStatus || $details->inbox === null) {
            return $actions;
        }

        foreach ($details->status->legalTransitions($details->inbox) as $nextStatus) {
            $expectedStatus = $details->status;
            $actions[] = Action::make('transition_'.$nextStatus->value)
                ->label(__('form_submissions.actions.transitions.'.$nextStatus->value))
                ->icon($this->transitionIcon($nextStatus))
                ->color($this->statusColor($nextStatus))
                ->requiresConfirmation()
                ->modalHeading(__('form_submissions.actions.confirm_heading'))
                ->modalDescription(__('form_submissions.actions.confirm_description', [
                    'status' => __('form_submissions.statuses.'.$nextStatus->value),
                ]))
                ->modalSubmitActionLabel(__('form_submissions.actions.confirm'))
                ->action(fn (): mixed => $this->transition($expectedStatus, $nextStatus));
        }

        return $actions;
    }

    public function statusColor(?FormSubmissionStatus $status): string
    {
        return match ($status) {
            FormSubmissionStatus::NEW => 'info',
            FormSubmissionStatus::IN_REVIEW => 'warning',
            FormSubmissionStatus::ACCEPTED, FormSubmissionStatus::RESOLVED => 'success',
            FormSubmissionStatus::REJECTED => 'danger',
            FormSubmissionStatus::CLOSED, null => 'gray',
        };
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return ['details' => $this->details()];
    }

    private function details(): DynamicFormSubmissionDetailDTO
    {
        return $this->details ??= $this->reviewService->getDetails(
            (int) $this->getRecord()->getKey(),
            app()->getLocale(),
        );
    }

    private function transition(FormSubmissionStatus $expectedStatus, FormSubmissionStatus $nextStatus): mixed
    {
        try {
            $transitioned = $this->reviewService->transitionStatus(
                (int) $this->getRecord()->getKey(),
                $expectedStatus,
                $nextStatus,
                (int) auth()->id(),
            );

            if (! $transitioned) {
                throw new \RuntimeException('The submission transition did not complete.');
            }

            Notification::make()
                ->title(__('form_submissions.notifications.transitioned'))
                ->body(__('form_submissions.notifications.transitioned_description', [
                    'status' => __('form_submissions.statuses.'.$nextStatus->value),
                ]))
                ->success()
                ->send();

            $this->details = null;

            return $this->redirect(DynamicFormSubmissionResource::getUrl('view', [
                'record' => $this->getRecord(),
            ]), navigate: false);
        } catch (ConflictException) {
            Notification::make()
                ->title(__('form_submissions.notifications.stale'))
                ->body(__('form_submissions.notifications.stale_description'))
                ->warning()
                ->persistent()
                ->send();

            $this->details = null;

            return $this->redirect(DynamicFormSubmissionResource::getUrl('view', [
                'record' => $this->getRecord(),
            ]), navigate: false);
        } catch (\DomainException) {
            Notification::make()
                ->title(__('form_submissions.notifications.illegal'))
                ->body(__('form_submissions.notifications.illegal_description'))
                ->danger()
                ->send();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title(__('form_submissions.notifications.failed'))
                ->body(__('form_submissions.notifications.safe_error'))
                ->danger()
                ->send();
        }

        $this->details = null;

        return null;
    }

    private function transitionIcon(FormSubmissionStatus $status): string
    {
        return match ($status) {
            FormSubmissionStatus::IN_REVIEW => 'heroicon-o-eye',
            FormSubmissionStatus::ACCEPTED => 'heroicon-o-check-circle',
            FormSubmissionStatus::REJECTED => 'heroicon-o-x-circle',
            FormSubmissionStatus::RESOLVED => 'heroicon-o-check-badge',
            FormSubmissionStatus::CLOSED => 'heroicon-o-lock-closed',
            FormSubmissionStatus::NEW => 'heroicon-o-inbox',
        };
    }
}
