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

        <section class="spu-nav-panel" aria-labelledby="faculty-nav-heading" style="margin-top:1.5rem">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem">
                <div>
                    <h3 id="faculty-nav-heading" style="font-size:1.125rem;font-weight:600">{{ __('admin.faculty_workspace.navigation.title') }}</h3>
                    <p style="font-size:0.8125rem;color:#6b7280">{{ __('admin.faculty_workspace.navigation.description') }}</p>
                </div>
                <button wire:click="openNavAddModal" type="button" style="display:inline-flex;align-items:center;padding:0.375rem 0.875rem;border-radius:0.375rem;background:#10b981;color:#fff;font-size:0.8125rem;font-weight:500;border:none;cursor:pointer">
                    + {{ __('admin.faculty_workspace.navigation.add_card') }}
                </button>
                @if (!empty($this->navCards))
                    <button wire:click="updateNavTitles" type="button" style="display:inline-flex;align-items:center;padding:0.375rem 0.875rem;border-radius:0.375rem;background:#2563eb;color:#fff;font-size:0.8125rem;font-weight:500;border:none;cursor:pointer;margin-left:0.5rem">
                        &#10003; {{ __('admin.faculty_workspace.actions.save_navigation') }}
                    </button>
                @endif
            </div>

            @if (empty($this->navCards))
                <p style="text-align:center;padding:2rem;color:#6b7280;background:#f9fafb;border-radius:0.5rem">
                    {{ __('admin.faculty_workspace.navigation.no_cards') }}
                </p>
            @else
                <div style="overflow-x:auto;border:1px solid #e5e7eb;border-radius:0.5rem">
                    <table style="width:100%;font-size:0.875rem;border-collapse:collapse">
                        <thead style="background:#f9fafb">
                            <tr>
                                <th style="text-align:left;padding:0.625rem;font-weight:500;color:#6b7280">{{ __('admin.faculty_workspace.navigation.subpage') }}</th>
                                <th style="text-align:left;padding:0.625rem;font-weight:500;color:#6b7280">{{ __('admin.faculty_workspace.navigation.visible') }}</th>
                                <th style="text-align:left;padding:0.625rem;font-weight:500;color:#6b7280">{{ __('admin.faculty_workspace.navigation.title_override_ar') }}</th>
                                <th style="text-align:left;padding:0.625rem;font-weight:500;color:#6b7280">{{ __('admin.faculty_workspace.navigation.title_override_en') }}</th>
                                <th style="text-align:left;padding:0.625rem;font-weight:500;color:#6b7280">{{ __('admin.faculty_workspace.navigation.sort_order') }}</th>
                                <th style="text-align:right;padding:0.625rem"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->navCards as $i => $card)
                                <tr style="border-top:1px solid #e5e7eb">
                                    <td style="padding:0.625rem;font-weight:500">
                                        {{ __('admin.faculty_workspace.subpages.' . str_replace('-', '_', $card['subpage_slug'])) }}
                                        <span style="color:#9ca3af;font-size:0.75rem;margin-left:0.25rem">({{ $card['subpage_slug'] }})</span>
                                    </td>
                                    <td style="padding:0.625rem">
                                        <button
                                            type="button"
                                            wire:click="toggleNavVisibility({{ $card['card_id'] }})"
                                            style="position:relative;display:inline-flex;height:1.5rem;width:2.75rem;flex-shrink:0;cursor:pointer;border-radius:9999px;border:2px solid transparent;transition:background-color 0.2s;{{ $card['is_visible'] ? 'background:#10b981' : 'background:#d1d5db' }}"
                                        >
                                            <span style="display:inline-block;height:1.25rem;width:1.25rem;transform:translateX({{ $card['is_visible'] ? '1.25rem' : '0' }});border-radius:9999px;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,0.1);transition:transform 0.2s"></span>
                                        </button>
                                    </td>
                                    <td style="padding:0.625rem">
                                        <input
                                            type="text"
                                            wire:model.lazy="navCards.{{ $i }}.title_override_ar"
                                            placeholder="{{ __('admin.faculty_workspace.navigation.default_title') }}"
                                            style="width:100%;padding:0.25rem 0.5rem;border:1px solid #d1d5db;border-radius:0.375rem;font-size:0.8125rem"
                                        />
                                    </td>
                                    <td style="padding:0.625rem">
                                        <input
                                            type="text"
                                            wire:model.lazy="navCards.{{ $i }}.title_override_en"
                                            placeholder="{{ __('admin.faculty_workspace.navigation.default_title') }}"
                                            style="width:100%;padding:0.25rem 0.5rem;border:1px solid #d1d5db;border-radius:0.375rem;font-size:0.8125rem"
                                        />
                                    </td>
                                    <td style="padding:0.625rem;color:#6b7280">{{ $card['sort_order'] }}</td>
                                    <td style="padding:0.625rem;text-align:right;white-space:nowrap">
                                        <button type="button" wire:click="moveNavUp({{ $card['card_id'] }})" title="{{ __('admin.faculty_workspace.navigation.move_up') }}" style="color:#9ca3af;padding:0.25rem">&uparrow;</button>
                                        <button type="button" wire:click="moveNavDown({{ $card['card_id'] }})" title="{{ __('admin.faculty_workspace.navigation.move_down') }}" style="color:#9ca3af;padding:0.25rem">&downarrow;</button>
                                        <button type="button" wire:click="deleteNavCard({{ $card['card_id'] }})" title="{{ __('admin.faculty_workspace.navigation.delete_card') }}" style="color:#dc2626;padding:0.25rem">&times;</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        @if ($this->navAddModal)
            <div style="position:fixed;inset:0;z-index:50;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.5)" wire:click.self="closeNavAddModal">
                <div style="background:#fff;border-radius:0.75rem;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);max-width:32rem;width:100%;margin:0 1rem;padding:1.5rem">
                    <h3 style="font-size:1.125rem;font-weight:600;margin-bottom:1rem">{{ __('admin.faculty_workspace.navigation.add_card') }}</h3>

                    <div style="margin-bottom:1rem">
                        <label style="display:block;font-size:0.875rem;font-weight:500;margin-bottom:0.25rem">{{ __('admin.faculty_workspace.navigation.subpage') }}</label>
                        <select wire:model="navAddForm.subpage_slug" style="width:100%;padding:0.5rem;border:1px solid #d1d5db;border-radius:0.375rem;font-size:0.875rem">
                            <option value="">{{ __('admin.faculty_workspace.navigation.select_subpage') }}</option>
                            @foreach ($this->getNavAvailableSubpages() as $slug => $label)
                                <option value="{{ $slug }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div style="margin-bottom:1rem">
                        <label style="display:block;font-size:0.875rem;font-weight:500;margin-bottom:0.25rem">{{ __('admin.faculty_workspace.navigation.title_override_ar') }}</label>
                        <input type="text" wire:model="navAddForm.title_ar" placeholder="{{ __('admin.faculty_workspace.navigation.default_title') }}" style="width:100%;padding:0.5rem;border:1px solid #d1d5db;border-radius:0.375rem;font-size:0.875rem" />
                    </div>

                    <div style="margin-bottom:1.5rem">
                        <label style="display:block;font-size:0.875rem;font-weight:500;margin-bottom:0.25rem">{{ __('admin.faculty_workspace.navigation.title_override_en') }}</label>
                        <input type="text" wire:model="navAddForm.title_en" placeholder="{{ __('admin.faculty_workspace.navigation.default_title') }}" style="width:100%;padding:0.5rem;border:1px solid #d1d5db;border-radius:0.375rem;font-size:0.875rem" />
                    </div>

                    <div style="display:flex;justify-content:flex-end;gap:0.5rem">
                        <button type="button" wire:click="closeNavAddModal" style="padding:0.5rem 1rem;border-radius:0.375rem;background:#f3f4f6;color:#374151;font-weight:500">{{ __('admin.faculty_workspace.navigation.cancel') }}</button>
                        <button type="button" wire:click="addNavCard" style="padding:0.5rem 1rem;border-radius:0.375rem;background:#10b981;color:#fff;font-weight:500">{{ __('admin.faculty_workspace.navigation.add_card') }}</button>
                    </div>
                </div>
            </div>
        @endif
    </x-admin.cms-shell>
</x-filament-panels::page>
