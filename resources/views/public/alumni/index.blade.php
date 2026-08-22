@extends('layouts.public')

@section('content')
    @php
        $isAr = $locale === 'ar';
        $filters = $page->filters;
        $options = $page->filterOptions;
        $pagination = $page->pagination;
        $directoryUrl = '/'.$locale.'/alumni';
        $validatedQuery = collect($filters)
            ->except('page')
            ->filter(fn (mixed $value): bool => is_scalar($value) && (string) $value !== '')
            ->map(fn (mixed $value): string => (string) $value)
            ->all();
        $queryUrl = fn (array $query): string => $directoryUrl.($query === [] ? '' : '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986));
        $pageUrl = fn (int $pageNumber): string => $queryUrl([...$validatedQuery, ...($pageNumber > 1 ? ['page' => $pageNumber] : [])]);
    @endphp

    <section class="relative flex min-h-[300px] items-end overflow-hidden pt-28 font-hacen" dir="{{ $direction }}">
        <div class="absolute inset-0">
            <img src="/images/pharmacy-place.jpg" alt="" class="h-full w-full object-cover" aria-hidden="true">
            <div class="absolute inset-0 bg-gradient-to-t from-spu-blue/95 via-spu-blue/70 to-spu-blue/20"></div>
        </div>
        <div class="container relative z-10 pb-14 text-center text-white">
            <nav class="mb-3 flex flex-wrap items-center justify-center gap-2 text-[11px] font-semibold text-white/75" aria-label="{{ $isAr ? 'مسار التنقل' : 'Breadcrumb' }}">
                <a href="/{{ $locale }}" class="transition-colors hover:text-white">{{ $isAr ? 'الرئيسية' : 'Home' }}</a>
                <span aria-hidden="true">/</span>
                <span>{{ $isAr ? 'الخريجون' : 'Alumni' }}</span>
            </nav>
            <h1 class="text-[34px] font-bold leading-tight md:text-[42px]">{{ $isAr ? 'دليل الخريجين' : 'Alumni Directory' }}</h1>
            <p class="mx-auto mt-4 max-w-[760px] text-sm font-semibold leading-7 text-white/82">
                {{ $isAr ? 'استكشف الخريجين المنشورة سجلاتهم حسب الكلية والقسم وسنة التخرج.' : 'Explore published alumni records by faculty, department, and graduation year.' }}
            </p>
        </div>
    </section>

    <section class="bg-white py-12 font-hacen md:py-16" dir="{{ $direction }}">
        <div class="container">
            <form method="GET" action="{{ $directoryUrl }}" class="mb-8 flex flex-wrap items-center gap-4" role="search">
                <label class="sr-only" for="alumni-search">{{ $isAr ? 'البحث في دليل الخريجين' : 'Search alumni directory' }}</label>
                <input id="alumni-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ $isAr ? 'ابحث بالاسم' : 'Search by name' }}" class="h-10 w-full min-w-0 rounded-[6px] border border-slate-200 bg-white px-4 text-[12px] font-semibold text-spu-blue outline-none transition-colors focus:border-spu-blue sm:w-auto sm:min-w-[240px]">

                <label class="sr-only" for="alumni-faculty">{{ $isAr ? 'الكلية' : 'Faculty' }}</label>
                <select id="alumni-faculty" name="faculty" onchange="this.form.submit()" class="h-10 w-full min-w-0 rounded-[6px] border border-slate-200 bg-white px-4 text-[12px] font-semibold text-spu-blue outline-none transition-colors focus:border-spu-blue sm:w-auto sm:min-w-[190px]">
                    <option value="">{{ $isAr ? 'كل الكليات' : 'All faculties' }}</option>
                    @foreach (($options['faculties'] ?? []) as $faculty)
                        <option value="{{ $faculty['value'] }}" @selected(($filters['faculty'] ?? '') === (string) $faculty['value'])>{{ $faculty['label'] }}</option>
                    @endforeach
                </select>

                <label class="sr-only" for="alumni-year">{{ $isAr ? 'سنة التخرج' : 'Graduation year' }}</label>
                <select id="alumni-year" name="year" onchange="this.form.submit()" class="h-10 w-full min-w-0 rounded-[6px] border border-slate-200 bg-white px-4 text-[12px] font-semibold text-spu-blue outline-none transition-colors focus:border-spu-blue sm:w-auto sm:min-w-[160px]">
                    <option value="">{{ $isAr ? 'كل السنوات' : 'All years' }}</option>
                    @foreach (($options['years'] ?? []) as $year)
                        <option value="{{ $year }}" @selected(($filters['year'] ?? '') === (string) $year)>{{ $year }}</option>
                    @endforeach
                </select>

                @if (($options['departments'] ?? []) !== [])
                    <label class="sr-only" for="alumni-department">{{ $isAr ? 'القسم' : 'Department' }}</label>
                    <select id="alumni-department" name="department" onchange="this.form.submit()" class="h-10 w-full min-w-0 rounded-[6px] border border-slate-200 bg-white px-4 text-[12px] font-semibold text-spu-blue outline-none transition-colors focus:border-spu-blue sm:w-auto sm:min-w-[190px]">
                        <option value="">{{ $isAr ? 'كل الأقسام' : 'All departments' }}</option>
                        @foreach ($options['departments'] as $department)
                            <option value="{{ $department['value'] }}" @selected(($filters['department'] ?? '') === (string) $department['value'])>{{ $department['label'] }}</option>
                        @endforeach
                    </select>
                @endif

                <button type="submit" class="inline-flex h-10 items-center justify-center rounded-[6px] bg-spu-red px-5 text-[11px] font-bold uppercase tracking-[0.08em] text-white transition-colors hover:bg-spu-blue">{{ $isAr ? 'بحث' : 'Search' }}</button>
                <a href="{{ $directoryUrl }}" class="inline-flex h-10 items-center justify-center rounded-[6px] border border-slate-200 px-5 text-[11px] font-bold uppercase tracking-[0.08em] text-spu-blue transition-colors hover:border-spu-blue">{{ $isAr ? 'الكل' : 'All' }}</a>
            </form>

            <p class="mb-5 text-[12px] font-semibold text-slate-500">
                {{ $isAr ? 'عرض' : 'Showing' }} {{ $pagination['from'] ?? 0 }}-{{ $pagination['to'] ?? 0 }} {{ $isAr ? 'من' : 'of' }} {{ $pagination['total_items'] ?? 0 }}
            </p>

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @forelse ($page->items as $item)
                    <article class="overflow-hidden border border-slate-200 bg-white shadow-[0_8px_26px_rgba(15,23,42,0.04)] transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_18px_44px_rgba(15,23,42,0.1)]">
                        <div class="h-[210px] overflow-hidden bg-slate-100">
                            @if (! empty($item['image']))
                                <img src="{{ $item['image'] }}" alt="{{ $item['title'] }}" class="h-full w-full object-cover">
                            @else
                                <div class="flex h-full items-center justify-center text-xs font-semibold text-slate-400">{{ $isAr ? 'لا توجد صورة' : 'No image available' }}</div>
                            @endif
                        </div>
                        <div class="p-5 text-center">
                            <h2 class="text-[15px] font-bold text-spu-blue">{{ $item['title'] }}</h2>
                            @if ($item['graduationYear'] !== null)
                                <p class="mt-2 text-[11px] font-bold text-spu-red">{{ $isAr ? 'سنة التخرج: ' : 'Graduation year: ' }}<span dir="ltr">{{ $item['graduationYear'] }}</span></p>
                            @endif
                            <p class="mt-2 text-[11px] font-semibold text-slate-500">{{ $item['faculty'] }}</p>
                            @if ($item['department'])
                                <p class="mt-1 text-[11px] font-semibold text-slate-500">{{ $item['department'] }}</p>
                            @endif
                            @if ($item['degree'])
                                <p class="mt-1 text-[11px] font-semibold text-slate-400">{{ $item['degree'] }}</p>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="col-span-full text-sm font-semibold text-slate-500">{{ $isAr ? 'لا توجد سجلات مطابقة.' : 'No matching alumni records are available.' }}</p>
                @endforelse
            </div>

            <x-public.pagination :current-page="$pagination['current_page'] ?? 1" :total-pages="$pagination['total_pages'] ?? 1" :page-url="$pageUrl" :locale="$locale" class="mt-10" />
        </div>
    </section>
@endsection
