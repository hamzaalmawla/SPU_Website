<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    <div class="flex flex-col gap-y-6">
        <section class="spu-task-grid" aria-labelledby="submission-inbox-heading">
            <div class="spu-task-grid__intro">
                <p class="spu-workspace__eyebrow">{{ __('form_submissions.workspace.eyebrow') }}</p>
                <h2 id="submission-inbox-heading">{{ __('form_submissions.workspace.heading') }}</h2>
                <p>{{ __('form_submissions.workspace.description') }}</p>
            </div>

            <nav class="spu-task-grid__items" aria-label="{{ __('form_submissions.workspace.task_navigation') }}">
                @foreach ($inboxTasks as $task)
                    <a
                        class="spu-task-card"
                        href="{{ $task['url'] }}"
                        @if ($task['active']) aria-current="page" @endif
                    >
                        <span class="spu-task-card__mark" aria-hidden="true">{{ $task['count'] }}</span>
                        <span>
                            <strong>{{ $task['label'] }}</strong>
                            <small>{{ $task['description'] }}</small>
                        </span>
                    </a>
                @endforeach
            </nav>
        </section>

        <x-filament-panels::resources.tabs />

        {{ $this->table }}
    </div>
</x-filament-panels::page>
