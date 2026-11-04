{{-- A faculty's academic staff, highest rank first: the dean and the other leaders, the department heads, the council and the rest of the staff. Search, group and department narrow the cards. --}}
@php
    $members = $page->items;
    $groupOf = static fn (array $member): string => match ($member['tier'] ?? 'staff') {
        'dean', 'leadership' => 'leadership',
        'head' => 'head',
        'council' => 'council',
        default => 'staff',
    };
    $groupLabels = [
        'leadership' => $isAr ? 'قيادة الكلية' : 'Faculty Leadership',
        'head' => $isAr ? 'رؤساء الأقسام' : 'Heads of Departments',
        'council' => $isAr ? 'أعضاء مجلس الكلية' : 'Council Members',
        'staff' => $isAr ? 'أعضاء الهيئة التدريسية' : 'Academic Staff',
    ];
    $grouped = collect($members)->groupBy($groupOf);
    $groups = array_values(array_filter(array_keys($groupLabels), static fn (string $group): bool => $grouped->has($group)));
    $showHeadings = count($groups) > 1;
    $departments = collect($members)
        ->pluck('department')
        ->filter(static fn (mixed $department): bool => is_string($department) && trim($department) !== '')
        ->unique()
        ->sort()
        ->values()
        ->all();
    $cardIndex = 0;
    $personIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"></path></svg>';
    $searchText = static fn (array $member): string => implode(' ', array_filter([$member['name'] ?? '', $member['position'] ?? '', $member['academicRank'] ?? '', $member['department'] ?? '']));
@endphp

<section class="fm-section font-hacen" x-data="facultyMembers">
    <div class="container max-w-[1200px]">
        @if ($members !== [])
            <div class="fm-toolbar">
                <div class="fm-toolbar__row">
                    <label class="fm-search">
                        <span class="sr-only">{{ $isAr ? 'ابحث عن عضو هيئة أكاديمية' : 'Search faculty members' }}</span>
                        <img src="/images/icon-search-outline.svg" alt="" class="fm-search__icon" aria-hidden="true">
                        <input type="search" x-model="query" placeholder="{{ $isAr ? 'ابحث بالاسم أو المنصب أو القسم' : 'Search by name, position or department' }}" autocomplete="off">
                    </label>

                    @if (count($departments) > 1)
                        <label class="fm-select">
                            <span class="sr-only">{{ $isAr ? 'القسم' : 'Department' }}</span>
                            <select x-model="department">
                                <option value="">{{ $isAr ? 'جميع الأقسام' : 'All departments' }}</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department }}">{{ $department }}</option>
                                @endforeach
                            </select>
                            <svg viewBox="0 0 20 20" class="fm-select__chevron" aria-hidden="true"><path d="m5 7 5 5 5-5"></path></svg>
                        </label>
                    @endif
                </div>

                @if ($showHeadings)
                    <div class="fm-chips" role="group" aria-label="{{ $isAr ? 'تصفية حسب الفئة' : 'Filter by group' }}">
                        <button type="button" class="fm-chip" data-group="" x-bind:class="chipClass($el)" x-bind:aria-pressed="chipPressed($el)" x-on:click="selectGroup">
                            {{ $isAr ? 'الكل' : 'All' }}
                            <span class="fm-chip__count" data-group="" x-text="groupCount($el)">{{ count($members) }}</span>
                        </button>
                        @foreach ($groups as $group)
                            <button type="button" class="fm-chip" data-group="{{ $group }}" x-bind:class="chipClass($el)" x-bind:aria-pressed="chipPressed($el)" x-on:click="selectGroup">
                                {{ $groupLabels[$group] }}
                                <span class="fm-chip__count" data-group="{{ $group }}" x-text="groupCount($el)">{{ $grouped->get($group)->count() }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif

                <div class="fm-toolbar__status">
                    <p role="status" x-text="statusText()">{{ $isAr ? count($members).' عضو هيئة أكاديمية' : count($members).' faculty members' }}</p>
                    <button type="button" class="fm-reset" x-show="hasFilters()" x-cloak x-on:click="reset">{{ $isAr ? 'مسح التصفية' : 'Clear filters' }}</button>
                </div>
            </div>

            @foreach ($groups as $group)
                <div class="fm-group" data-group-section="{{ $group }}" x-bind:class="groupClass($el)">
                    @if ($showHeadings)
                        <h2 class="fm-group__title">{{ $groupLabels[$group] }}</h2>
                    @endif

                    <div class="fm-grid">
                        @foreach ($grouped->get($group) as $member)
                            @php $isDean = ($member['tier'] ?? '') === 'dean'; @endphp
                            <a href="{{ $member['profileUrl'] ?? '#' }}"
                               class="fm-card {{ $isDean ? 'fm-card--dean' : '' }} {{ $group !== 'staff' ? 'fm-card--leader' : '' }}"
                               data-member-card
                               data-index="{{ $cardIndex++ }}"
                               data-group="{{ $group }}"
                               data-department="{{ $member['department'] ?? '' }}"
                               data-search="{{ $searchText($member) }}"
                               x-bind:class="cardClass($el)">
                                <span class="fm-card__media">
                                    @if (! empty($member['image']))
                                        <img src="{{ $member['image'] }}" alt="{{ $member['name'] ?? '' }}" loading="lazy">
                                    @else
                                        <span class="fm-card__placeholder">{!! $personIcon !!}</span>
                                    @endif
                                    @if ($isDean)
                                        <span class="fm-card__badge">{{ $isAr ? 'عميد الكلية' : 'Dean' }}</span>
                                    @endif
                                </span>
                                <span class="fm-card__body">
                                    @foreach (($member['positions'] ?? []) as $heldPosition)
                                        <span class="fm-card__role">{{ $heldPosition }}</span>
                                    @endforeach
                                    <span class="fm-card__name" dir="auto">{{ $member['name'] ?? '' }}</span>
                                    @if (($member['academicRank'] ?? '') !== '')
                                        <span class="fm-card__rank"><span>{{ $isAr ? 'الرتبة الأكاديمية:' : 'Academic rank:' }}</span> <span dir="auto">{{ $member['academicRank'] }}</span></span>
                                    @endif
                                    @if (! empty($member['department']))
                                        <span class="fm-card__department">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-6h6v6"/></svg>
                                            {{ $member['department'] }}
                                        </span>
                                    @endif
                                    <span class="fm-card__link">
                                        {{ $isAr ? 'عرض الملف الشخصي' : 'View profile' }}
                                        <img src="/images/icon-arrow-right-outline.svg" alt="" class="rtl:rotate-180" aria-hidden="true">
                                    </span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="fm-empty" x-show="noResults()" x-cloak>
                <p>{{ $isAr ? 'لا توجد نتائج مطابقة لبحثك.' : 'No faculty members match your search.' }}</p>
                <button type="button" class="fm-reset" x-on:click="reset">{{ $isAr ? 'مسح التصفية' : 'Clear filters' }}</button>
            </div>
        @else
            <div class="mx-auto max-w-2xl rounded-xl border border-slate-200 bg-slate-50 px-6 py-12 text-center">
                <p class="font-bold text-slate-700">{{ $isAr ? 'لا يوجد أعضاء هيئة أكاديمية منشورون حالياً.' : 'No published faculty members are available yet.' }}</p>
            </div>
        @endif
    </div>
</section>
