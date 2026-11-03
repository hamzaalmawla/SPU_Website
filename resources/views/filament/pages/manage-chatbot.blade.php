@php
    /** @var \App\DTOs\Chatbot\ChatbotSettingsDTO $settings */
    /** @var \App\DTOs\Chatbot\ChatbotKnowledgeStatsDTO $stats */
    /** @var \App\DTOs\Chatbot\ChatbotUsageSummaryDTO $usage */
    /** @var \Illuminate\Support\Collection<int, \App\DTOs\Chatbot\ChatbotDocumentDTO> $documents */
    $live = $reasons === [];
    $number = static fn (int|float $value): string => number_format($value);
    $money = static fn (float $value): string => '$'.number_format($value, $value < 1 ? 4 : 2);
    $size = static function (int $bytes): string {
        return $bytes >= 1048576 ? number_format($bytes / 1048576, 1).' MB' : max(1, (int) round($bytes / 1024)).' KB';
    };
    $maxDay = max(1, ...array_map(static fn (array $day): int => $day['questions'], $usage->days));
@endphp

<x-filament-panels::page>
    <div class="spu-assistant">
        <section @class(['spu-assistant__status', 'is-live' => $live])>
            <span class="spu-assistant__status-dot" aria-hidden="true"></span>
            <div>
                <p class="spu-assistant__status-title">{{ $live ? __('admin_chatbot.live') : __('admin_chatbot.not_live') }}</p>
                @unless ($live)
                    <ul class="spu-assistant__reasons">
                        @foreach ($reasons as $reason)
                            <li>{{ $reason }}</li>
                        @endforeach
                    </ul>
                @endunless
                <p class="spu-assistant__status-meta">{{ __('admin_chatbot.stats.model', ['model' => $modelLabel]) }}</p>
            </div>
        </section>

        <div class="spu-assistant__stats">
            <div class="spu-assistant__stat">
                <span class="spu-assistant__stat-label">{{ __('admin_chatbot.stats.files') }}</span>
                <span class="spu-assistant__stat-value">{{ $number($stats->documents) }}</span>
                <span class="spu-assistant__stat-hint">{{ __('admin_chatbot.stats.files_hint', ['searchable' => $number($stats->searchableDocuments), 'total' => $number($stats->documents)]) }}</span>
                <span class="spu-assistant__stat-hint">{{ __('admin_chatbot.stats.website_hint', ['pages' => $number($stats->websitePages)]) }}</span>
            </div>
            <div class="spu-assistant__stat">
                <span class="spu-assistant__stat-label">{{ __('admin_chatbot.stats.passages') }}</span>
                <span class="spu-assistant__stat-value">{{ $number($stats->passages) }}</span>
                <span class="spu-assistant__stat-hint">{{ __('admin_chatbot.stats.passages_hint', ['characters' => $number($stats->characters)]) }}</span>
            </div>
            <div class="spu-assistant__stat">
                <span class="spu-assistant__stat-label">{{ __('admin_chatbot.stats.questions_today') }}</span>
                <span class="spu-assistant__stat-value">{{ $number($usage->questionsToday) }}</span>
                <span class="spu-assistant__stat-hint">{{ __('admin_chatbot.stats.questions_hint', ['cached' => $number($usage->cacheHitsToday), 'unanswered' => $number($usage->withoutSourcesToday), 'failures' => $number($usage->failuresToday)]) }}</span>
                <span class="spu-assistant__stat-hint">{{ __('admin_chatbot.stats.blocked_hint', ['blocked' => $number($usage->blockedToday)]) }}</span>
                <span class="spu-assistant__stat-hint">{{ __('admin_chatbot.stats.fair_use_hint', ['limited' => $number($usage->rateLimitedToday), 'paused' => $number($usage->pausedToday)]) }}</span>
            </div>
            <div class="spu-assistant__stat">
                <span class="spu-assistant__stat-label">{{ __('admin_chatbot.stats.cost_today') }}</span>
                <span class="spu-assistant__stat-value" dir="ltr">{{ $money($usage->costTodayUsd) }}</span>
                <span class="spu-assistant__stat-hint">{{ __('admin_chatbot.stats.cost_30') }}: <bdi dir="ltr">{{ $money($usage->cost30DaysUsd) }}</bdi></span>
                <span class="spu-assistant__stat-hint">{{ __('admin_chatbot.stats.cost_30_hint', ['questions' => $number($usage->questions30Days), 'tokens' => $number($usage->tokens30Days)]) }}</span>
            </div>
        </div>

        <section class="spu-assistant__card">
            <h2 class="spu-assistant__card-title">{{ __('admin_chatbot.usage_chart') }}</h2>
            @if ($usage->questions30Days === 0)
                <p class="spu-assistant__muted">{{ __('admin_chatbot.usage_chart_empty') }}</p>
            @else
                <ol class="spu-assistant__chart" dir="ltr">
                    @foreach ($usage->days as $day)
                        <li class="spu-assistant__bar" title="{{ $day['day'] }}: {{ $day['questions'] }} · ${{ number_format($day['costUsd'], 4) }}">
                            <span class="spu-assistant__bar-value">{{ $day['questions'] > 0 ? $day['questions'] : '' }}</span>
                            <span class="spu-assistant__bar-fill" style="height: {{ max(2, (int) round($day['questions'] / $maxDay * 100)) }}%"></span>
                            <span class="spu-assistant__bar-label">{{ \Illuminate\Support\Carbon::parse($day['day'])->format('d/m') }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

        <section class="spu-assistant__card spu-assistant__card--flush">
            @if ($documents->isEmpty())
                <div class="spu-assistant__empty">
                    <x-filament::icon icon="heroicon-o-document-arrow-up" class="spu-assistant__empty-icon" />
                    <p class="spu-assistant__empty-title">{{ __('admin_chatbot.table.empty_title') }}</p>
                    <p class="spu-assistant__muted">{{ __('admin_chatbot.table.empty_body') }}</p>
                    @if ($canUpdate)
                        {{ $this->uploadDocumentsAction }}
                    @endif
                </div>
            @else
                <div class="spu-assistant__table-wrap">
                    <table class="spu-assistant__table">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('admin_chatbot.table.document') }}</th>
                                <th scope="col">{{ __('admin_chatbot.table.status') }}</th>
                                <th scope="col">{{ __('admin_chatbot.table.passages') }}</th>
                                <th scope="col">{{ __('admin_chatbot.table.size') }}</th>
                                <th scope="col">{{ __('admin_chatbot.table.added') }}</th>
                                <th scope="col"><span class="sr-only">{{ __('admin_chatbot.table.actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($documents as $document)
                                <tr wire:key="chatbot-document-{{ $document->id }}" @class(['is-inactive' => ! $document->isActive])>
                                    <td>
                                        <div class="spu-assistant__doc">
                                            <span class="spu-assistant__doc-type" dir="ltr">{{ $document->sourceType === 'website' ? 'WEB' : strtoupper($document->extension ?? 'txt') }}</span>
                                            <div class="spu-assistant__doc-text">
                                                <span class="spu-assistant__doc-title" dir="auto">{{ $document->title }}</span>
                                                <span class="spu-assistant__muted" dir="auto">
                                                    @if ($document->sourceType === 'website' && $document->sourceUrl)
                                                        {{ __('admin_chatbot.types.website') }} ·
                                                        <a href="{{ $document->sourceUrl }}" target="_blank" rel="noopener" class="spu-assistant__link" dir="ltr">{{ $document->sourceUrl }}</a>
                                                    @else
                                                        {{ $document->sourceType === 'text' ? __('admin_chatbot.types.text') : ($document->originalName ?? __('admin_chatbot.types.upload')) }}
                                                    @endif
                                                    @unless ($document->isActive)
                                                        · {{ __('admin_chatbot.actions.disable') }}
                                                    @endunless
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <x-filament::badge :color="match ($document->status) { 'ready' => $document->needsReindex ? 'warning' : 'success', 'failed' => 'danger', default => 'gray' }">
                                            {{ __('admin_chatbot.statuses.'.$document->status) }}
                                        </x-filament::badge>
                                        @if ($document->needsReindex && $document->status === 'ready')
                                            <p class="spu-assistant__note">{{ __('admin_chatbot.needs_reindex') }}</p>
                                        @elseif ($document->error)
                                            <p @class(['spu-assistant__note', 'is-error' => $document->status === 'failed'])>{{ $document->error }}</p>
                                        @endif
                                    </td>
                                    <td dir="ltr" class="spu-assistant__num">{{ $number($document->chunkCount) }}</td>
                                    <td dir="ltr" class="spu-assistant__num">{{ $size($document->sizeBytes) }}</td>
                                    <td>
                                        <span dir="ltr">{{ \Illuminate\Support\Carbon::parse($document->createdAt)->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</span>
                                        @if ($document->uploadedBy)
                                            <span class="spu-assistant__muted">{{ __('admin_chatbot.table.by', ['name' => $document->uploadedBy]) }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="spu-assistant__row-actions">
                                            {{ ($this->previewDocumentAction)(['document' => $document->id]) }}
                                            {{ ($this->toggleDocumentAction)(['document' => $document->id, 'active' => $document->isActive]) }}
                                            {{ ($this->renameDocumentAction)(['document' => $document->id]) }}
                                            {{ ($this->reindexDocumentAction)(['document' => $document->id]) }}
                                            {{ ($this->deleteDocumentAction)(['document' => $document->id]) }}
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <p class="spu-assistant__privacy">{{ __('admin_chatbot.protection') }}</p>
        <p class="spu-assistant__privacy">{{ __('admin_chatbot.privacy') }}</p>
    </div>
</x-filament-panels::page>
