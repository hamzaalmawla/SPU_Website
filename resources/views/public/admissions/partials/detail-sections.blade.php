{{--
    Full regulation text under an admissions tab or page: titled articles whose
    bodies keep their paragraphs and numbered lists.
--}}
@if (! empty($sections))
    <div class="mt-12 space-y-6">
        @foreach ($sections as $detail)
            @if (! empty($detail['title']) || ! empty($detail['body']))
                <article class="rounded-lg border-s-4 border-spu-blue bg-white p-6 shadow-sm">
                    @if (! empty($detail['title']))
                        <h3 class="mb-3 text-lg font-black text-spu-blue">{{ $detail['title'] }}</h3>
                    @endif
                    @if (! empty($detail['body']))
                        <x-public.rich-text class="text-sm leading-7 text-slate-700" :text="$detail['body']" />
                    @endif
                </article>
            @endif
        @endforeach
    </div>
@endif
