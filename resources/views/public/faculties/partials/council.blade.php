{{-- A faculty's council page: the dean, the other leadership positions, the council members and the department heads. --}}
@php
    $dean = is_array($page->detail['dean'] ?? null) ? $page->detail['dean'] : null;
    $leadership = is_array($page->detail['leadership'] ?? null) ? $page->detail['leadership'] : [];
    $heads = is_array($page->detail['heads'] ?? null) ? $page->detail['heads'] : [];
    $members = $page->items;
    $personIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"></path></svg>';
@endphp

@if ($dean === null && $leadership === [] && $members === [] && $heads === [])
    <section class="fc-section bg-white font-hacen">
        <div class="container">
            <p class="mx-auto max-w-2xl rounded-xl border border-slate-200 bg-slate-50 px-6 py-12 text-center font-bold text-slate-700">{{ $isAr ? 'لم تُنشر قيادة الكلية ومجلسها بعد.' : 'The faculty leadership and council are not published yet.' }}</p>
        </div>
    </section>
@endif

@if ($dean !== null || $leadership !== [])
    <section class="fc-section bg-white font-hacen" aria-labelledby="fc-leadership-title">
        <div class="container max-w-[1160px]">
            <header class="fc-heading">
                <span class="fc-eyebrow">{{ $isAr ? 'قيادة الكلية' : 'Faculty Leadership' }}</span>
                <h2 id="fc-leadership-title" class="fc-title">{{ $faculty['title'] }}</h2>
            </header>

            @if ($dean !== null)
                <article class="fc-dean">
                    <div class="fc-dean__photo">
                        @if (! empty($dean['image']))
                            <img src="{{ $dean['image'] }}" alt="{{ $dean['name'] }}" loading="lazy">
                        @else
                            {!! $personIcon !!}
                        @endif
                    </div>
                    <div class="fc-dean__body">
                        <div class="fc-chips">
                            <span class="fc-chip fc-chip--primary">{{ $dean['title'] }}</span>
                            <span class="fc-chip fc-chip--gold">{{ $isAr ? 'رئيس مجلس الكلية' : 'Council Chair' }}</span>
                        </div>
                        <h3 class="fc-dean__name" dir="auto">{{ $dean['name'] }}</h3>
                        @if (($dean['bio'] ?? '') !== '')
                            <p class="fc-dean__bio" dir="auto">{{ $dean['bio'] }}</p>
                        @endif
                        <a href="{{ $dean['url'] }}" class="fc-dean__link">
                            {{ $isAr ? 'عرض الملف الشخصي' : 'View Profile' }}
                            <img src="/images/icon-arrow-right-outline.svg" alt="" class="h-3 w-3 brightness-0 invert rtl:rotate-180" aria-hidden="true">
                        </a>
                    </div>
                </article>
            @endif

            @if ($leadership !== [])
                <div class="fc-grid">
                    @foreach ($leadership as $person)
                        <a href="{{ $person['url'] }}" class="fc-card">
                            <span class="fc-avatar">
                                @if (! empty($person['image']))<img src="{{ $person['image'] }}" alt="" loading="lazy">@else{!! $personIcon !!}@endif
                            </span>
                            <span class="fc-card__text">
                                <span class="fc-card__name" dir="auto">{{ $person['name'] }}</span>
                                <span class="fc-card__role">{{ implode(' · ', $person['titles']) }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endif

@if ($members !== [] || $heads !== [])
    <section class="fc-section fc-section--tint font-hacen" aria-labelledby="fc-council-title">
        <div class="container max-w-[1160px]">
            <header class="fc-heading">
                <span class="fc-eyebrow">{{ $isAr ? 'مجلس الكلية' : 'Faculty Council' }}</span>
                <h2 id="fc-council-title" class="fc-title">{{ $isAr ? 'أعضاء مجلس الكلية' : 'Faculty Council Members' }}</h2>
            </header>

            @if ($members !== [])
                <div class="fc-group">
                    <h3 class="fc-subtitle">{{ $isAr ? 'أعضاء المجلس' : 'Council Members' }}</h3>
                    <div class="fc-grid">
                        @foreach ($members as $member)
                            <a href="{{ $member['url'] }}" class="fc-card">
                                <span class="fc-avatar fc-avatar--md">
                                    @if (! empty($member['image']))<img src="{{ $member['image'] }}" alt="" loading="lazy">@else{!! $personIcon !!}@endif
                                </span>
                                <span class="fc-card__text">
                                    <span class="fc-card__name" dir="auto">{{ $member['name'] }}</span>
                                    <span class="fc-chips">
                                        @foreach ($member['roles'] as $role)
                                            <span class="fc-chip">{{ $role }}</span>
                                        @endforeach
                                    </span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($heads !== [])
                <div class="fc-group">
                    <h3 class="fc-subtitle">{{ $isAr ? 'رؤساء الأقسام' : 'Heads of Department' }}</h3>
                    <div class="fc-grid fc-grid--heads">
                        @foreach ($heads as $head)
                            <a href="{{ $head['url'] }}" class="fc-card fc-head">
                                <span class="fc-head__department">
                                    <span class="fc-head__type">{{ $head['type'] === 'specialization' ? ($isAr ? 'اختصاص' : 'Specialization') : ($isAr ? 'قسم' : 'Section') }}</span>
                                    <span class="fc-card__name" dir="auto">{{ $head['department'] }}</span>
                                </span>
                                <span class="fc-head__person">
                                    <span class="fc-avatar fc-avatar--sm">
                                        @if (! empty($head['image']))<img src="{{ $head['image'] }}" alt="" loading="lazy">@else{!! $personIcon !!}@endif
                                    </span>
                                    <span class="fc-card__text">
                                        <span class="fc-card__name text-[0.95rem]" dir="auto">{{ $head['name'] }}</span>
                                        <span class="fc-card__muted">{{ $head['title'] }}</span>
                                    </span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>
@endif
