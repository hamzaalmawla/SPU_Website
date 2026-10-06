<x-filament-panels::page>
    <x-filament-panels::form wire:submit="upload">
        {{ $this->form }}

        <x-filament::button type="submit" icon="heroicon-o-arrow-up-tray" size="lg">
            Upload all images
        </x-filament::button>
    </x-filament-panels::form>
</x-filament-panels::page>
