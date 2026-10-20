<?php

declare(strict_types=1);

namespace App\Filament\Resources\StaffIntakeRequestResource\Pages;

use App\Contracts\Staff\StaffIntakeReviewServiceInterface;
use App\DTOs\Staff\StaffIntakeRequestDetailDTO;
use App\Filament\Resources\StaffIntakeRequestResource;
use App\Models\Staff\StaffIntakeRequest;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

class ViewStaffIntakeRequest extends ViewRecord
{
    protected static string $resource = StaffIntakeRequestResource::class;

    protected static string $view = 'filament.resources.staff-intake-request-resource.pages.view-staff-intake-request';

    private StaffIntakeReviewServiceInterface $reviewService;

    private ?StaffIntakeRequestDetailDTO $details = null;

    public function boot(StaffIntakeReviewServiceInterface $reviewService): void
    {
        $this->reviewService = $reviewService;
    }

    public function getTitle(): string|Htmlable
    {
        return $this->details()->submitterName.' — '.$this->details()->reference;
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        $details = $this->details();
        if ($details->status !== StaffIntakeRequest::STATUS_PENDING) {
            return [];
        }

        return [
            Action::make('approve')
                ->label(__('staff_intake.admin.actions.approve'))
                ->icon('heroicon-o-check')
                ->color('success')
                ->form([
                    Radio::make('target')
                        ->label(__('staff_intake.admin.actions.target'))
                        ->options($this->targetOptions($details))
                        ->default(array_key_first($this->targetOptions($details)))
                        ->required(),
                    Textarea::make('note')->label(__('staff_intake.admin.actions.note'))->rows(3)->maxLength(2000),
                ])
                ->action(function (array $data): void {
                    $target = (string) ($data['target'] ?? '');
                    $targetId = str_starts_with($target, 'member:') ? (int) substr($target, 7) : null;

                    try {
                        $result = $this->reviewService->approve(
                            (int) $this->getRecord()->getKey(),
                            (int) auth()->id(),
                            $targetId,
                            is_string($data['note'] ?? null) ? $data['note'] : null,
                        );
                    } catch (ValidationException|AuthorizationException $exception) {
                        $this->failed($exception);

                        return;
                    }

                    $this->refreshDetails();
                    Notification::make()
                        ->title(__($result->published ? 'staff_intake.admin.notifications.approved_published' : 'staff_intake.admin.notifications.approved_draft'))
                        ->body($result->publishErrors !== [] ? __('staff_intake.admin.notifications.not_publishable')."\n".implode("\n", $result->publishErrors) : null)
                        ->success()
                        ->send();
                }),
            Action::make('reject')
                ->label(__('staff_intake.admin.actions.reject'))
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->requiresConfirmation()
                ->form([Textarea::make('note')->label(__('staff_intake.admin.actions.note'))->rows(3)->maxLength(2000)])
                ->action(function (array $data): void {
                    try {
                        $this->reviewService->reject((int) $this->getRecord()->getKey(), (int) auth()->id(), is_string($data['note'] ?? null) ? $data['note'] : null);
                    } catch (AuthorizationException $exception) {
                        $this->failed($exception);

                        return;
                    }

                    $this->refreshDetails();
                    Notification::make()->title(__('staff_intake.admin.notifications.rejected'))->success()->send();
                }),
        ];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return ['details' => $this->details()];
    }

    /** @return array<string, string> */
    private function targetOptions(StaffIntakeRequestDetailDTO $details): array
    {
        $options = [];
        if ($details->type === StaffIntakeRequest::TYPE_UPDATE && $details->claimedMember !== null) {
            $options['claimed'] = __('staff_intake.admin.actions.target_claimed').': '.$details->claimedMember['name'];
        }
        if ($details->type === StaffIntakeRequest::TYPE_CREATE) {
            $options['new'] = __('staff_intake.admin.actions.target_new');
        }
        foreach ($details->candidates as $candidate) {
            if ($candidate['sameFaculty'] && ($details->claimedMember['id'] ?? null) !== $candidate['id']) {
                $options['member:'.$candidate['id']] = __('staff_intake.admin.actions.target_existing', ['name' => $candidate['name']]);
            }
        }

        return $options;
    }

    private function details(): StaffIntakeRequestDetailDTO
    {
        return $this->details ??= $this->reviewService->detail((int) $this->getRecord()->getKey(), app()->getLocale(), (int) auth()->id());
    }

    private function refreshDetails(): void
    {
        $this->details = null;
        $this->record->refresh();
    }

    private function failed(\Throwable $exception): void
    {
        $body = $exception instanceof ValidationException
            ? implode("\n", array_merge(...array_values($exception->errors())))
            : $exception->getMessage();

        Notification::make()->title(__('staff_intake.admin.notifications.failed'))->body($body)->danger()->persistent()->send();
    }
}
