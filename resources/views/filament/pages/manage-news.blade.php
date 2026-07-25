<x-filament-panels::page>
    <x-admin.cms-shell area="news">
        @if ($this->getNewsOperationalLinks() !== [])
            <section class="spu-task-grid" aria-labelledby="announcement-workspace-heading">
                <div class="spu-task-grid__intro">
                    <p class="spu-workspace__eyebrow">{{ __('admin.editorial_workspace.announcements.choose_task') }}</p>
                    <h2 id="announcement-workspace-heading">{{ __('admin.editorial_workspace.announcements.heading') }}</h2>
                    <p>{{ __('admin.editorial_workspace.announcements.description') }}</p>
                </div>
                <div class="spu-task-grid__items">
                    @foreach ($this->getNewsOperationalLinks() as $link)
                        <a href="{{ $link['url'] }}" class="spu-task-card">
                            <span class="spu-task-card__mark" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <span>
                                <strong>{{ $link['label'] }}</strong>
                                <small>{{ $link['description'] }}</small>
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <x-filament-panels::form wire:submit="save">
            {{ $this->form }}
        </x-filament-panels::form>
    </x-admin.cms-shell>
</x-filament-panels::page>
