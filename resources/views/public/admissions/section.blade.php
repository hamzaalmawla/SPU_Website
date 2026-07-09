@extends('layouts.public')

@section('content')
    @php
        $section = $page->section;
        $slug = $page->sectionSlug;
        $homeUrl = '/'.$locale;
        $parentUrl = '/'.$locale.'/admissions';
        $homeLabel = $section['breadcrumbHome'] ?? ($locale === 'ar' ? 'الرئيسية' : 'Home');
        $parentLabel = $section['breadcrumbParent'] ?? ($locale === 'ar' ? 'القبول والتسجيل' : 'Admissions');
        $currentLabel = $section['breadcrumbCurrent'] ?? ($section['title'] ?? '');
        $heroImage = $section['heroImage'] ?? '/images/DSC_1015.JPG';
        $title = $section['title'] ?? '';
        $compactHero = ($section['heroHeight'] ?? '') === 'compact';
    @endphp

    @if ($slug === 'requirements')
        @php
            $activeTab = ($section['tabs'] ?? [])[0] ?? [];
        @endphp
        <div class="bg-white font-hacen" dir="{{ $direction }}" data-page-name="admissions-{{ $slug }}" data-page-content x-data="admissionsTabs()" data-active-tab="{{ $activeTab['id'] ?? '' }}">
            @include('public.admissions.partials.hero', compact('homeUrl', 'parentUrl', 'homeLabel', 'parentLabel', 'currentLabel', 'heroImage', 'title', 'compactHero'))

            <section class="bg-white pb-28 pt-11">
                <div class="container mx-auto px-6">
                    <div class="mx-auto flex max-w-[760px] flex-wrap items-center justify-center border-b border-slate-200 text-center text-xs font-black tracking-wide text-slate-700">
                        @foreach (($section['tabs'] ?? []) as $tab)
                            <button type="button" class="px-7 pb-4 transition hover:text-spu-red {{ $loop->first ? 'border-b-2 border-spu-red text-spu-red' : 'border-b-2 border-transparent' }}" data-tab="{{ $tab['id'] ?? '' }}" x-bind:class="underlineButtonClass($el)" aria-selected="{{ $loop->first ? 'true' : 'false' }}" x-bind:aria-selected="isActive($el.dataset.tab)" x-on:click="setActiveTab($event)">{{ $tab['label'] ?? '' }}</button>
                        @endforeach
                    </div>

                    @foreach (($section['tabs'] ?? []) as $tab)
                        <div class="mx-auto mt-8 max-w-[1010px] space-y-16" data-tab-panel="{{ $tab['id'] ?? '' }}" x-show="isActive($el.dataset.tabPanel)">
                            <section class="border-s-2 border-spu-red ps-8 md:ps-10">
                                <div class="mb-8 flex items-center gap-3">
                                    <span class="inline-flex h-6 w-6 items-center justify-center text-spu-red" aria-hidden="true"><svg viewBox="0 0 24 24" class="h-6 w-6 fill-current"><path d="M12 2.5 14.3 5l3.4-.4 1.1 3.2 3 1.7-1.6 3 1.6 3-3 1.7-1.1 3.2-3.4-.4-2.3 2.5L9.7 20l-3.4.4-1.1-3.2-3-1.7 1.6-3-1.6-3 3-1.7 1.1-3.2 3.4.4L12 2.5Zm-1.1 12.9 5.2-5.2-1.4-1.4-3.8 3.8-1.6-1.6-1.4 1.4 3 3Z"></path></svg></span>
                                    <h2 class="text-2xl font-black text-spu-blue md:text-[28px]">{{ $section['eligibilityTitle'] ?? '' }}</h2>
                                </div>
                                <div class="space-y-7">
                                    @foreach (($tab['criteria'] ?? []) as $criterion)
                                        <div class="flex gap-4"><span class="mt-1 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 border-spu-blue" aria-hidden="true"><svg viewBox="0 0 20 20" class="h-3 w-3 fill-none stroke-spu-blue stroke-[2.5]"><path d="m5 10 3 3 7-7"></path></svg></span><div><h3 class="text-base font-black text-slate-900">{{ $criterion['title'] ?? '' }}</h3><p class="mt-1 max-w-[650px] text-sm font-bold leading-6 text-slate-700">{{ $criterion['desc'] ?? '' }}</p></div></div>
                                    @endforeach
                                </div>
                            </section>

                            <section class="border-s-2 border-slate-300 ps-8 md:ps-10">
                                <div class="mb-7 flex items-center gap-3"><span class="inline-flex h-6 w-6 items-center justify-center text-spu-blue" aria-hidden="true"><svg viewBox="0 0 24 24" class="h-6 w-6 fill-none stroke-current stroke-[1.8]"><path d="M3 7.5h6l2 2h10v8.5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7.5Z"></path><path d="M3 7.5V6a2 2 0 0 1 2-2h5l2 2h4"></path></svg></span><h2 class="text-2xl font-black text-spu-blue md:text-[28px]">{{ $section['documentsTitle'] ?? '' }}</h2></div>
                                <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                                    @foreach (($tab['documents'] ?? []) as $documentItem)
                                        <div class="flex items-center justify-between gap-4 px-4 py-4 text-sm text-black md:px-5 {{ ! $loop->last ? 'border-b border-slate-200' : '' }}"><span>{{ $documentItem['name'] ?? '' }}</span><span class="rounded px-2 py-1 text-[10px] font-black {{ ($documentItem['required'] ?? false) ? 'bg-red-50 text-spu-red' : 'bg-slate-100 text-slate-700' }}">{{ ($documentItem['required'] ?? false) ? ($section['requiredLabel'] ?? '') : ($section['optionalLabel'] ?? '') }}</span></div>
                                    @endforeach
                                </div>
                            </section>

                            <section class="border-s-2 border-spu-red ps-8 md:ps-10"><div class="rounded-lg border border-slate-200 bg-white px-5 py-5 md:px-6"><h2 class="border-b border-slate-200 pb-3 text-2xl font-black text-spu-blue md:text-[28px]">{{ $section['readyTitle'] ?? '' }}</h2><div class="mt-4 space-y-4 text-sm text-black">@foreach (($tab['checklist'] ?? []) as $item)<label class="flex items-center gap-3"><input type="checkbox" class="h-4 w-4 rounded border-slate-400 text-spu-red focus:ring-spu-red"><span>{{ $item }}</span></label>@endforeach</div></div></section>
                            <section class="border-s-2 border-slate-300 ps-8 md:ps-10"><div class="flex gap-5"><span class="mt-1 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-spu-red text-sm font-black text-white" aria-hidden="true">i</span><div><h2 class="mb-3 text-2xl font-black text-spu-blue md:text-[28px]">{{ $section['notesTitle'] ?? '' }}</h2><p class="max-w-[900px] text-sm leading-6 text-black">{{ $tab['note'] ?? '' }}</p></div></div></section>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
    @elseif ($slug === 'tuition')
        <div class="bg-white font-hacen" dir="{{ $direction }}" data-page-name="admissions-{{ $slug }}" data-page-content x-data="admissionsTuition()">
            @include('public.admissions.partials.hero', compact('homeUrl', 'parentUrl', 'homeLabel', 'parentLabel', 'currentLabel', 'heroImage', 'title', 'compactHero'))
            <section class="bg-white pb-24 pt-12">
                <div class="container mx-auto max-w-[1060px] px-6">
                    @php
                        $facultyOptions = collect($section['feeRows'] ?? [])->pluck('faculty')->unique()->values();
                        $typeOptions = collect($section['feeRows'] ?? [])->pluck('type')->unique()->values();
                    @endphp
                    <div class="flex flex-col gap-5 md:flex-row">
                        <label class="relative block w-full md:w-[220px]"><span class="sr-only">{{ $section['filters']['facultyLabel'] ?? '' }}</span><select x-model="selectedFaculty" class="h-12 w-full appearance-none rounded-lg border border-slate-200 bg-white px-5 pe-10 text-sm font-black text-slate-600 shadow-[0_8px_22px_rgba(32,39,89,0.08)] outline-none transition focus:border-spu-blue"><option value="">{{ $section['filters']['facultyLabel'] ?? '' }}</option>@foreach ($facultyOptions as $faculty)<option value="{{ $faculty }}">{{ $faculty }}</option>@endforeach</select><span class="pointer-events-none absolute inset-y-0 end-4 flex items-center text-spu-blue" aria-hidden="true"><svg viewBox="0 0 20 20" class="h-4 w-4 fill-none stroke-current stroke-2"><path d="m5 7 5 5 5-5"></path></svg></span></label>
                        <label class="relative block w-full md:w-[220px]"><span class="sr-only">{{ $section['filters']['studentTypeLabel'] ?? '' }}</span><select x-model="selectedType" class="h-12 w-full appearance-none rounded-lg border border-slate-200 bg-white px-5 pe-10 text-sm font-black text-slate-600 shadow-[0_8px_22px_rgba(32,39,89,0.08)] outline-none transition focus:border-spu-blue"><option value="">{{ $section['filters']['studentTypeLabel'] ?? '' }}</option>@foreach ($typeOptions as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</select><span class="pointer-events-none absolute inset-y-0 end-4 flex items-center text-spu-blue" aria-hidden="true"><svg viewBox="0 0 20 20" class="h-4 w-4 fill-none stroke-current stroke-2"><path d="m5 7 5 5 5-5"></path></svg></span></label>
                    </div>

                    <section class="mt-16"><h2 class="mb-9 text-[26px] font-black leading-tight text-spu-blue">{{ $section['overviewTitle'] ?? '' }}</h2><div class="overflow-x-auto rounded-md border border-slate-200 bg-white"><table class="w-full min-w-[880px] border-collapse text-start"><thead class="bg-spu-blue text-white"><tr>@foreach (($section['tableHeaders'] ?? []) as $header)<th class="px-4 py-4 text-start text-sm font-black">{{ $header['label'] ?? '' }}</th>@endforeach</tr></thead><tbody>
                        @foreach (($section['feeRows'] ?? []) as $row)
                            <tr class="border-b border-slate-100 last:border-b-0" data-tuition-row data-faculty="{{ $row['faculty'] ?? '' }}" data-type="{{ $row['type'] ?? '' }}" x-show="rowVisible($el)"><td class="px-4 py-4 text-sm text-slate-900">{{ $row['faculty'] ?? '' }}</td><td class="px-4 py-4 text-sm text-slate-900">{{ $row['type'] ?? '' }}</td><td class="px-4 py-4 text-sm font-black text-spu-blue">{{ $row['tuitionFee'] ?? '' }}</td><td class="px-4 py-4 text-sm font-black text-spu-blue">{{ $row['registrationFee'] ?? '' }}</td><td class="px-4 py-4 text-sm text-slate-900">{{ $row['additionalFees'] ?? '' }}</td><td class="px-4 py-4 text-sm text-slate-700">{{ $row['notes'] ?? '' }}</td></tr>
                        @endforeach
                        <tr x-show="emptyStateVisible()"><td class="px-4 py-6 text-center text-sm text-slate-500" colspan="6">{{ $section['emptyState'] ?? '' }}</td></tr>
                    </tbody></table></div></section>

                    <section class="mt-16"><h2 class="mb-8 text-[26px] font-black leading-tight text-spu-blue">{{ $section['paymentTitle'] ?? '' }}</h2><div class="grid gap-10 md:grid-cols-2">
                        @foreach (($section['methods'] ?? []) as $method)
                            <article class="border-s-4 border-spu-blue ps-6"><div class="mb-4 flex items-center gap-5"><span class="inline-flex h-5 w-5 items-center justify-center text-spu-blue" aria-hidden="true">@if (($method['icon'] ?? '') === 'bank')<svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current stroke-2"><path d="M4 10h16"></path><path d="M5 20h14"></path><path d="M6 10v8"></path><path d="M10 10v8"></path><path d="M14 10v8"></path><path d="M18 10v8"></path><path d="m12 4 8 4H4l8-4Z"></path></svg>@else<svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current stroke-2"><rect x="3" y="6" width="18" height="12" rx="1.5"></rect><path d="M3 10h18"></path></svg>@endif</span><h3 class="text-xl font-black text-spu-blue">{{ $method['title'] ?? '' }}</h3></div><p class="max-w-[420px] text-sm leading-6 text-slate-700">{{ $method['desc'] ?? '' }}</p>@if (! empty($method['details']))<div class="mt-5 space-y-1 ps-5 text-sm leading-6 text-slate-700">@foreach ($method['details'] as $detail)<p><span>{{ $detail['label'] ?? '' }}</span><span>: </span><span>{{ $detail['value'] ?? '' }}</span></p>@endforeach</div>@endif @if (! empty($method['ctaUrl']))<a href="{{ $method['ctaUrl'] }}" class="mt-6 inline-flex h-10 items-center justify-center bg-slate-200 px-5 text-sm font-medium text-slate-700 transition hover:bg-spu-blue hover:text-white">{{ $method['cta'] ?? '' }}</a>@endif</article>
                        @endforeach
                    </div></section>

                    <section class="mt-16 border-s-4 border-spu-red ps-6"><div class="mb-4 flex items-center gap-3"><span class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-spu-red text-xs font-black text-spu-red" aria-hidden="true">i</span><h2 class="text-[26px] font-black leading-tight text-spu-blue">{{ $section['notesTitle'] ?? '' }}</h2></div><div class="space-y-3 text-sm leading-6 text-slate-700">@foreach (($section['notes'] ?? []) as $note)<p>{{ $note }}</p>@endforeach</div></section>
                </div>
            </section>
        </div>
    @elseif ($slug === 'how-to-apply')
        <div class="admissions-design" dir="{{ $direction }}" data-page-name="admissions-{{ $slug }}" data-page-content>
            @include('public.admissions.partials.hero', compact('homeUrl', 'parentUrl', 'homeLabel', 'parentLabel', 'currentLabel', 'heroImage', 'title', 'compactHero'))

            <section class="admissions-overlap-intro" aria-labelledby="admissions-journey-title">
                <h1 id="admissions-journey-title">{{ $section['heroTitle'] ?? '' }}</h1>
                <p>{{ $section['heroDesc'] ?? '' }}</p>
            </section>

            <section class="admissions-shell admissions-feature-cards" aria-label="Admissions journey highlights">
                @foreach (($section['featureCards'] ?? []) as $card)
                    <article class="admissions-feature-card">
                        <div><h3>{{ $card['title'] ?? '' }}</h3></div>
                        <span class="admissions-feature-card__icon" aria-hidden="true">
                            @if (($card['icon'] ?? '') === 'steps')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><path d="M6 5v14"></path><path d="M18 5v14"></path><circle cx="6" cy="7" r="2"></circle><circle cx="18" cy="17" r="2"></circle><path d="M10 7h4"></path><path d="M10 17h4"></path></svg>
                            @elseif (($card['icon'] ?? '') === 'apply')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><path d="M5 13a7 7 0 0 1 14 0"></path><path d="M3 13h4v5H5a2 2 0 0 1-2-2z"></path><path d="M21 13h-4v5h2a2 2 0 0 0 2-2z"></path><path d="M12 6v4"></path></svg>
                            @else
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><path d="M7 3h7l5 5v13H7z"></path><path d="M14 3v6h5"></path><path d="M10 13h6"></path><path d="M10 17h6"></path></svg>
                            @endif
                        </span>
                        <p>{{ $card['desc'] ?? '' }}</p>
                    </article>
                @endforeach
            </section>

            <section class="admissions-shell admissions-journey" aria-labelledby="step-by-step-title">
                <h2 id="step-by-step-title" class="admissions-page-title">{{ $section['guideTitle'] ?? '' }}</h2>
                <div class="admissions-timeline">
                    @foreach (($section['steps'] ?? []) as $step)
                        <article class="admissions-timeline-step {{ $loop->last ? 'admissions-timeline-step--final' : '' }}">
                            <span class="admissions-timeline-marker">{{ $step['number'] ?? '' }}</span>
                            <div class="admissions-step-card">
                                <h3>{{ $step['title'] ?? '' }}</h3>
                                <p>{{ $step['desc'] ?? '' }}</p>
                                <a class="admissions-step-card__button" href="{{ $step['href'] ?? '#' }}">{{ $step['cta'] ?? '' }}</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        </div>
    @elseif ($slug === 'faq')
        <div class="admissions-design" dir="{{ $direction }}" data-page-name="admissions-{{ $slug }}" data-page-content x-data="admissionsFaq()">
            @include('public.admissions.partials.hero', compact('homeUrl', 'parentUrl', 'homeLabel', 'parentLabel', 'currentLabel', 'heroImage', 'title', 'compactHero'))

            <section class="admissions-search-band">
                <div class="admissions-shell">
                    <label class="admissions-search">
                        <span class="sr-only">{{ $section['searchLabel'] ?? ($locale === 'ar' ? 'ابحث في أسئلة القبول' : 'Search admissions questions') }}</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
                        <input type="search" x-model="search" placeholder="{{ $section['searchPlaceholder'] ?? '' }}">
                    </label>
                </div>
            </section>

            <section class="admissions-faq-content">
                <div class="admissions-shell">
                    @foreach (($section['sections'] ?? []) as $group)
                        @php
                            $categoryId = $group['id'] ?? str($group['title'] ?? 'category-'.$loop->iteration)->slug()->toString();
                        @endphp
                        <section class="admissions-faq-section" data-faq-category data-category="{{ $categoryId }}" x-show="categoryVisible($el)">
                            <div class="admissions-section-heading {{ $loop->first ? 'admissions-section-heading--small' : 'admissions-section-heading--large' }}">
                                <span class="admissions-section-heading__icon" aria-hidden="true"><img src="{{ $group['icon'] ?? '/images/icon-file-outline.svg' }}" alt="" class="h-6 w-6"></span>
                                <div class="admissions-section-heading__text"><h2>{{ $group['title'] ?? '' }}</h2><span class="admissions-heading-line"></span></div>
                            </div>
                            <div class="admissions-accordion">
                                @foreach (($group['items'] ?? []) as $item)
                                    @php
                                        $searchText = trim(($item['q'] ?? '').' '.($item['a'] ?? ''));
                                    @endphp
                                    <article class="admissions-accordion__item {{ $loop->parent->first && $loop->first ? 'is-open' : '' }}" data-faq-item data-category="{{ $categoryId }}" data-index="{{ $loop->index }}" data-search="{{ $searchText }}" x-show="itemVisible($el)" x-bind:class="accordionItemClass($el)">
                                        <button type="button" class="admissions-accordion__button" data-category="{{ $categoryId }}" data-index="{{ $loop->index }}" aria-expanded="{{ $loop->parent->first && $loop->first ? 'true' : 'false' }}" x-bind:aria-expanded="isOpen($el)" x-on:click="toggleAccordion($event)">
                                            <span class="admissions-accordion__question">{{ $item['q'] ?? '' }}</span>
                                            <svg class="admissions-accordion__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
                                        </button>
                                        <div class="admissions-accordion__answer" data-category="{{ $categoryId }}" data-index="{{ $loop->index }}" x-show="isOpen($el)"><p>{{ $item['a'] ?? '' }}</p></div>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                    <div class="admissions-empty-state" x-show="emptyStateVisible()">{{ $section['emptyState'] ?? '' }}</div>
                </div>
            </section>
        </div>
    @elseif ($slug === 'calendar')
        <div class="admissions-design" dir="{{ $direction }}" data-page-name="admissions-{{ $slug }}" data-page-content>
            @include('public.admissions.partials.hero', compact('homeUrl', 'parentUrl', 'homeLabel', 'parentLabel', 'currentLabel', 'heroImage', 'title', 'compactHero'))
            <section class="admissions-calendar-page"><div class="admissions-shell">
                <div class="admissions-stat-cards" aria-label="Calendar highlights">
                    @foreach (($section['statCards'] ?? []) as $card)
                        <article class="admissions-stat-card"><h3>{{ $card['title'] ?? '' }}</h3><span class="admissions-stat-card__icon" aria-hidden="true">@if (($card['icon'] ?? '') === 'download')<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><path d="M12 3v12"></path><path d="m7 10 5 5 5-5"></path><path d="M5 20h14"></path></svg>@elseif (($card['icon'] ?? '') === 'key')<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><path d="M5 4h14v17H5z"></path><path d="M8 2v4"></path><path d="M16 2v4"></path><path d="M5 9h14"></path><path d="m8 15 2 2 5-5"></path></svg>@else<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><path d="M5 4h14v17H5z"></path><path d="M8 2v4"></path><path d="M16 2v4"></path><path d="M5 9h14"></path><path d="M8 13h3v3H8z" fill="currentColor" stroke="none"></path></svg>@endif</span><p>{{ $card['desc'] ?? '' }}</p></article>
                    @endforeach
                </div>
                <section class="admissions-deadlines" aria-labelledby="essential-deadlines-title"><h2 id="essential-deadlines-title" class="admissions-page-title">{{ $section['deadlinesTitle'] ?? '' }}</h2><div class="admissions-deadline-grid">
                    @foreach (($section['deadlines'] ?? []) as $deadline)
                        <article class="admissions-deadline-card"><span class="admissions-deadline-card__type">{{ $deadline['type'] ?? '' }}</span><h3>{{ $deadline['title'] ?? '' }}</h3><p>{{ $deadline['date'] ?? '' }}</p></article>
                    @endforeach
                </div></section>
                <section aria-labelledby="detailed-timeline-title"><h2 id="detailed-timeline-title" class="admissions-page-title">{{ $section['timelineTitle'] ?? '' }}</h2><div class="admissions-calendar-timeline-card">
                    @foreach (($section['semesters'] ?? []) as $semester)
                        <section class="admissions-semester"><h3>{{ $semester['title'] ?? '' }}</h3><div class="admissions-event-list">
                            @foreach (($semester['events'] ?? []) as $event)
                                <article class="admissions-event-row"><div class="admissions-event-date">{{ $event['date'] ?? '' }}</div><div><h4>{{ $event['title'] ?? '' }}</h4><p>{{ $event['desc'] ?? '' }}</p></div></article>
                            @endforeach
                        </div></section>
                    @endforeach
                </div></section>
                <article class="admissions-download-card"><span class="admissions-download-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2h9l5 5v20H6z"></path><path d="M15 2v6h5"></path><rect x="8" y="12" width="9" height="7" rx="1"></rect><path d="M10 14h2.5a1.5 1.5 0 0 1 0 3H10v-3Z"></path></svg></span><div><h3>{{ $section['download']['title'] ?? '' }}</h3><p>{{ $section['download']['desc'] ?? '' }}</p></div><a class="admissions-download-button" href="{{ $section['download']['href'] ?? '#' }}" download><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3v12"></path><path d="m7 10 5 5 5-5"></path><path d="M5 20h14"></path></svg><span>{{ $section['download']['button'] ?? '' }}</span></a></article>
                <article class="admissions-notice-card"><span class="admissions-notice-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"></circle><path d="M12 8h.01"></path><path d="M11 12h1v5h1"></path></svg></span><div><h3>{{ $section['notice']['title'] ?? '' }}</h3><p>{{ $section['notice']['desc'] ?? '' }}</p></div></article>
            </div></section>
        </div>
    @elseif ($slug === 'documents')
        @php
            $checklistTab = collect($section['tabs'] ?? [])->firstWhere('id', 'checklist') ?? [];
            $firstSubTab = ($checklistTab['subTabs'] ?? [])[0] ?? [];
        @endphp
        <div class="bg-white font-hacen" dir="{{ $direction }}" data-page-name="admissions-{{ $slug }}" data-page-content x-data="admissionsDocuments()" data-active-tab="checklist" data-active-sub-tab="{{ $firstSubTab['id'] ?? 'freshman' }}">
            <section class="bg-white">
                <div class="relative flex h-[370px] items-center justify-center overflow-hidden lg:h-[400px]">
                    <img src="{{ $heroImage }}" alt="{{ $title }}" class="absolute inset-0 h-full w-full object-cover">
                    <div class="absolute inset-0 bg-[linear-gradient(180deg,rgba(32,39,89,0.80)_0%,rgba(32,39,89,0.50)_50%,rgba(32,39,89,0)_100%)]"></div>
                    <div class="container relative z-10 mx-auto px-6 text-center text-white">
                        <nav class="mb-4 flex items-center justify-center gap-2 text-xs font-black text-white" aria-label="Breadcrumb"><a href="{{ $homeUrl }}" class="rounded bg-spu-blue/35 px-2 py-1 transition hover:bg-spu-blue/55">{{ $homeLabel }}</a><span aria-hidden="true" class="text-white/75">›</span><a href="{{ $parentUrl }}" class="transition hover:text-white/80">{{ $parentLabel }}</a><span aria-hidden="true" class="text-white/75">›</span><span>{{ $currentLabel }}</span></nav>
                        <h1 class="text-[32px] font-black leading-tight text-white md:text-[42px]">{{ $title }}</h1>
                        <div class="mt-7 flex flex-wrap items-center justify-center gap-3"><a href="{{ $section['applyUrl'] ?? '#' }}" class="inline-flex h-10 min-w-[136px] items-center justify-center rounded-md bg-spu-red px-8 text-xs font-black uppercase tracking-wide text-white transition hover:bg-spu-blue">{{ $section['applyLabel'] ?? '' }}</a><a href="{{ $section['requestInfoUrl'] ?? '#' }}" class="inline-flex h-10 min-w-[136px] items-center justify-center rounded-md border border-white bg-white/5 px-8 text-xs font-black text-white transition hover:bg-white hover:text-spu-blue">{{ $section['requestInfoLabel'] ?? '' }}</a></div>
                    </div>
                </div>
            </section>

            <section class="border-b border-slate-200 bg-white"><div class="container mx-auto px-6"><div class="mx-auto flex max-w-[900px] items-center justify-center overflow-x-auto text-center text-xs font-black tracking-wide text-slate-700">
                @foreach (($section['tabs'] ?? []) as $tab)
                    <button type="button" class="whitespace-nowrap px-5 pb-4 pt-5 transition hover:text-spu-red md:px-8 {{ $loop->first ? 'border-b-2 border-spu-red text-spu-red' : 'border-b-2 border-transparent' }}" data-tab="{{ $tab['id'] ?? '' }}" x-bind:class="tabButtonClass($el)" aria-selected="{{ $loop->first ? 'true' : 'false' }}" x-bind:aria-selected="isTab($el.dataset.tab)" x-on:click="setTab($event)">{{ $tab['label'] ?? '' }}</button>
                @endforeach
            </div></div></section>

            @foreach (($section['tabs'] ?? []) as $tab)
                @if (($tab['id'] ?? '') === 'checklist')
                    <section class="bg-white py-16 md:py-20" data-documents-panel="checklist" x-show="isTab('checklist')"><div class="container mx-auto px-6">
                        <div class="mx-auto mb-10 flex max-w-[760px] flex-wrap items-center justify-center gap-2">
                            @foreach (($tab['subTabs'] ?? []) as $sub)
                                <button type="button" class="rounded-md px-5 py-2.5 text-xs font-black transition {{ $loop->first ? 'bg-spu-blue text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}" data-sub-tab="{{ $sub['id'] ?? '' }}" x-bind:class="subTabButtonClass($el)" x-on:click="setSubTab($event)">{{ $sub['label'] ?? '' }}</button>
                            @endforeach
                        </div>
                        @foreach (($tab['subTabs'] ?? []) as $sub)
                            <div class="mx-auto max-w-[1010px]" data-sub-tab-panel="{{ $sub['id'] ?? '' }}" x-show="isSubTab('{{ $sub['id'] ?? '' }}')">
                                <p class="mb-8 text-center text-sm font-bold leading-7 text-slate-700">{{ $sub['desc'] ?? '' }}</p>
                                <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                                    @foreach (($sub['items'] ?? []) as $item)
                                        <div class="flex flex-col gap-2 px-5 py-4 md:flex-row md:items-center md:justify-between md:gap-4 {{ ! $loop->last ? 'border-b border-slate-200' : '' }}"><div class="flex items-start gap-3"><span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 {{ ($item['required'] ?? false) ? 'border-spu-red text-spu-red' : 'border-spu-blue text-spu-blue' }}" aria-hidden="true"><svg viewBox="0 0 20 20" class="h-3 w-3 fill-none stroke-current stroke-[2.5]"><path d="m5 10 3 3 7-7"></path></svg></span><div><span class="text-sm font-black text-slate-900">{{ $item['name'] ?? '' }}</span><p class="mt-0.5 text-xs text-slate-500">{{ $item['note'] ?? '' }}</p></div></div><span class="self-start rounded px-2.5 py-1 text-[10px] font-black md:self-center {{ ($item['required'] ?? false) ? 'bg-red-50 text-spu-red' : 'bg-slate-100 text-slate-600' }}">{{ ($item['required'] ?? false) ? ($section['requiredLabel'] ?? '') : ($section['optionalLabel'] ?? '') }}</span></div>
                                    @endforeach
                                </div>
                                <div class="admissions-download-card mt-10"><div class="admissions-download-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="m7 10 5 5 5-5"></path><path d="M12 15V3"></path></svg></div><div><h3>{{ $section['downloadLabel'] ?? '' }} — {{ $sub['label'] ?? '' }}</h3><p>{{ $sub['download']['size'] ?? '' }}</p></div><a href="{{ $sub['download']['href'] ?? '#' }}" class="admissions-download-button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="m7 10 5 5 5-5"></path><path d="M12 15V3"></path></svg><span>{{ $section['downloadLabel'] ?? '' }}</span></a></div>
                            </div>
                        @endforeach
                    </div></section>
                @elseif (($tab['id'] ?? '') === 'granted')
                    <section class="bg-white py-16 md:py-20" x-show="isTab('granted')"><div class="container mx-auto px-6"><div class="mx-auto max-w-[1010px]"><p class="mb-10 text-center text-sm font-bold leading-7 text-slate-700">{{ $tab['intro'] ?? '' }}</p><div class="cms-grid-cards gap-6">
                        @foreach (($tab['items'] ?? []) as $doc)
                            <article class="flex flex-col rounded-lg border border-slate-200 bg-white p-6 shadow-sm transition hover:shadow-md"><div class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-lg bg-spu-blue/10 text-spu-blue"><svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current stroke-2" aria-hidden="true"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><path d="M14 2v6h6"></path></svg></div><h3 class="mb-2 text-base font-black text-spu-blue">{{ $doc['title'] ?? '' }}</h3><p class="mb-4 flex-1 text-sm leading-6 text-slate-600">{{ $doc['desc'] ?? '' }}</p><span class="inline-block self-start rounded bg-slate-100 px-2.5 py-1 text-[10px] font-black text-slate-600">{{ $doc['availability'] ?? '' }}</span></article>
                        @endforeach
                    </div></div></div></section>
                @elseif (($tab['id'] ?? '') === 'studySystem')
                    <section class="bg-white py-16 md:py-20" x-show="isTab('studySystem')"><div class="container mx-auto px-6"><div class="mx-auto max-w-[1010px]"><p class="mb-10 text-center text-sm font-bold leading-7 text-slate-700">{{ $tab['intro'] ?? '' }}</p><div class="admissions-section-heading admissions-section-heading--large mb-8"><span class="admissions-section-heading__icon" aria-hidden="true"><svg viewBox="0 0 24 24" class="h-6 w-6 fill-none stroke-spu-red stroke-2"><path d="M12 2.5 14.3 5l3.4-.4 1.1 3.2 3 1.7-1.6 3 1.6 3-3 1.7-1.1 3.2-3.4-.4-2.3 2.5L9.7 20l-3.4.4-1.1-3.2-3-1.7 1.6-3-1.6-3 3-1.7 1.1-3.2 3.4.4L12 2.5Z"></path></svg></span><div class="admissions-section-heading__text"><h2>{{ $tab['scaleTitle'] ?? '' }}</h2><span class="admissions-heading-line"></span></div></div><div class="overflow-hidden rounded-lg border border-slate-200"><div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-spu-blue text-xs font-black uppercase tracking-wide text-white"><tr>@foreach (($tab['scaleHeaders'] ?? []) as $header)<th class="px-4 py-3 text-start">{{ $header['label'] ?? '' }}</th>@endforeach</tr></thead><tbody class="divide-y divide-slate-200">@foreach (($tab['scaleRows'] ?? []) as $row)<tr class="bg-white hover:bg-slate-50"><td class="px-4 py-3 font-bold text-slate-900">{{ $row['percentage'] ?? '' }}</td><td class="px-4 py-3 font-black text-spu-blue">{{ $row['gpa'] ?? '' }}</td><td class="px-4 py-3 font-bold text-slate-900">{{ $row['grade'] ?? '' }}</td><td class="px-4 py-3 text-slate-600">{{ $row['descriptor'] ?? '' }}</td></tr>@endforeach</tbody></table></div></div><div class="mt-8 space-y-3">@foreach (($tab['notes'] ?? []) as $note)<div class="flex items-start gap-3"><span class="mt-1 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-spu-red/10 text-spu-red" aria-hidden="true"><svg viewBox="0 0 20 20" class="h-3 w-3 fill-none stroke-current stroke-2"><path d="M10 6v4m0 4h.01M10 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16Z"></path></svg></span><p class="text-sm leading-6 text-slate-700">{{ $note }}</p></div>@endforeach</div></div></div></section>
                @elseif (($tab['id'] ?? '') === 'warnings')
                    <section class="bg-white py-16 md:py-20" x-show="isTab('warnings')"><div class="container mx-auto px-6"><div class="mx-auto max-w-[1010px]"><p class="mb-10 text-center text-sm font-bold leading-7 text-slate-700">{{ $tab['intro'] ?? '' }}</p><div class="admissions-section-heading admissions-section-heading--large mb-8"><span class="admissions-section-heading__icon" aria-hidden="true"><svg viewBox="0 0 24 24" class="h-6 w-6 fill-none stroke-spu-red stroke-2"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"></path><path d="M12 9v4m0 4h.01"></path></svg></span><div class="admissions-section-heading__text"><h2>{{ $tab['levelsTitle'] ?? '' }}</h2><span class="admissions-heading-line"></span></div></div><div class="space-y-6">@foreach (($tab['levels'] ?? []) as $level)<div class="rounded-lg border-s-4 border-spu-red bg-white p-6 shadow-sm"><div class="mb-3 flex flex-wrap items-center gap-3"><h3 class="text-lg font-black text-spu-blue">{{ $level['level'] ?? '' }}</h3><span class="rounded bg-red-50 px-2.5 py-1 text-[10px] font-black text-spu-red">{{ $level['threshold'] ?? '' }}</span></div><div class="grid gap-4 md:grid-cols-2"><div><h4 class="mb-1 text-xs font-black uppercase tracking-wide text-slate-500">{{ $locale === 'ar' ? 'العواقب' : 'Consequences' }}</h4><p class="text-sm leading-6 text-slate-700">{{ $level['consequences'] ?? '' }}</p></div><div><h4 class="mb-1 text-xs font-black uppercase tracking-wide text-slate-500">{{ $locale === 'ar' ? 'طريق التعافي' : 'Recovery Path' }}</h4><p class="text-sm leading-6 text-slate-700">{{ $level['recovery'] ?? '' }}</p></div></div></div>@endforeach</div></div></div></section>
                @endif
            @endforeach

            <section class="bg-slate-50 py-16"><div class="container mx-auto px-6"><div class="mx-auto max-w-[1010px]"><div class="admissions-download-card mb-8"><div class="admissions-download-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="m7 10 5 5 5-5"></path><path d="M12 15V3"></path></svg></div><div><h3>{{ $section['downloadAllLabel'] ?? '' }}</h3><p>{{ $section['downloadAllDesc'] ?? '' }}</p></div><a href="#" class="admissions-download-button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="m7 10 5 5 5-5"></path><path d="M12 15V3"></path></svg><span>{{ $section['downloadLabel'] ?? '' }}</span></a></div><p class="text-center text-xs font-bold text-slate-400"><span>{{ $section['lastReviewedLabel'] ?? '' }}: </span><span>{{ $section['lastReviewed'] ?? '' }}</span></p></div></div></section>
        </div>
    @elseif ($slug === 'transfer')
        @php
            $activeTab = ($section['tabs'] ?? [])[0] ?? [];
        @endphp
        <div class="bg-white font-hacen" dir="{{ $direction }}" data-page-name="admissions-{{ $slug }}" data-page-content x-data="admissionsTabs()" data-active-tab="{{ $activeTab['id'] ?? '' }}">
            @include('public.admissions.partials.hero', compact('homeUrl', 'parentUrl', 'homeLabel', 'parentLabel', 'currentLabel', 'heroImage', 'title', 'compactHero'))
            <section class="bg-white pb-28 pt-20"><div class="container mx-auto max-w-[1060px] px-6">
                <div class="mx-auto flex w-fit max-w-full items-center overflow-hidden rounded-xl border border-slate-200 bg-white p-1 shadow-[0_8px_24px_rgba(32,39,89,0.10)]">
                    @foreach (($section['tabs'] ?? []) as $tab)
                        <button type="button" class="min-w-[180px] rounded-lg px-5 py-4 text-base font-black transition {{ $loop->first ? 'bg-spu-red text-white' : 'text-slate-600 hover:text-spu-red' }}" data-tab="{{ $tab['id'] ?? '' }}" x-bind:class="pillButtonClass($el)" aria-selected="{{ $loop->first ? 'true' : 'false' }}" x-bind:aria-selected="isActive($el.dataset.tab)" x-on:click="setActiveTab($event)">{{ $tab['label'] ?? '' }}</button>
                    @endforeach
                </div>
                @foreach (($section['tabs'] ?? []) as $tab)
                    <div data-tab-panel="{{ $tab['id'] ?? '' }}" x-show="isActive($el.dataset.tabPanel)">
                        <section class="mt-20"><h2 class="text-center text-[32px] font-black leading-tight text-spu-blue">{{ $tab['policiesTitle'] ?? '' }}</h2><div class="mt-5 border-t border-slate-200"></div><div class="cms-grid-wide mt-8 gap-12">
                            @foreach (($tab['policies'] ?? []) as $policy)
                                <article class="border-s-2 border-spu-red ps-5"><div class="mb-4 flex items-center gap-2 text-spu-red"><span class="inline-flex h-4 w-4 items-center justify-center" aria-hidden="true">@if (($policy['icon'] ?? '') === 'transfer')<svg viewBox="0 0 20 20" class="h-4 w-4 fill-none stroke-current stroke-2"><path d="M4 6h10"></path><path d="m11 3 3 3-3 3"></path><path d="M16 14H6"></path><path d="m9 11-3 3 3 3"></path></svg>@else<svg viewBox="0 0 20 20" class="h-4 w-4 fill-none stroke-current stroke-2"><path d="m4 10 3 3 9-9"></path><path d="m14 10 2 2-5 5"></path></svg>@endif</span><h3 class="text-lg font-black">{{ $policy['title'] ?? '' }}</h3></div><p class="max-w-[390px] text-sm leading-6 text-black">{{ $policy['desc'] ?? '' }}</p></article>
                            @endforeach
                        </div></section>
                        <section class="mt-24 border-s-2 border-slate-300 ps-8"><h2 class="mb-8 text-center text-[32px] font-black leading-tight text-spu-blue">{{ $tab['documentsTitle'] ?? '' }}</h2><div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                            @foreach (($tab['documents'] ?? []) as $documentItem)
                                <div class="flex items-center justify-between gap-4 px-4 py-4 text-sm text-black {{ ! $loop->last ? 'border-b border-slate-200' : '' }}"><span>{{ $documentItem['title'] ?? '' }}</span><span class="rounded px-2 py-1 text-[10px] font-black {{ ($documentItem['required'] ?? false) ? 'bg-red-50 text-spu-red' : 'bg-slate-100 text-slate-700' }}">{{ ($documentItem['required'] ?? false) ? ($section['requiredLabel'] ?? '') : ($section['optionalLabel'] ?? '') }}</span></div>
                            @endforeach
                        </div></section>
                        <section class="mt-28"><h2 class="text-center text-[32px] font-black leading-tight text-spu-blue">{{ $tab['processTitle'] ?? '' }}</h2><div class="relative mx-auto mt-16 grid max-w-[900px] gap-14"><div class="absolute bottom-0 left-1/2 top-0 hidden w-px -translate-x-1/2 bg-slate-300 md:block" aria-hidden="true"></div>
                            @foreach (($tab['steps'] ?? []) as $step)
                                <div class="relative grid gap-8 md:grid-cols-2 md:items-center"><div class="hidden md:block {{ $loop->odd ? 'order-1' : 'order-2' }}"></div><span class="absolute left-1/2 top-10 z-10 hidden h-6 w-6 -translate-x-1/2 rounded-full border-4 border-white md:block {{ $loop->first ? 'bg-spu-blue' : 'bg-slate-300' }}" aria-hidden="true"></span><article class="rounded-lg border border-slate-200 bg-white px-8 py-8 {{ $loop->odd ? 'md:order-2 md:ms-16' : 'md:order-1 md:me-16' }}"><h3 class="text-xl font-black text-spu-blue">{{ $step['title'] ?? '' }}</h3><p class="mt-4 text-xs leading-6 text-black">{{ $step['desc'] ?? '' }}</p></article></div>
                            @endforeach
                        </div></section>
                    </div>
                @endforeach
                <section class="mt-20 border-s-4 border-spu-red ps-6"><div class="flex gap-5"><span class="mt-1 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-spu-red text-sm font-black text-white" aria-hidden="true">i</span><div><h2 class="mb-3 text-xl font-black text-spu-blue">{{ $section['notesTitle'] ?? '' }}</h2><p class="max-w-[900px] text-sm leading-5 text-black">{{ $section['notesDesc'] ?? '' }}</p></div></div></section>
            </div></section>
        </div>
    @else
        <div class="bg-white font-hacen" dir="{{ $direction }}" data-page-name="admissions-{{ $slug }}" data-page-content>
            @include('public.admissions.partials.hero', compact('homeUrl', 'parentUrl', 'homeLabel', 'parentLabel', 'currentLabel', 'heroImage', 'title', 'compactHero'))

            <section class="bg-white py-16 md:py-20">
                <div class="container mx-auto max-w-5xl px-6">
                    @if (! empty($section['intro']))
                        <div class="mx-auto mb-12 max-w-3xl text-center text-base font-bold leading-8 text-slate-700">
                            @if (is_array($section['intro']))
                                @foreach ($section['intro'] as $paragraph)
                                    <p class="mb-4 last:mb-0">{{ $paragraph }}</p>
                                @endforeach
                            @else
                                <p>{{ $section['intro'] }}</p>
                            @endif
                        </div>
                    @endif

                    @if (! empty($section['cards']))
                        <h2 class="mb-8 text-center text-3xl font-black text-spu-blue md:text-4xl">{{ $section['cardsTitle'] ?? $title }}</h2>
                        <div class="cms-grid-cards gap-6">
                            @foreach ($section['cards'] as $card)
                                <article class="rounded-2xl border border-slate-100 bg-white p-7 shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                                    <h3 class="mb-3 text-xl font-black text-spu-blue">{{ $card['title'] ?? '' }}</h3>
                                    <p class="text-sm font-bold leading-7 text-slate-700">{{ $card['body'] ?? '' }}</p>
                                </article>
                            @endforeach
                        </div>
                    @endif

                    @if (! empty($section['steps']))
                        <h2 class="mb-10 text-center text-3xl font-black text-spu-blue md:text-4xl">{{ $section['stepsTitle'] ?? $title }}</h2>
                        <div class="space-y-6">
                            @foreach ($section['steps'] as $step)
                                <article class="rounded-2xl border border-slate-100 bg-white p-7 shadow-sm">
                                    <div class="mb-3 flex items-center gap-4">
                                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-spu-red text-sm font-black text-white">{{ $loop->iteration }}</span>
                                        <h3 class="text-xl font-black text-spu-blue">{{ $step['title'] ?? '' }}</h3>
                                    </div>
                                    <p class="text-sm font-bold leading-7 text-slate-700">{{ $step['body'] ?? '' }}</p>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        </div>
    @endif
@endsection
