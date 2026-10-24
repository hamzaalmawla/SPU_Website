@php
    $board = $this->studyPlanBoardData();
    $t = static fn (string $key, array $replace = []): string => __('admin.faculty_workspace.board.'.$key, $replace);
    $creditsShort = $t('credits_short');
    $termCount = count($board['terms']);
@endphp

<section class="spu-board" aria-labelledby="spu-board-heading" wire:key="study-plan-board">
    <div class="spu-board__head">
        <p class="spu-workspace__eyebrow">{{ $t('eyebrow') }}</p>
        <h2 id="spu-board-heading">{{ $t('title') }}</h2>
        <p>{{ $t('help') }}</p>
    </div>

    @if ($board['departments'] === [])
        <div class="spu-board__empty">
            <p>{{ $t('no_departments') }}</p>
            <button type="button" class="spu-board__btn spu-board__btn--primary" wire:click="newBoardDepartment">+ {{ $t('add_department') }}</button>
        </div>
    @else
        <div class="spu-board__bar">
            <nav class="spu-board__tabs" aria-label="{{ $t('title') }}">
                @foreach ($board['departments'] as $department)
                    <button type="button" wire:click="selectBoardDepartment('{{ $department['id'] }}')" wire:key="board-tab-{{ $department['id'] }}"
                        @if ($department['id'] === $board['departmentId']) aria-current="true" @endif>{{ $department['name'] }}</button>
                @endforeach
                <button type="button" class="spu-board__tab-add" wire:click="newBoardDepartment">+ {{ $t('add_department') }}</button>
            </nav>
            <div class="spu-board__bar-actions">
                <button type="button" class="spu-board__btn" wire:click="editBoardDepartment">{{ $t('edit_department') }}</button>
            </div>
        </div>

        <p class="spu-board__summary">
            {{ $t('required_credits') }}: <strong>{{ $board['department']['calculatedCredits'] }}</strong>
            @if ((float) $board['department']['totalCredits'] !== (float) $board['department']['calculatedCredits'])
                &middot; {{ $t('declared_credits') }}: <strong>{{ $board['department']['totalCredits'] }}</strong>
            @endif
        </p>

        @if ($board['warnings'] !== [])
            <details class="spu-board__warnings">
                <summary>{{ $t('warnings') }} ({{ count($board['warnings']) }})</summary>
                <ul>
                    @foreach ($board['warnings'] as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            </details>
        @endif

        <div class="spu-board__scroller" x-data="spuStudyPlanBoard" wire:key="board-scroller-{{ $board['departmentId'] }}">
            <div class="spu-board__inner" x-ref="inner">
                <svg class="spu-board__lines" x-ref="lines" aria-hidden="true"></svg>
                <div class="spu-board__grid" style="grid-template-columns: repeat({{ $termCount }}, 12rem) 9rem;">
                    @foreach ($board['terms'] as $term)
                        <div class="spu-board__term" wire:key="board-term-{{ $board['departmentId'] }}-{{ $term['id'] }}"
                            x-on:dragover="dragOver($event)" x-on:dragleave="dragLeave($event)" x-on:drop="drop($event, '{{ $term['id'] }}')">
                            <header class="spu-board__term-head">
                                <strong>{{ $t('term') }} {{ $term['label'] }}</strong>
                                <small>{{ $term['credits'] }} {{ $creditsShort }}</small>
                                @if ($term['courses'] === [])
                                    <button type="button" class="spu-board__icon" wire:click="removeBoardTerm('{{ $term['id'] }}')" title="{{ $t('remove_term') }}" aria-label="{{ $t('remove_term') }}">&times;</button>
                                @endif
                            </header>

                            @foreach ($term['courses'] as $course)
                                <button type="button" wire:key="board-card-{{ $board['departmentId'] }}-{{ $course['id'] }}"
                                    class="spu-board__card spu-board__card--{{ $course['type'] }} @if (! $course['required']) is-elective @endif @if ($course['warning']) has-warning @endif"
                                    data-board-card data-course-id="{{ $course['id'] }}" data-prereqs='@json($course['prerequisites'])'
                                    draggable="true" wire:click="openBoardCourse('{{ $course['id'] }}')"
                                    x-on:dragstart="dragStart($event, '{{ $course['id'] }}')"
                                    x-on:mouseenter="highlight('{{ $course['id'] }}')" x-on:mouseleave="highlight('')"
                                    x-on:focus="highlight('{{ $course['id'] }}')" x-on:blur="highlight('')">
                                    <span class="spu-board__card-meta"><span>{{ $course['code'] }}</span><span>{{ $course['credits'] }} {{ $creditsShort }}</span></span>
                                    <span class="spu-board__card-title" dir="auto">{{ $course['title'] }}</span>
                                    @if ($course['lessonCount'] > 0 || $course['prerequisiteCredits'] > 0 || $course['warning'])
                                        <span class="spu-board__card-flags">
                                            @if ($course['lessonCount'] > 0)<span title="{{ $t('lectures') }}">PDF {{ $course['lessonCount'] }}</span>@endif
                                            @if ($course['prerequisiteCredits'] > 0)<span>&ge;{{ $course['prerequisiteCredits'] }} {{ $creditsShort }}</span>@endif
                                            @if ($course['warning'])<span class="is-warning" title="{{ $t('warnings') }}">!</span>@endif
                                        </span>
                                    @endif
                                </button>
                            @endforeach

                            <button type="button" class="spu-board__add" wire:click="newBoardCourse('{{ $term['id'] }}')">+ {{ $t('add_course') }}</button>
                        </div>
                    @endforeach

                    <div class="spu-board__term spu-board__term--add">
                        <button type="button" class="spu-board__add" wire:click="addBoardTerm">+ {{ $t('add_term') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if (is_array($this->boardCourse))
        @php($course = $this->boardCourse)
        <div class="spu-board-panel" role="dialog" aria-modal="true" aria-labelledby="spu-board-course-title" wire:key="board-course-panel"
            x-data x-on:keydown.escape.window="$wire.closeBoardPanels()">
            <div class="spu-board-panel__backdrop" wire:click="closeBoardPanels"></div>
            <form class="spu-board-panel__sheet" wire:submit="saveBoardCourse">
                <header class="spu-board-panel__head">
                    <h3 id="spu-board-course-title">{{ $this->boardCourseIsNew ? $t('new_course') : ($course['code'].' - '.($course['titleEn'] ?: $course['titleAr'])) }}</h3>
                    <button type="button" class="spu-board__icon" wire:click="closeBoardPanels" aria-label="{{ $t('close') }}">&times;</button>
                </header>

                <div class="spu-board-panel__body">
                    <div class="spu-board-form">
                        <label>{{ $t('code') }}<input type="text" wire:model="boardCourse.code" maxlength="80" required></label>
                        <label>{{ $t('term_field') }}
                            <select wire:model="boardCourse.termId">
                                @foreach ($board['terms'] as $term)
                                    <option value="{{ $term['id'] }}">{{ $t('term') }} {{ $term['label'] }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>{{ $t('credits') }}<input type="number" step="0.5" min="0" max="40" wire:model="boardCourse.credits"></label>
                        <label>{{ $t('type') }}
                            <select wire:model="boardCourse.type">
                                @foreach (['university', 'faculty', 'specialization'] as $type)
                                    <option value="{{ $type }}">{{ $t('types.'.$type) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>{{ $t('theory') }}<input type="number" step="0.5" min="0" max="40" wire:model="boardCourse.theory"></label>
                        <label>{{ $t('practical') }}<input type="number" step="0.5" min="0" max="40" wire:model="boardCourse.practical"></label>
                        <label class="spu-board-form__check"><input type="checkbox" wire:model="boardCourse.required"> {{ $t('required') }}</label>
                    </div>

                    <fieldset class="spu-board-form">
                        <legend>{{ $t('teachers') }}</legend>
                        @php($teacherChoices = $this->boardTeacherChoices())
                        @php($teacherNames = $teacherChoices['faculty'] + $teacherChoices['other'])
                        @foreach (['theory', 'practical'] as $part)
                            @php($chosenTeachers = array_map('strval', (array) ($this->boardCourse[$part.'TeacherIds'] ?? [])))
                            <div class="spu-board-teachers" wire:key="board-teachers-{{ $part }}">
                                <span class="spu-board-teachers__label">{{ $t($part.'_teachers') }}</span>
                                @if ($chosenTeachers !== [])
                                    <ul class="spu-board-teachers__list">
                                        @foreach ($chosenTeachers as $chosenId)
                                            <li wire:key="board-teacher-{{ $part }}-{{ $chosenId }}">
                                                <span>{{ $teacherNames[(int) $chosenId] ?? '#'.$chosenId }}</span>
                                                <button type="button" wire:click="removeBoardTeacher('{{ $part }}', '{{ $chosenId }}')" aria-label="{{ $t('remove_teacher') }}: {{ $teacherNames[(int) $chosenId] ?? $chosenId }}">&times;</button>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                                <label class="spu-board-teachers__add">
                                    <select wire:change="addBoardTeacher('{{ $part }}', $event.target.value)" aria-label="{{ $t('add_'.$part.'_teacher') }}">
                                        <option value="" selected>{{ $t('add_teacher') }}</option>
                                        <optgroup label="{{ $t('teachers_this_faculty') }}">
                                            @foreach ($teacherChoices['faculty'] as $memberId => $memberName)
                                                @continue(in_array((string) $memberId, $chosenTeachers, true))
                                                <option value="{{ $memberId }}">{{ $memberName }}</option>
                                            @endforeach
                                        </optgroup>
                                        <optgroup label="{{ $t('teachers_other_faculties') }}">
                                            @foreach ($teacherChoices['other'] as $memberId => $memberName)
                                                @continue(in_array((string) $memberId, $chosenTeachers, true))
                                                <option value="{{ $memberId }}">{{ $memberName }}</option>
                                            @endforeach
                                        </optgroup>
                                    </select>
                                </label>
                                <label class="spu-board-form__check"><input type="checkbox" wire:model="boardCourse.{{ $part }}TeacherEnabled"> {{ $t($part.'_teacher_shown') }}</label>
                            </div>
                        @endforeach
                        <small>{{ $t('teachers_help') }}</small>
                    </fieldset>

                    <div class="spu-board-form spu-board-form--stack">
                        <label>{{ $t('title_en') }}<input type="text" dir="ltr" wire:model="boardCourse.titleEn" maxlength="180" required></label>
                        <label>{{ $t('title_ar') }}<input type="text" dir="rtl" wire:model="boardCourse.titleAr" maxlength="180" required></label>
                        <label>{{ $t('description_en') }}<textarea dir="ltr" rows="2" wire:model="boardCourse.descriptionEn"></textarea></label>
                        <label>{{ $t('description_ar') }}<textarea dir="rtl" rows="2" wire:model="boardCourse.descriptionAr"></textarea></label>
                    </div>

                    <fieldset class="spu-board-form spu-board-form--stack">
                        <legend>{{ $t('prerequisites') }}</legend>
                        <input type="search" wire:model.live.debounce.300ms="boardPrerequisiteSearch" placeholder="{{ $t('prerequisites_search') }}" aria-label="{{ $t('prerequisites_search') }}">
                        <div class="spu-board-checks">
                            @foreach ($this->boardPrerequisiteChoices() as $optionId => $optionLabel)
                                <label wire:key="prereq-{{ $optionId }}"><input type="checkbox" value="{{ $optionId }}" wire:model="boardCourse.prerequisites"> {{ $optionLabel }}</label>
                            @endforeach
                        </div>
                        <label>{{ $t('prerequisite_credits') }}
                            <input type="number" min="0" max="400" wire:model="boardCourse.prerequisiteCredits">
                            <small>{{ $t('prerequisite_credits_help') }}</small>
                        </label>
                    </fieldset>

                    <fieldset class="spu-board-form spu-board-form--stack">
                        <legend>{{ $t('lectures') }}</legend>
                        @if ($this->boardCourseIsNew)
                            <p class="spu-board-hint">{{ $t('lectures_save_first') }}</p>
                        @else
                            <p class="spu-board-hint">{{ $t('lectures_help') }}</p>

                            @forelse ($course['lessons'] as $index => $lesson)
                                <div class="spu-board-lecture" wire:key="lecture-{{ $index }}-{{ $lesson['id'] }}">
                                    <input type="text" dir="ltr" wire:model="boardCourse.lessons.{{ $index }}.titleEn" placeholder="{{ $t('lecture_en') }}" aria-label="{{ $t('lecture_en') }}">
                                    <input type="text" dir="rtl" wire:model="boardCourse.lessons.{{ $index }}.titleAr" placeholder="{{ $t('lecture_ar') }}" aria-label="{{ $t('lecture_ar') }}">
                                    <span class="spu-board-lecture__actions">
                                        @if ($lesson['pdfUrl'] !== '')<a href="{{ $lesson['pdfUrl'] }}" target="_blank" rel="noopener">{{ $t('lecture_open') }}</a>@endif
                                        <button type="button" class="spu-board__icon" wire:click="moveBoardLecture({{ $index }}, -1)" title="{{ $t('lecture_up') }}" aria-label="{{ $t('lecture_up') }}">&uarr;</button>
                                        <button type="button" class="spu-board__icon" wire:click="moveBoardLecture({{ $index }}, 1)" title="{{ $t('lecture_down') }}" aria-label="{{ $t('lecture_down') }}">&darr;</button>
                                        <button type="button" class="spu-board__icon is-danger" wire:click="removeBoardLecture({{ $index }})" title="{{ $t('lecture_remove') }}" aria-label="{{ $t('lecture_remove') }}">&times;</button>
                                    </span>
                                </div>
                            @empty
                                <p class="spu-board-hint">{{ $t('none_yet') }}</p>
                            @endforelse

                            <div class="spu-board-upload">
                                <input type="file" multiple accept="application/pdf,.pdf" wire:model="lectureUploads" aria-label="{{ $t('lecture_file') }}">
                                @error('lectureUploads.*')<p class="spu-board-error">{{ $message }}</p>@enderror
                                <button type="button" class="spu-board__btn" wire:click="uploadBoardLectures" wire:loading.attr="disabled" wire:target="lectureUploads,uploadBoardLectures"
                                    @if ($this->lectureUploads === []) disabled @endif>
                                    <span wire:loading.remove wire:target="uploadBoardLectures">{{ $t('upload_lectures') }}@if ($this->lectureUploads !== []) ({{ count($this->lectureUploads) }})@endif</span>
                                    <span wire:loading wire:target="lectureUploads,uploadBoardLectures">{{ $t('uploading') }}</span>
                                </button>
                            </div>
                        @endif
                    </fieldset>

                    <fieldset class="spu-board-form spu-board-form--stack">
                        <legend>{{ $t('specification') }}</legend>
                        @if ($this->boardCourseIsNew)
                            <p class="spu-board-hint">{{ $t('specification_save_first') }}</p>
                        @else
                            <p class="spu-board-hint">{{ $t('specification_help') }}</p>

                            @if (($course['specificationUrl'] ?? '') !== '')
                                <div class="spu-board-lecture" wire:key="specification-{{ md5($course['specificationUrl']) }}">
                                    <span dir="ltr">{{ basename($course['specificationUrl']) }}</span>
                                    <span class="spu-board-lecture__actions">
                                        <a href="{{ $course['specificationUrl'] }}" target="_blank" rel="noopener">{{ $t('lecture_open') }}</a>
                                        <button type="button" class="spu-board__icon is-danger" wire:click="removeBoardSpecification" title="{{ $t('specification_remove') }}" aria-label="{{ $t('specification_remove') }}">&times;</button>
                                    </span>
                                </div>
                            @else
                                <p class="spu-board-hint">{{ $t('specification_none') }}</p>
                            @endif

                            <div class="spu-board-upload">
                                <input type="file" accept="application/pdf,.pdf" wire:model="specificationUpload" aria-label="{{ $t('specification_file') }}">
                                @error('specificationUpload')<p class="spu-board-error">{{ $message }}</p>@enderror
                                <button type="button" class="spu-board__btn" wire:click="uploadBoardSpecification" wire:loading.attr="disabled" wire:target="specificationUpload,uploadBoardSpecification"
                                    @if ($this->specificationUpload === null) disabled @endif>
                                    <span wire:loading.remove wire:target="uploadBoardSpecification">{{ ($course['specificationUrl'] ?? '') !== '' ? $t('replace_specification') : $t('upload_specification') }}</span>
                                    <span wire:loading wire:target="specificationUpload,uploadBoardSpecification">{{ $t('uploading') }}</span>
                                </button>
                            </div>
                        @endif
                    </fieldset>
                </div>

                <footer class="spu-board-panel__foot">
                    @unless ($this->boardCourseIsNew)
                        <button type="button" class="spu-board__btn is-danger" wire:click="deleteBoardCourse" wire:confirm="{{ $t('delete_confirm') }}">{{ $t('delete') }}</button>
                    @endunless
                    <span class="spu-board-panel__spacer"></span>
                    <button type="button" class="spu-board__btn" wire:click="closeBoardPanels">{{ $t('cancel') }}</button>
                    <button type="submit" class="spu-board__btn spu-board__btn--primary" wire:loading.attr="disabled" wire:target="saveBoardCourse">{{ $t('save') }}</button>
                </footer>
            </form>
        </div>
    @endif

    @if (is_array($this->boardDepartmentForm))
        <div class="spu-board-panel" role="dialog" aria-modal="true" aria-labelledby="spu-board-department-title" wire:key="board-department-panel"
            x-data x-on:keydown.escape.window="$wire.closeBoardPanels()">
            <div class="spu-board-panel__backdrop" wire:click="closeBoardPanels"></div>
            <form class="spu-board-panel__sheet spu-board-panel__sheet--narrow" wire:submit="saveBoardDepartment">
                <header class="spu-board-panel__head">
                    <h3 id="spu-board-department-title">{{ $this->boardDepartmentIsNew ? $t('add_department') : $t('department_form') }}</h3>
                    <button type="button" class="spu-board__icon" wire:click="closeBoardPanels" aria-label="{{ $t('close') }}">&times;</button>
                </header>
                <div class="spu-board-panel__body">
                    <div class="spu-board-form spu-board-form--stack">
                        <label>{{ $t('department_name_en') }}<input type="text" dir="ltr" wire:model="boardDepartmentForm.nameEn" maxlength="160" required></label>
                        <label>{{ $t('department_name_ar') }}<input type="text" dir="rtl" wire:model="boardDepartmentForm.nameAr" maxlength="160" required></label>
                        @unless ($this->boardDepartmentIsNew)
                            <label>{{ $t('department_credits') }}
                                <input type="number" step="0.5" min="0" max="400" wire:model="boardDepartmentForm.totalCredits">
                                <small>{{ $t('required_credits') }}: {{ $board['department']['calculatedCredits'] }}
                                    <button type="button" class="spu-board__link" wire:click="useCalculatedBoardCredits">{{ $t('use_calculated') }}</button></small>
                            </label>
                        @endunless
                    </div>

                    @unless ($this->boardDepartmentIsNew)
                        <fieldset class="spu-board-form spu-board-form--stack">
                            <legend>{{ $t('elective_pools') }}</legend>
                            <p class="spu-board-hint">{{ $t('elective_pools_help') }}</p>
                            @foreach (($this->boardDepartmentForm['electivePools'] ?? []) as $index => $pool)
                                <div class="spu-board-row" wire:key="pool-{{ $index }}">
                                    <label>{{ $t('pool_kind') }}
                                        <select wire:model="boardDepartmentForm.electivePools.{{ $index }}.id">
                                            @foreach (['university', 'faculty', 'department'] as $kind)
                                                <option value="{{ $kind }}">{{ $t('pool_kinds.'.$kind) }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label>{{ $t('required_hours') }}<input type="number" min="0" max="200" step="0.5" wire:model="boardDepartmentForm.electivePools.{{ $index }}.requiredHours"></label>
                                    <label>{{ $t('description_en') }}<input type="text" dir="ltr" wire:model="boardDepartmentForm.electivePools.{{ $index }}.descriptionEn"></label>
                                    <label>{{ $t('description_ar') }}<input type="text" dir="rtl" wire:model="boardDepartmentForm.electivePools.{{ $index }}.descriptionAr"></label>
                                    <button type="button" class="spu-board__icon is-danger" wire:click="removeBoardPool({{ $index }})" aria-label="{{ $t('remove') }}" title="{{ $t('remove') }}">&times;</button>
                                </div>
                            @endforeach
                            <button type="button" class="spu-board__add" wire:click="addBoardPool">+ {{ $t('add_pool') }}</button>
                        </fieldset>

                        <fieldset class="spu-board-form spu-board-form--stack">
                            <legend>{{ $t('promotion_rules') }}</legend>
                            <p class="spu-board-hint">{{ $t('promotion_rules_help') }}</p>
                            @foreach (($this->boardDepartmentForm['promotionRequirements'] ?? []) as $index => $rule)
                                <div class="spu-board-row" wire:key="rule-{{ $index }}">
                                    <label>{{ $t('from_year') }}<input type="number" min="0" max="20" wire:model="boardDepartmentForm.promotionRequirements.{{ $index }}.fromYear"></label>
                                    <label>{{ $t('to_year') }}<input type="number" min="0" max="20" wire:model="boardDepartmentForm.promotionRequirements.{{ $index }}.toYear"></label>
                                    <label>{{ $t('required_credits_rule') }}<input type="number" min="0" max="400" wire:model="boardDepartmentForm.promotionRequirements.{{ $index }}.requiredCredits"></label>
                                    <button type="button" class="spu-board__icon is-danger" wire:click="removeBoardPromotion({{ $index }})" aria-label="{{ $t('remove') }}" title="{{ $t('remove') }}">&times;</button>
                                </div>
                            @endforeach
                            <button type="button" class="spu-board__add" wire:click="addBoardPromotion">+ {{ $t('add_rule') }}</button>
                        </fieldset>
                    @endunless
                </div>
                <footer class="spu-board-panel__foot">
                    @unless ($this->boardDepartmentIsNew)
                        @if (count($board['departments']) > 1)
                            <button type="button" class="spu-board__btn is-danger" wire:click="deleteBoardDepartment" wire:confirm="{{ $t('delete_department_confirm') }}">{{ $t('delete_department') }}</button>
                        @endif
                    @endunless
                    <span class="spu-board-panel__spacer"></span>
                    <button type="button" class="spu-board__btn" wire:click="closeBoardPanels">{{ $t('cancel') }}</button>
                    <button type="submit" class="spu-board__btn spu-board__btn--primary">{{ $t('save') }}</button>
                </footer>
            </form>
        </div>
    @endif
</section>
