<x-filament-panels::page>
    <x-admin.cms-shell area="news">
        <x-filament-panels::form wire:submit="save">
            {{ $this->form }}
        </x-filament-panels::form>
    </x-admin.cms-shell>
</x-filament-panels::page>
