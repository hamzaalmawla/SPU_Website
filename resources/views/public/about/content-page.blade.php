@extends('layouts.public')

@section('content')
    <div class="bg-slate-50 font-hacen text-spu-blue">
        @if ($page->slug === 'history')
            @php
                $history = [
                    'en' => [
                        'foundingTitle' => 'The Founding Vision',
                        'quote' => 'A university founded to advance academic excellence, professional preparation, and meaningful contribution to society.',
                        'body' => [
                            'Established with a profound commitment to educational innovation, Syrian Private University emerged from a collective vision to elevate the standards of higher education in the region. The founders recognized the critical need for an institution that not only imparted knowledge but also fostered critical thinking, ethical leadership, and practical skills aligned with global standards.',
                            'From its inception, the university was designed to be a beacon of academic rigor, integrating foundational theories with applied practice. This dual approach ensures that graduates are not merely degree holders, but competent professionals ready to engage with and solve the complex challenges of the modern world.',
                        ],
                        'timelineTitle' => 'Institutional Timeline',
                        'timeline' => [
                            ['year' => '2005', 'title' => 'Founding of SPU', 'body' => 'The university officially opened its doors, establishing core faculties and laying the groundwork for a comprehensive academic curriculum.'],
                            ['year' => '2010', 'title' => 'Academic Expansion', 'body' => 'Introduction of new specialized degree programs and the inauguration of state-of-the-art research laboratories.'],
                            ['year' => '2016', 'title' => 'Applied Learning Development', 'body' => 'Strategic shift towards experiential learning, fostering deep industry partnerships and establishing robust internship programs.'],
                            ['year' => '2026', 'title' => 'Digital Transformation', 'body' => 'Looking ahead to full integration of advanced educational technologies and global digital collaborative platforms.'],
                        ],
                        'narratives' => [
                            ['title' => 'Academic Growth', 'eyebrow' => 'Curriculum Expansion', 'body' => 'Over the decades, the academic portfolio has evolved to encompass a diverse range of disciplines, from engineering and medicine to business and the humanities. This growth has been guided by rigorous accreditation standards and a commitment to interdisciplinary studies, ensuring a holistic educational experience.'],
                            ['title' => 'Applied Learning', 'eyebrow' => 'Practical Excellence', 'body' => 'The transition from theoretical instruction to applied methodology marked a significant milestone. Investments in clinical facilities, engineering workshops, and business simulation centers have transformed the campus into a dynamic environment where students actively construct their professional identities before graduation.'],
                            ['title' => 'Community Contribution', 'eyebrow' => 'Social Impact', 'body' => 'Beyond the campus borders, the university has established itself as a vital civic partner. Through free medical clinics, public policy research, and community extension programs, the institution continually reinvests its intellectual capital back into the society it was founded to serve.'],
                        ],
                        'legacyTitle' => 'Continuing the Legacy',
                        'legacyBody' => 'Syrian Private University continues to build on its founding vision by strengthening academic programs, supporting students, advancing applied learning, and contributing to the future of higher education.',
                    ],
                    'ar' => [
                        'foundingTitle' => 'رؤية التأسيس',
                        'quote' => 'جامعة تأسست لتعزيز التميز الأكاديمي، والإعداد المهني، والمساهمة الفاعلة في خدمة المجتمع.',
                        'body' => [
                            'انطلقت الجامعة السورية الخاصة من التزام عميق بتطوير التعليم العالي وتعزيز الابتكار الأكاديمي في المنطقة. وقد أدرك المؤسسون الحاجة إلى مؤسسة لا تكتفي بنقل المعرفة، بل تنمي التفكير النقدي والقيادة الأخلاقية والمهارات العملية المتوافقة مع المعايير العالمية.',
                            'منذ نشأتها، صُممت الجامعة لتكون منارة للرصانة الأكاديمية، تجمع بين النظريات الأساسية والتطبيق العملي، بما يضمن إعداد خريجين قادرين على التعامل مع تحديات العالم الحديث وحلها بكفاءة.',
                        ],
                        'timelineTitle' => 'المسار المؤسسي',
                        'timeline' => [
                            ['year' => '2005', 'title' => 'تأسيس SPU', 'body' => 'افتتحت الجامعة أبوابها رسميا، وأسست كلياتها الأساسية، ووضعت قاعدة لمنهج أكاديمي شامل.'],
                            ['year' => '2010', 'title' => 'التوسع الأكاديمي', 'body' => 'إطلاق برامج اختصاصية جديدة وافتتاح مختبرات بحثية وتعليمية متقدمة.'],
                            ['year' => '2016', 'title' => 'تطوير التعليم التطبيقي', 'body' => 'تحول استراتيجي نحو التعلم الخبروي وبناء شراكات عملية وبرامج تدريب ميداني متينة.'],
                            ['year' => '2026', 'title' => 'التحول الرقمي', 'body' => 'التوجه نحو دمج التقنيات التعليمية المتقدمة ومنصات التعاون الرقمي العالمية.'],
                        ],
                        'narratives' => [
                            ['title' => 'النمو الأكاديمي', 'eyebrow' => 'توسع المناهج', 'body' => 'تطور العرض الأكاديمي عبر السنوات ليشمل طيفا واسعا من الاختصاصات من الهندسة والطب إلى الأعمال والعلوم الإنسانية، ضمن معايير اعتماد صارمة والتزام بالدراسات البينية.'],
                            ['title' => 'التعليم التطبيقي', 'eyebrow' => 'تميز عملي', 'body' => 'شكّل الانتقال من التعليم النظري إلى المنهجية التطبيقية محطة مهمة، عبر الاستثمار في مرافق سريرية وورش هندسية ومراكز محاكاة أعمال تتيح للطلاب بناء هويتهم المهنية قبل التخرج.'],
                            ['title' => 'المساهمة المجتمعية', 'eyebrow' => 'أثر اجتماعي', 'body' => 'خارج حدود الحرم الجامعي، رسخت الجامعة دورها كشريك مدني فاعل من خلال العيادات الطبية والبحث التطبيقي وبرامج خدمة المجتمع.'],
                        ],
                        'legacyTitle' => 'استمرار الإرث',
                        'legacyBody' => 'تواصل الجامعة السورية الخاصة البناء على رؤية تأسيسها من خلال تطوير البرامج الأكاديمية، ودعم الطلاب، وتعزيز التعليم التطبيقي، والمساهمة في مستقبل التعليم العالي.',
                    ],
                ][$locale];
            @endphp

            <section class="history-subpage-hero relative flex items-center justify-center overflow-hidden pt-28 font-hacen">
                <img src="{{ $page->heroImage ?: '/images/about-hero-2.webp' }}" alt="{{ $page->title }}" class="absolute inset-0 h-full w-full object-cover">
                <div class="container relative z-10 mx-auto px-6 text-center text-white">
                    <nav class="mb-6 flex items-center justify-center gap-3 text-xs font-bold text-white/85" aria-label="Breadcrumb">
                        <a href="/{{ $locale }}" class="transition hover:text-white">{{ $locale === 'ar' ? 'الرئيسية' : 'Home' }}</a>
                        <span aria-hidden="true">›</span>
                        <a href="/{{ $locale }}/about" class="transition hover:text-white">{{ $locale === 'ar' ? 'عن الجامعة' : 'About' }}</a>
                        <span aria-hidden="true">›</span>
                        <span>{{ $page->title }}</span>
                    </nav>
                    <h1 class="text-4xl font-black leading-tight text-white md:text-5xl">{{ $page->title }}</h1>
                </div>
            </section>

            <section class="bg-white py-24 font-hacen">
                <div class="container mx-auto">
                    <h2 class="reveal reveal-up mb-16 text-center text-4xl font-black text-spu-blue md:text-5xl">{{ $history['foundingTitle'] }}</h2>
                    <div class="history-vision-grid">
                        <div class="history-vision-image reveal reveal-left">
                            <img src="/images/about-hero-1.webp" alt="{{ $history['foundingTitle'] }}" class="h-full w-full object-cover">
                        </div>
                        <div class="reveal reveal-right">
                            <blockquote class="history-quote mb-12 text-2xl font-black leading-relaxed text-slate-950">{{ $history['quote'] }}</blockquote>
                            <div class="grid gap-6 text-base leading-relaxed text-slate-700">
                                @foreach ($history['body'] as $paragraph)
                                    <p>{{ $paragraph }}</p>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bg-section py-24 font-hacen">
                <div class="container mx-auto px-6">
                    <h2 class="reveal reveal-up mb-16 text-center text-4xl font-black text-spu-blue md:text-5xl">{{ $history['timelineTitle'] }}</h2>
                    <div class="history-timeline">
                        @foreach ($history['timeline'] as $point)
                            <article class="history-timeline-item reveal {{ $loop->odd ? 'reveal-left' : 'reveal-right' }}">
                                <span class="history-timeline-dot" aria-hidden="true"></span>
                                <div class="history-timeline-content">
                                    <p class="mb-3 text-4xl font-black leading-none text-spu-blue/35" translate="no">{{ $point['year'] }}</p>
                                    <h3 class="text-xl font-black leading-tight text-spu-blue">{{ $point['title'] }}</h3>
                                    <p class="mt-5 text-sm leading-relaxed text-slate-700">{{ $point['body'] }}</p>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="bg-white py-16 font-hacen">
                <div class="container mx-auto max-w-6xl px-6">
                    @foreach ($history['narratives'] as $item)
                        <article class="history-row reveal reveal-up">
                            <div>
                                <h2 class="text-xl font-black text-spu-blue">{{ $item['title'] }}</h2>
                                <p class="mt-2 text-xs font-black uppercase tracking-widest text-spu-red">{{ $item['eyebrow'] }}</p>
                            </div>
                            <p class="max-w-3xl text-lg leading-relaxed text-slate-700">{{ $item['body'] }}</p>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="bg-white py-20 font-hacen">
                <div class="container mx-auto px-6">
                    <div class="history-legacy reveal reveal-up mx-auto max-w-4xl">
                        <h2 class="text-3xl font-black text-spu-blue">{{ $history['legacyTitle'] }}</h2>
                        <p class="mt-5 text-base leading-relaxed text-slate-700">{{ $history['legacyBody'] }}</p>
                    </div>
                </div>
            </section>

            @include('public.about.partials.navigation-section', ['locale' => $locale])
        @else
            @include('public.about.partials.hero', ['title' => $page->headline, 'summary' => $page->summary, 'image' => $page->heroImage])

            <section class="bg-white py-20 font-hacen">
                <div class="container mx-auto">
                    <p class="mx-auto mb-12 max-w-3xl text-center text-slate-700">{{ $page->summary }}</p>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                        @foreach ($page->sections as $section)
                            <article class="reveal reveal-up rounded-2xl border border-slate-100 bg-white p-8 text-center shadow-sm">
                                <div class="mx-auto mb-6 flex h-14 w-14 items-center justify-center rounded-full bg-spu-blue/5">
                                    <img src="/images/icon-check-circle-outline.svg" alt="" class="h-7 w-7">
                                </div>
                                <h2 class="mb-3 text-xl font-black text-spu-blue">{{ $section['title'] ?? '' }}</h2>
                                @if (! empty($section['body']))
                                    <p class="text-slate-700">{{ $section['body'] }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="bg-section py-24 font-hacen">
                <div class="container mx-auto">
                    <h2 class="reveal reveal-up mb-12 text-center text-4xl font-black text-spu-blue md:text-5xl">{{ $locale === 'ar' ? 'الأعمدة الاستراتيجية' : 'Strategic Pillars' }}</h2>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                        @foreach ($page->sections as $section)
                            <article class="reveal reveal-up rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
                                <h3 class="mb-3 text-lg font-black text-spu-blue">{{ $section['title'] ?? '' }}</h3>
                                @if (! empty($section['body']))
                                    <p class="text-slate-700">{{ $section['body'] }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif
    </div>
@endsection
