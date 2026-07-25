<x-filament-panels::page>
    <x-admin.cms-shell area="facilities">
        <section class="spu-task-grid" aria-labelledby="faculty-task-heading">
            <div class="spu-task-grid__intro">
                <p class="spu-workspace__eyebrow">{{ __('admin.faculty_workspace.choose_task') }}</p>
                <h2 id="faculty-task-heading">{{ __('admin.faculty_workspace.heading') }}</h2>
                <p>{{ __('admin.faculty_workspace.description') }}</p>
            </div>

            <div class="spu-task-grid__items">
                @foreach ($this->getFacultyWorkspaceTasks() as $task)
                    <a href="{{ $task['url'] }}" class="spu-task-card" @if ($task['active']) aria-current="page" @endif>
                        <span class="spu-task-card__mark">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        <span>
                            <strong>{{ $task['label'] }}</strong>
                            <small>{{ $task['description'] }}</small>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>

        @if ($this->getStudyPlanDepartmentNavigation() !== [])
            <section class="spu-choice-panel" aria-labelledby="study-plan-department-heading">
                <div>
                    <p class="spu-workspace__eyebrow">{{ __('admin.faculty_workspace.study_plan.step_department') }}</p>
                    <h2 id="study-plan-department-heading">{{ __('admin.faculty_workspace.study_plan.choose_department') }}</h2>
                </div>
                <nav class="spu-choice-links" aria-label="{{ __('admin.faculty_workspace.study_plan.choose_department') }}">
                    @foreach ($this->getStudyPlanDepartmentNavigation() as $department)
                        <a href="{{ $department['url'] }}" @if ($department['active']) aria-current="page" @endif>
                            {{ $department['label'] }}
                        </a>
                    @endforeach
                </nav>
            </section>

            <section class="spu-choice-panel" aria-labelledby="study-plan-term-heading">
                <div>
                    <p class="spu-workspace__eyebrow">{{ __('admin.faculty_workspace.study_plan.step_term') }}</p>
                    <h2 id="study-plan-term-heading">{{ __('admin.faculty_workspace.study_plan.choose_term') }}</h2>
                </div>
                <nav class="spu-choice-links" aria-label="{{ __('admin.faculty_workspace.study_plan.choose_term') }}">
                    @foreach ($this->getStudyPlanTermNavigation() as $term)
                        <a href="{{ $term['url'] }}" @if ($term['active']) aria-current="page" @endif>
                            {{ $term['label'] }}
                        </a>
                    @endforeach
                </nav>
            </section>
        @endif

        <x-filament-panels::form wire:submit="save">
            {{ $this->form }}
        </x-filament-panels::form>
    </x-admin.cms-shell>
</x-filament-panels::page>
