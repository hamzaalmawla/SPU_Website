<x-filament-panels::page>
    <x-admin.cms-shell area="research">
        <section class="spu-task-grid" aria-labelledby="research-task-heading">
            <div class="spu-task-grid__intro">
                <p class="spu-workspace__eyebrow">{{ __('admin.research_workspace.choose_task') }}</p>
                <h2 id="research-task-heading">{{ __('admin.research_workspace.heading') }}</h2>
                <p>{{ __('admin.research_workspace.description') }}</p>
            </div>

            <div class="spu-task-grid__items">
                @foreach ($this->getWorkspaceTasks() as $task)
                    <a
                        href="{{ $task['url'] }}"
                        class="spu-task-card"
                        @if ($task['active']) aria-current="page" @endif
                    >
                        <span class="spu-task-card__mark">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        <span>
                            <strong>{{ $task['label'] }}</strong>
                            <small>{{ $task['description'] }}</small>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>

        <x-filament-panels::form wire:submit="save">
            {{ $this->form }}
        </x-filament-panels::form>
    </x-admin.cms-shell>
</x-filament-panels::page>
