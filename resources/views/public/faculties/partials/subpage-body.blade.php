{{-- The page intro's body text (admin: Page intro › Body), shown above the page's own content. --}}
@if (trim((string) ($subpage['body'] ?? '')) !== '')
    <section class="bg-white pt-14 font-hacen md:pt-20">
        <div class="container">
            <x-public.rich-text class="mx-auto max-w-[860px] text-[15px] leading-8 text-slate-600" :text="$subpage['body']" />
        </div>
    </section>
@endif
