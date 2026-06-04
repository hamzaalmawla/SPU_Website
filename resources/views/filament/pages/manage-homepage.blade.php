<x-filament-panels::page>
    <div class="flex items-center gap-3 mb-6">
        <h3 class="text-lg font-medium">Current State:</h3>
        <x-filament::badge :color="$this->getStateBadgeColor()">
            {{ ucfirst($this->getHomepageState()) }}
        </x-filament::badge>
    </div>

    <x-filament-panels::form>
        {{ $this->form }}
    </x-filament-panels::form>
</x-filament-panels::page>
