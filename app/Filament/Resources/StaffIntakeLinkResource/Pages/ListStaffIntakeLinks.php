<?php

declare(strict_types=1);

namespace App\Filament\Resources\StaffIntakeLinkResource\Pages;

use App\Contracts\Staff\StaffIntakeLinkServiceInterface;
use App\DTOs\Staff\StaffIntakeLinkInputDTO;
use App\Filament\Resources\StaffIntakeLinkResource;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;

class ListStaffIntakeLinks extends ListRecords
{
    protected static string $resource = StaffIntakeLinkResource::class;

    protected function getHeaderActions(): array
    {
        $links = app(StaffIntakeLinkServiceInterface::class);
        $faculties = $links->facultyOptions((int) auth()->id());

        return [
            Action::make('create_link')
                ->label(__('staff_intake.admin.actions.create'))
                ->icon('heroicon-o-plus')
                ->form([
                    Select::make('faculty_id')
                        ->label(__('staff_intake.admin.form.faculty'))
                        ->options($faculties)
                        ->default(count($faculties) === 1 ? array_key_first($faculties) : null)
                        ->required(),
                    TextInput::make('label')
                        ->label(__('staff_intake.admin.form.label'))
                        ->helperText(__('staff_intake.admin.form.label_hint'))
                        ->maxLength(255),
                    DateTimePicker::make('expires_at')
                        ->label(__('staff_intake.admin.form.expires_at'))
                        ->helperText(__('staff_intake.admin.form.expires_hint'))
                        ->minDate(now())
                        ->native(false),
                    TextInput::make('max_submissions')
                        ->label(__('staff_intake.admin.form.max_submissions'))
                        ->helperText(__('staff_intake.admin.form.max_hint'))
                        ->numeric()
                        ->minValue(1),
                ])
                ->action(function (array $data) use ($links): void {
                    $created = $links->create(new StaffIntakeLinkInputDTO(
                        facultyId: (int) $data['faculty_id'],
                        label: is_string($data['label'] ?? null) ? $data['label'] : null,
                        expiresAt: filled($data['expires_at'] ?? null) ? new \DateTimeImmutable((string) $data['expires_at']) : null,
                        maxSubmissions: filled($data['max_submissions'] ?? null) ? (int) $data['max_submissions'] : null,
                    ), (int) auth()->id());

                    StaffIntakeLinkResource::notifyLink($created, __('staff_intake.admin.notifications.link_created'));
                }),
        ];
    }
}
