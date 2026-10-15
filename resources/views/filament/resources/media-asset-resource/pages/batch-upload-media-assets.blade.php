<x-filament-panels::page>
    <x-filament-panels::form wire:submit="upload">
        {{ $this->form }}

        @if ($this->pendingUploads !== [])
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200" role="status" aria-live="polite" data-batch-progress>
                Uploading… {{ $this->uploadedCount }} of {{ $this->batchTotal }} images stored. Keep this page open.
            </p>
        @endif

        <x-filament::button type="submit" icon="heroicon-o-arrow-up-tray" size="lg" :disabled="$this->pendingUploads !== []">
            Upload all images
        </x-filament::button>
    </x-filament-panels::form>
</x-filament-panels::page>
