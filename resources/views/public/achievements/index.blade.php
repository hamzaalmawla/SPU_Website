@extends('layouts.public')

@section('content')
    <section class="relative overflow-hidden bg-spu-blue py-16 text-white font-hacen">
        <div class="absolute inset-0 opacity-15" style="background-image: radial-gradient(circle at 20% 20%, #fff 0, transparent 28%), radial-gradient(circle at 80% 80%, #d71920 0, transparent 22%)"></div>
        <div class="container relative">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-white/70">{{ __('public.achievements.eyebrow') }}</p>
            <h1 class="mt-3 text-4xl font-black md:text-5xl">{{ __('public.achievements.title') }}</h1>
            <p class="mt-5 max-w-2xl text-base leading-8 text-white/80">{{ __('public.achievements.description') }}</p>
        </div>
    </section>

    <section class="bg-[#f7f8fb] py-14 font-hacen" data-achievement-archive>
        <div class="container">
            <form method="GET" action="/{{ $locale }}/achievements" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" data-achievement-filters>
                <fieldset>
                    <legend class="mb-4 font-bold text-spu-blue">{{ __('public.achievements.filter') }}</legend>
                    <div class="flex flex-wrap gap-3">
                        @foreach ($archive->categories as $category)
                            <label class="cursor-pointer">
                                <input type="checkbox" name="categories[]" value="{{ $category->slug }}" class="peer sr-only" @checked(in_array($category->slug, $archive->selectedCategories, true))>
                                <span class="inline-flex rounded-full border border-slate-300 px-5 py-2 text-sm font-bold text-slate-600 transition hover:border-spu-red peer-checked:border-spu-red peer-checked:bg-spu-red peer-checked:text-white peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-spu-blue">{{ $category->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <noscript><button class="mt-4 rounded-lg bg-spu-blue px-5 py-2 text-sm font-bold text-white" type="submit">{{ __('public.achievements.apply') }}</button></noscript>
            </form>

            <div class="relative mt-10" aria-live="polite" aria-busy="false" data-achievement-results-container>
                <div class="absolute inset-0 z-10 hidden items-start justify-center rounded-2xl bg-white/80 pt-20" data-achievement-loading>
                    <span class="rounded-full bg-spu-blue px-5 py-3 text-sm font-bold text-white">{{ __('public.achievements.loading') }}</span>
                </div>
                @include('public.achievements.partials.results', ['archive' => $archive, 'locale' => $locale])
            </div>
        </div>
    </section>

    <script>
        (() => {
            const root = document.querySelector('[data-achievement-archive]');
            if (!root) return;
            const form = root.querySelector('[data-achievement-filters]');
            const container = root.querySelector('[data-achievement-results-container]');
            const loading = root.querySelector('[data-achievement-loading]');
            let controller;

            const syncFilters = (url) => {
                const selected = new URL(url).searchParams.getAll('categories[]');
                form.querySelectorAll('input[name="categories[]"]').forEach((input) => {
                    input.checked = selected.includes(input.value);
                });
            };

            const load = async (url, updateHistory = true) => {
                controller?.abort();
                controller = new AbortController();
                loading.classList.remove('hidden');
                loading.classList.add('flex');
                container.setAttribute('aria-busy', 'true');
                try {
                    const response = await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}, signal: controller.signal});
                    if (!response.ok) throw new Error('Request failed');
                    container.querySelector('[data-achievement-results]').outerHTML = await response.text();
                    if (updateHistory) window.history.pushState({}, '', url);
                } catch (error) {
                    if (error.name !== 'AbortError') window.location.assign(url);
                } finally {
                    loading.classList.add('hidden');
                    loading.classList.remove('flex');
                    container.setAttribute('aria-busy', 'false');
                }
            };

            form.addEventListener('change', () => load(`${form.action}?${new URLSearchParams(new FormData(form))}`));
            root.addEventListener('click', (event) => {
                const link = event.target.closest('[data-achievement-pagination] a');
                if (!link) return;
                event.preventDefault();
                load(link.href);
            });
            window.addEventListener('popstate', () => {
                syncFilters(window.location.href);
                load(window.location.href, false);
            });
        })();
    </script>
@endsection
