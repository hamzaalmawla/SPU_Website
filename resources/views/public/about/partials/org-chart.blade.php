{{-- The university's organisational chart: apex chain, the president's offices, then one column per branch. Boxes with a page are links. --}}
@php
    $chart = $page->chart;
    $isAr = $locale === 'ar';
    $box = static function (array $node, string $class, string $linkClass = '') {
        $title = e($node['title']);
        $arrow = '<span aria-hidden="true" class="org-chart__arrow">↗</span>';

        return ($node['url'] ?? '') !== ''
            ? '<a href="'.e($node['url']).'" class="'.$class.' '.$linkClass.'">'.$title.$arrow.'</a>'
            : '<span class="'.$class.'">'.$title.'</span>';
    };
@endphp

<section class="org-chart bg-slate-50 py-16 font-hacen md:py-20" aria-label="{{ $isAr ? 'الهيكل التنظيمي' : 'Organisational chart' }}">
    <div class="container mx-auto px-4">
        <div class="flex flex-col items-center">
            @foreach ($chart['apex'] as $node)
                @if (! $loop->first)
                    <span class="org-chart__stem h-7" aria-hidden="true"></span>
                @endif
                {!! $box($node, $loop->last ? 'org-chart__apex org-chart__apex--lead' : 'org-chart__apex', 'org-chart__link') !!}
            @endforeach

            @if ($chart['offices'] !== [])
                <span class="org-chart__stem h-7" aria-hidden="true"></span>
                <ul class="flex max-w-5xl flex-wrap justify-center gap-3" role="list">
                    @foreach ($chart['offices'] as $node)
                        <li>{!! $box($node, 'org-chart__office', 'org-chart__link') !!}</li>
                    @endforeach
                </ul>
            @endif

            @if ($chart['branches'] !== [])
                <span class="org-chart__stem h-8" aria-hidden="true"></span>
            @endif
        </div>

        @if ($chart['branches'] !== [])
            <ol class="org-chart__branches grid gap-6 sm:grid-cols-2 xl:grid-cols-5" role="list">
                @foreach ($chart['branches'] as $branch)
                    <li class="org-chart__branch flex flex-col">
                        {!! $box($branch, 'org-chart__head', 'org-chart__link') !!}
                        @if ($branch['note'] !== '')
                            {!! $box(['title' => $branch['note'], 'url' => $branch['noteUrl']], 'org-chart__note', 'org-chart__link') !!}
                        @endif
                        <ul class="mt-4 grid gap-2" role="list">
                            @foreach ($branch['units'] as $unit)
                                <li>
                                    {!! $box($unit, 'org-chart__unit', 'org-chart__link') !!}
                                    @if ($unit['children'] !== [])
                                        <ul class="org-chart__children mt-2 grid gap-2" role="list">
                                            @foreach ($unit['children'] as $child)
                                                <li>{!! $box($child, 'org-chart__unit org-chart__unit--child', 'org-chart__link') !!}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</section>
