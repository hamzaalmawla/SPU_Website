<?php

declare(strict_types=1);

namespace App\Filament\Resources\FacultyMemberResource\Pages;

use App\Contracts\Content\PeopleMergeServiceInterface;
use App\Filament\Resources\Concerns\InteractsWithAboutEntityCmsWorkflow;
use App\Filament\Resources\FacultyMemberResource;
use App\Models\Person\FacultyMember;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class EditFacultyMember extends EditRecord
{
    use InteractsWithAboutEntityCmsWorkflow {
        getHeaderActions as cmsHeaderActions;
    }

    protected static string $resource = FacultyMemberResource::class;

    protected function entityType(): string
    {
        return 'faculty-member';
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return FacultyMemberResource::prepareFacultyMemberFormData($data, (int) $this->record->getKey());
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            ...$this->cmsHeaderActions(),
            Action::make('linkLeadership')
                ->label(__('admin_resources.faculty_member.link_leadership'))
                ->icon('heroicon-o-link')
                ->color('gray')
                ->visible(fn (): bool => $this->linkedPersonId() === null && $this->canLink())
                ->modalHeading(__('admin_resources.faculty_member.link_leadership_heading'))
                ->modalDescription(__('admin_resources.faculty_member.link_leadership_help'))
                ->form([
                    Select::make('person_id')
                        ->label(__('admin_resources.faculty_member.leadership_profile'))
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => app(PeopleMergeServiceInterface::class)->leadershipOptions($search))
                        ->getOptionLabelUsing(fn (mixed $value): ?string => app(PeopleMergeServiceInterface::class)->leadershipOptions()[(int) $value] ?? null)
                        ->options(fn (): array => app(PeopleMergeServiceInterface::class)->leadershipOptions())
                        ->required(),
                ])
                ->action(function (array $data): void {
                    try {
                        app(PeopleMergeServiceInterface::class)->linkFacultyMember((int) $this->record->getKey(), (int) $data['person_id'], (int) auth()->id());
                    } catch (AuthorizationException|InvalidArgumentException $exception) {
                        Notification::make()->title(__('admin_resources.faculty_member.link_failed'))->body($exception->getMessage())->danger()->send();

                        return;
                    }

                    $this->record->refresh();
                    Notification::make()->title(__('admin_resources.faculty_member.linked_to_leadership'))->success()->send();
                }),
            Action::make('unlinkLeadership')
                ->label(__('admin_resources.faculty_member.unlink_leadership'))
                ->icon('heroicon-o-link-slash')
                ->color('gray')
                ->visible(fn (): bool => $this->linkedPersonId() !== null && $this->canLink())
                ->requiresConfirmation()
                ->modalDescription(__('admin_resources.faculty_member.unlink_leadership_description'))
                ->action(function (): void {
                    try {
                        app(PeopleMergeServiceInterface::class)->unlinkFacultyMember((int) $this->record->getKey(), (int) auth()->id());
                    } catch (AuthorizationException $exception) {
                        Notification::make()->title(__('admin_resources.faculty_member.link_failed'))->body($exception->getMessage())->danger()->send();

                        return;
                    }

                    $this->record->refresh();
                    Notification::make()->title(__('admin_resources.faculty_member.unlinked_from_leadership'))->success()->send();
                }),
        ];
    }

    private function linkedPersonId(): ?int
    {
        return $this->record instanceof FacultyMember && $this->record->person_id !== null ? (int) $this->record->person_id : null;
    }

    private function canLink(): bool
    {
        return Gate::allows('faculty_members.update') && Gate::allows('people.update');
    }
}
