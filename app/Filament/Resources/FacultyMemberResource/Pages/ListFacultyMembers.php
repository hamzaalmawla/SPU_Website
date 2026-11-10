<?php

declare(strict_types=1);

namespace App\Filament\Resources\FacultyMemberResource\Pages;

use App\Contracts\Content\ProfileAdminServiceInterface;
use App\Filament\Resources\FacultyMemberResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListFacultyMembers extends ListRecords
{
    protected static string $resource = FacultyMemberResource::class;

    /** Set when the list is opened from a faculty page, so it shows only that faculty. */
    #[Url(as: 'faculty_scope')]
    public ?string $facultyScope = null;

    public function getTitle(): string|Htmlable
    {
        $facultyName = $this->contextFacultyName();

        return $facultyName !== null
            ? __('admin_resources.faculty_member.members_of', ['faculty' => $facultyName])
            : parent::getTitle();
    }

    protected function getTableQuery(): ?Builder
    {
        $query = FacultyMemberResource::getEloquentQuery();
        $scope = FacultyMemberResource::contextScope($this);

        return $scope !== '' ? FacultyMemberResource::whereFacultyScope($query, $scope) : $query;
    }

    protected function getHeaderActions(): array
    {
        $scope = FacultyMemberResource::contextScope($this);

        return [
            Actions\CreateAction::make()
                ->label(__('admin_resources.faculty_member.add_member'))
                ->url(FacultyMemberResource::getUrl('create', $scope !== '' ? ['faculty_scope' => $scope] : [])),
        ];
    }

    private function contextFacultyName(): ?string
    {
        $facultyId = FacultyMemberResource::contextFacultyId($this);
        if ($facultyId === null) {
            return null;
        }

        return app(ProfileAdminServiceInterface::class)->facultyOptions((int) auth()->id())[$facultyId] ?? null;
    }
}
