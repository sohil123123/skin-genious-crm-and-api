@php
    $providers = $this->getProviderHealth();
    $pipeline = $this->getPipelineHealth();
    $runs = $this->getRecentSyncRuns();
    $failedEvents = $this->getFailedEvents();

    $statusMeta = fn (string $status): array => match ($status) {
        'healthy' => ['ok', 'Healthy', 'Calls are arriving normally.'],
        'quiet' => ['warn', 'Quiet', 'Nothing has arrived for a while. Worth a look.'],
        'errors' => ['bad', 'Errors', 'Calls are arriving but some failed to process.'],
        'waiting' => ['idle', 'Waiting', 'Connected, but no call has arrived yet.'],
        default => ['idle', 'Disabled', 'This integration is switched off.'],
    };
@endphp

{{--
    Every style on this page is defined below rather than pulled from Tailwind.

    The panel does not register a viteTheme, so resources/css/app.css is never
    loaded into the admin panel — only Filament's own compiled stylesheet is.
    Utility classes written here would silently do nothing, which is exactly
    what this page looked like before: a flat, unstyled list of numbers.

    Self-contained CSS also means the page cannot be broken by someone
    forgetting to rebuild assets on deploy.
--}}
<x-filament-panels::page>

    <div class="cih">

        {{-- Providers. "Last heard" is the headline: an integration that has
             silently stopped looks exactly like a quiet week without it. --}}
        <section class="cih-providers">
            @foreach ($providers as $health)
                {{-- Block form, not @php(...). Blade extracts @php…@endphp
                     blocks with a non-greedy regex before it compiles
                     directives, so an inline @php( in a file that also uses the
                     block form gets paired with the next @endphp and swallows
                     everything in between. --}}
                @php
                    [$tone, $label, $hint] = $statusMeta($health['status']);
                @endphp

                <article class="cih-card cih-card--{{ $tone }}">
                    <header class="cih-card__head">
                        <div class="cih-card__title">
                            <h3>{{ $health['label'] }}</h3>
                            <span class="cih-pill cih-pill--{{ $tone }}">{{ $label }}</span>
                        </div>

                        <p class="cih-card__lead">
                            @if ($health['last_event_at'])
                                Last heard <strong>{{ $health['last_event_at']->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) }} ago</strong>
                            @else
                                {{ $hint }}
                            @endif
                        </p>

                        @if ($health['last_event_at'])
                            <p class="cih-card__sub">{{ $hint }}</p>
                        @endif
                    </header>

                    <dl class="cih-stats">
                        <div class="cih-stat">
                            <dt>Calls · 7 days</dt>
                            <dd>{{ number_format($health['calls_7d']) }}</dd>
                        </div>
                        <div class="cih-stat">
                            <dt>Calls · total</dt>
                            <dd>{{ number_format($health['calls_total']) }}</dd>
                        </div>
                        <div class="cih-stat {{ $health['failed_events_7d'] > 0 ? 'is-bad' : '' }}">
                            <dt>Failed events</dt>
                            <dd>{{ number_format($health['failed_events_7d']) }}</dd>
                        </div>
                        {{-- Shown plainly, never as a warning: both providers
                             retry by design, so a redelivery is normal traffic. --}}
                        <div class="cih-stat">
                            <dt>Redeliveries</dt>
                            <dd>{{ number_format($health['duplicates_7d']) }}</dd>
                        </div>
                    </dl>

                    @if ($health['sync_running'] || $health['last_successful_sync'] || $health['last_failed_sync'])
                        <div class="cih-sync">
                            @if ($health['sync_running'])
                                <p class="cih-sync__running">A sync is running now.</p>
                            @endif

                            @if ($health['last_successful_sync'])
                                <p>
                                    Last successful sync
                                    {{ $health['last_successful_sync']->finished_at?->diffForHumans() ?? '—' }}
                                    — {{ number_format($health['last_successful_sync']->calls_created) }} new,
                                    {{ number_format($health['last_successful_sync']->calls_updated) }} updated.
                                </p>
                            @endif

                            @if ($health['last_failed_sync'])
                                <p class="cih-sync__failed">
                                    Last failed sync {{ $health['last_failed_sync']->finished_at?->diffForHumans() ?? '—' }}.
                                </p>
                            @endif
                        </div>
                    @endif

                    @if ($health['last_error'])
                        <p class="cih-error">{{ Str::limit($health['last_error'], 240) }}</p>
                    @endif
                </article>
            @endforeach
        </section>

        {{-- The recording and transcription pipeline. --}}
        <section class="cih-card cih-card--plain">
            <header class="cih-card__head">
                <div class="cih-card__title"><h3>Recordings and transcripts</h3></div>
                <p class="cih-card__sub">
                    “Remote only” is audio the clinic does not own yet — it still lives on the provider’s
                    server and disappears when their retention window closes.
                </p>
            </header>

            <div class="cih-groups">
                @foreach ([
                    'Download' => $pipeline['download'],
                    'Storage' => $pipeline['storage'],
                    'Transcription' => $pipeline['transcription'],
                ] as $group => $counts)
                    <div class="cih-group">
                        <h4>{{ $group }}</h4>
                        <dl>
                            @foreach ($counts as $state => $count)
                                @php
                                    $isBad = $state === 'failed' && $count > 0;
                                    $isWarn = $state === 'remote_only' && $count > 0;
                                @endphp
                                <div class="cih-row {{ $isBad ? 'is-bad' : ($isWarn ? 'is-warn' : '') }}">
                                    <dt>{{ Str::headline($state) }}</dt>
                                    <dd>{{ number_format($count) }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Sync history. --}}
        @if ($runs->isNotEmpty())
            <section class="cih-card cih-card--plain">
                <header class="cih-card__head">
                    <div class="cih-card__title"><h3>Recent sync runs</h3></div>
                </header>

                <div class="cih-scroll">
                    <table class="cih-table">
                        <thead>
                            <tr>
                                <th>Started</th>
                                <th>Provider</th>
                                <th>Trigger</th>
                                <th>Status</th>
                                <th class="num">Received</th>
                                <th class="num">New</th>
                                <th class="num">Updated</th>
                                <th class="num">Failed</th>
                                <th class="num">Took</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($runs as $run)
                                <tr>
                                    <td class="nowrap">{{ $run->started_at?->timezone(app_timezone())->format(app_datetime_format()) }}</td>
                                    <td>{{ $run->provider?->getLabel() }}</td>
                                    <td>
                                        {{ Str::headline($run->trigger) }}
                                        @if ($run->triggeredBy)
                                            <span class="cih-muted">· {{ $run->triggeredBy->name }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $runTone = match ($run->status?->value) {
                                                'completed' => 'ok',
                                                'running' => 'idle',
                                                'partial' => 'warn',
                                                default => 'bad',
                                            };
                                        @endphp
                                        <span class="cih-pill cih-pill--{{ $runTone }}">{{ $run->status?->getLabel() }}</span>
                                    </td>
                                    <td class="num">{{ number_format($run->records_received) }}</td>
                                    <td class="num">{{ number_format($run->calls_created) }}</td>
                                    <td class="num">{{ number_format($run->calls_updated) }}</td>
                                    <td class="num {{ $run->calls_failed > 0 ? 'is-bad' : '' }}">{{ number_format($run->calls_failed) }}</td>
                                    <td class="num">{{ $run->duration_seconds !== null ? $run->duration_seconds . 's' : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        {{-- Each of these is a call the CRM does not have. --}}
        @if ($failedEvents->isNotEmpty())
            <section class="cih-card cih-card--plain">
                <header class="cih-card__head">
                    <div class="cih-card__title"><h3>Failed events</h3></div>
                    <p class="cih-card__sub">
                        Each one is a call that never made it in. The original payload is archived,
                        so these can be reprocessed once the cause is fixed.
                    </p>
                </header>

                <ul class="cih-failures">
                    @foreach ($failedEvents as $event)
                        <li>
                            <p class="cih-failures__head">
                                {{ $event->provider?->getLabel() }} ·
                                <span class="mono">{{ $event->provider_call_id ?: 'no call id' }}</span>
                                <span class="cih-muted">
                                    · {{ $event->received_at?->diffForHumans() }}
                                    · {{ $event->attempts }} attempt{{ $event->attempts === 1 ? '' : 's' }}
                                </span>
                            </p>
                            @if ($event->error_message)
                                <p class="cih-failures__msg">{{ Str::limit($event->error_message, 240) }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

    </div>

    <style>
        .cih {
            /* Tones are defined once here so every card, pill and number on the
               page agrees on what "bad" looks like. */
            --cih-ok: #16a34a;
            --cih-ok-bg: #dcfce7;
            --cih-warn: #b45309;
            --cih-warn-bg: #fef3c7;
            --cih-bad: #dc2626;
            --cih-bad-bg: #fee2e2;
            --cih-idle: #6b7280;
            --cih-idle-bg: #f3f4f6;

            --cih-surface: #fff;
            --cih-border: rgba(17, 24, 39, .09);
            --cih-text: #111827;
            --cih-muted: #6b7280;

            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            color: var(--cih-text);
        }

        .dark .cih {
            --cih-ok-bg: rgba(22, 163, 74, .16);
            --cih-warn-bg: rgba(180, 83, 9, .18);
            --cih-bad-bg: rgba(220, 38, 38, .16);
            --cih-idle-bg: rgba(107, 114, 128, .18);

            --cih-ok: #4ade80;
            --cih-warn: #fbbf24;
            --cih-bad: #f87171;
            --cih-idle: #9ca3af;

            --cih-surface: rgb(24 24 27);
            --cih-border: rgba(255, 255, 255, .1);
            --cih-text: #f4f4f5;
            --cih-muted: #a1a1aa;
        }

        /* auto-fit rather than fixed breakpoints: the cards decide for
           themselves when there is room for a second column, so this behaves
           correctly inside a collapsed sidebar as well as on a phone. */
        .cih-providers {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 22rem), 1fr));
            gap: 1.25rem;
        }

        .cih-card {
            background: var(--cih-surface);
            border: 1px solid var(--cih-border);
            border-radius: .75rem;
            padding: 1.25rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .04);
        }

        /* A colour down the edge, so status is readable before any text is. */
        .cih-card--ok { border-left: 3px solid var(--cih-ok); }
        .cih-card--warn { border-left: 3px solid var(--cih-warn); }
        .cih-card--bad { border-left: 3px solid var(--cih-bad); }
        .cih-card--idle { border-left: 3px solid var(--cih-idle); }

        .cih-card__head { margin-bottom: 1rem; }

        .cih-card__title {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: .625rem;
        }

        .cih-card__title h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: -.01em;
        }

        .cih-card__lead {
            margin: .5rem 0 0;
            font-size: .9375rem;
        }

        .cih-card__sub {
            margin: .25rem 0 0;
            font-size: .8125rem;
            color: var(--cih-muted);
            line-height: 1.5;
        }

        .cih-pill {
            display: inline-flex;
            align-items: center;
            padding: .125rem .5rem;
            border-radius: 999px;
            font-size: .6875rem;
            font-weight: 700;
            letter-spacing: .02em;
            white-space: nowrap;
        }

        .cih-pill--ok { background: var(--cih-ok-bg); color: var(--cih-ok); }
        .cih-pill--warn { background: var(--cih-warn-bg); color: var(--cih-warn); }
        .cih-pill--bad { background: var(--cih-bad-bg); color: var(--cih-bad); }
        .cih-pill--idle { background: var(--cih-idle-bg); color: var(--cih-idle); }

        .cih-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(6.5rem, 1fr));
            gap: .75rem;
            margin: 0;
        }

        .cih-stat {
            background: var(--cih-idle-bg);
            border-radius: .5rem;
            padding: .625rem .75rem;
        }

        /* The number first, the label under it — the figure is what is being
           scanned for, not the word. */
        .cih-stat dt {
            order: 2;
            font-size: .6875rem;
            color: var(--cih-muted);
            line-height: 1.3;
        }

        .cih-stat dd {
            order: 1;
            margin: 0 0 .125rem;
            font-size: 1.375rem;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            line-height: 1.1;
        }

        .cih-stat { display: flex; flex-direction: column; }
        .cih-stat.is-bad dd { color: var(--cih-bad); }

        .cih-sync {
            margin-top: 1rem;
            padding-top: .875rem;
            border-top: 1px solid var(--cih-border);
            font-size: .8125rem;
            color: var(--cih-muted);
            line-height: 1.6;
        }

        .cih-sync p { margin: 0; }
        .cih-sync__running { color: var(--cih-ok); font-weight: 600; }
        .cih-sync__failed { color: var(--cih-bad); }

        .cih-error {
            margin: .875rem 0 0;
            padding: .5rem .625rem;
            border-radius: .5rem;
            background: var(--cih-bad-bg);
            color: var(--cih-bad);
            font-size: .75rem;
            line-height: 1.5;
            word-break: break-word;
        }

        .cih-groups {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 14rem), 1fr));
            gap: 1.25rem;
        }

        .cih-group h4 {
            margin: 0 0 .5rem;
            font-size: .75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--cih-muted);
        }

        .cih-group dl { margin: 0; }

        .cih-row {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: .75rem;
            padding: .3125rem 0;
            border-bottom: 1px solid var(--cih-border);
            font-size: .8125rem;
        }

        .cih-row:last-child { border-bottom: 0; }
        .cih-row dt { color: var(--cih-muted); }

        .cih-row dd {
            margin: 0;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
        }

        .cih-row.is-bad dd { color: var(--cih-bad); }
        .cih-row.is-warn dd { color: var(--cih-warn); }

        /* A table is the right shape for run history, so it stays a table and
           scrolls sideways rather than being reflowed into unreadable cards. */
        .cih-scroll {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin: 0 -.25rem;
            padding: 0 .25rem;
        }

        .cih-table {
            width: 100%;
            min-width: 44rem;
            border-collapse: collapse;
            font-size: .8125rem;
        }

        .cih-table th {
            text-align: left;
            font-weight: 600;
            font-size: .6875rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--cih-muted);
            padding: 0 .75rem .5rem 0;
            white-space: nowrap;
        }

        .cih-table td {
            padding: .5rem .75rem .5rem 0;
            border-top: 1px solid var(--cih-border);
            vertical-align: middle;
        }

        .cih-table .num { text-align: right; font-variant-numeric: tabular-nums; }
        .cih-table .nowrap { white-space: nowrap; }
        .cih-table td.is-bad { color: var(--cih-bad); font-weight: 600; }

        .cih-muted { color: var(--cih-muted); }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .75rem; }

        .cih-failures { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .75rem; }

        .cih-failures li {
            border-left: 2px solid var(--cih-bad);
            padding-left: .75rem;
        }

        .cih-failures__head { margin: 0; font-size: .8125rem; font-weight: 600; word-break: break-word; }

        .cih-failures__msg {
            margin: .125rem 0 0;
            font-size: .75rem;
            color: var(--cih-muted);
            line-height: 1.5;
            word-break: break-word;
        }

        @media (max-width: 640px) {
            .cih-card { padding: 1rem; }
            .cih-stat dd { font-size: 1.25rem; }
        }
    </style>
</x-filament-panels::page>
