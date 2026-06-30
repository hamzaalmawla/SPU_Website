<x-filament-panels::page>
    <x-admin.cms-shell
        area="homepage"
        :state="ucfirst($this->getHomepageState())"
        :state-color="$this->getStateBadgeColor()"
    >
        <x-filament-panels::form>
            {{ $this->form }}
        </x-filament-panels::form>
    </x-admin.cms-shell>
</x-filament-panels::page>
