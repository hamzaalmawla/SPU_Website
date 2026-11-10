{{-- The page intro's body text (admin: Page intro › Body), shown below the page's hero. --}}
@if (trim((string) ($subpage['body'] ?? '')) !== '')
    <section class="bg-white {{ $bodyTopClass ?? 'pt-14 md:pt-20' }} font-hacen">
        <div class="container">
            <x-public.rich-text class="mx-auto max-w-[860px] text-[15px] leading-8 text-slate-600" :text="$subpage['body']" />
        </div>
    </section>
@endif
