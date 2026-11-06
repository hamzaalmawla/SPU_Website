{{--
    Editor plain text (body, summary, description) with its paragraphs, line
    breaks and numbered or bulleted lists kept. One plain line renders as the
    same <p> the views always printed, so short text looks exactly as before.

    <x-public.rich-text :text="$section['body']" class="mt-4 text-sm leading-7" />
--}}
@props(['text' => ''])
@php
    $blocks = app(\App\Contracts\Shared\PlainTextFormatterInterface::class)->blocks((string) $text);
    $isSingleLine = count($blocks) === 1 && $blocks[0]['type'] === 'paragraph' && count($blocks[0]['lines']) === 1;
@endphp
@if ($blocks === [])
<p {{ $attributes }}></p>
@elseif ($isSingleLine)
<p {{ $attributes }}>{{ $blocks[0]['lines'][0] }}</p>
@else
<div {{ $attributes->class('rich-text') }}>@foreach ($blocks as $block)@if ($block['type'] === 'paragraph')<p>@foreach ($block['lines'] as $line)@if (! $loop->first)<br>@endif{{ $line }}@endforeach</p>@else<{{ $block['type'] === 'ordered' ? 'ol' : 'ul' }} class="rich-text__list" role="list">@foreach ($block['items'] as $item)<li><span class="rich-text__marker">{{ $item['marker'] }}</span><span class="rich-text__item">@foreach ($item['lines'] as $line)@if (! $loop->first)<br>@endif{{ $line }}@endforeach</span></li>@endforeach</{{ $block['type'] === 'ordered' ? 'ol' : 'ul' }}>@endif @endforeach</div>
@endif
