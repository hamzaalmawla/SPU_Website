<?php

declare(strict_types=1);

namespace App\Filament\Resources\CenterStaffLinkResource\Pages;

use App\Contracts\Center\CenterCatalogServiceInterface;
use App\Contracts\Staff\StaffIntakeLinkServiceInterface;
use App\DTOs\Staff\StaffIntakeLinkInputDTO;
use App\Filament\Resources\CenterStaffLinkResource;
use App\Filament\Resources\StaffIntakeLinkResource;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListCenterStaffLinks extends ListRecords
{
    protected static string $resource = CenterStaffLinkResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('staff_intake.center.links_intro');
    }

    protected function getHeaderActions(): array
    {
        $links = app(StaffIntakeLinkServiceInterface::class);
        $centers = app(CenterCatalogServiceInterface::class)->centerOptions(app()->getLocale());

        return [
            Action::make('create_link')
                ->label(__('staff_intake.admin.actions.create'))
                ->icon('heroicon-o-plus')
                ->visible($centers !== [] && $links->canUseCenters((int) auth()->id(), 'create'))
                ->form([
                    Select::make('center_slug')
                        ->label(__('staff_intake.center.center'))
                        ->options($centers)
                        ->default(count($centers) === 1 ? array_key_first($centers) : null)
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
                        facultyId: null,
                        label: is_string($data['label'] ?? null) ? $data['label'] : null,
                        expiresAt: filled($data['expires_at'] ?? null) ? new \DateTimeImmutable((string) $data['expires_at']) : null,
                        maxSubmissions: filled($data['max_submissions'] ?? null) ? (int) $data['max_submissions'] : null,
                        centerSlug: (string) $data['center_slug'],
                    ), (int) auth()->id());

                    StaffIntakeLinkResource::notifyLink($created, __('staff_intake.admin.notifications.link_created'));
                }),
        ];
    }
}
