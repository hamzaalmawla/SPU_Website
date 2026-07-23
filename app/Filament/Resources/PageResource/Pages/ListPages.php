<?php

declare(strict_types=1);

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use App\Models\Page\Page;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;

class ListPages extends ListRecords
{
    protected static string $resource = PageResource::class;

    public function getTitle(): string
    {
        return __('admin.page_resource.headings.list');
    }

    public function getBreadcrumb(): ?string
    {
        return __('admin.page_resource.breadcrumbs.list');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('admin.page_resource.actions.create'))
                ->visible(fn (): bool => Gate::allows('create', Page::class)),
        ];
    }
}
