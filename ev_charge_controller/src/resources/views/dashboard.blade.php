<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Charger Control</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=jetbrains-mono:400,500|space-grotesk:400,500,700" rel="stylesheet" />
        @php
            $viteManifestPath = public_path('build/manifest.json');
            $viteManifest = file_exists($viteManifestPath)
                ? json_decode(file_get_contents($viteManifestPath), true)
                : null;
            $compiledCssFile = is_array($viteManifest) && isset($viteManifest['resources/css/app.css']['file'])
                ? $viteManifest['resources/css/app.css']['file']
                : null;
            $compiledCssPath = $compiledCssFile
                ? public_path('build/'.$compiledCssFile)
                : null;
        @endphp

        @if (file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @elseif ($compiledCssPath && file_exists($compiledCssPath))
            <style>{!! file_get_contents($compiledCssPath) !!}</style>
        @endif
    </head>
    <body class="font-sans">
        <main class="mx-auto flex min-h-screen w-full max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
            <section class="glass-panel overflow-hidden">
                <div class="grid gap-6 px-5 py-6 lg:grid-cols-[1.4fr_0.9fr] lg:px-8 lg:py-8">
                    <div class="space-y-5">
                        <div class="eyebrow">EV Charge Controller</div>
                        <div class="max-w-3xl space-y-3">
                            <h1 class="text-4xl font-bold tracking-tight text-white sm:text-5xl">Cheapest-charge planner for Nordpool, grid tariffs, solar, and daily readiness.</h1>
                            <p class="max-w-2xl text-sm leading-6 text-white/70 sm:text-base">
                                Strategy evaluation runs every 30 minutes, ranks the next import and solar windows, and keeps the car above the daily floor before filling toward the target SoC.
                            </p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <article class="metric-card">
                                <div class="eyebrow">Current SoC</div>
                                <div class="mt-3 text-4xl font-bold text-white">{{ number_format($settings?->current_soc_percent ?? 0, 0) }}%</div>
                                <p class="mt-2 text-sm text-white/60">Minimum {{ number_format($settings?->daily_minimum_soc_percent ?? 0, 0) }}% by {{ data_get($displayPlan, 'deadline_at')?->format('H:i') ?? 'n/a' }}</p>
                            </article>
                            <article class="metric-card">
                                <div class="eyebrow">Planned Energy</div>
                                <div class="mt-3 text-4xl font-bold text-white">{{ number_format(data_get($displayPlan, 'planned_energy_kwh', 0), 1) }} kWh</div>
                                <p class="mt-2 text-sm text-white/60">Target delta {{ number_format(data_get($displayPlan, 'target_energy_kwh', 0), 1) }} kWh</p>
                            </article>
                            <article class="metric-card">
                                <div class="eyebrow">Estimated Cost</div>
                                <div class="mt-3 text-4xl font-bold text-white">EUR {{ number_format(data_get($displayPlan, 'estimated_cost', 0), 2) }}</div>
                                <p class="mt-2 text-sm text-white/60">Avg. EUR {{ number_format(data_get($displayPlan, 'average_price_per_kwh', 0), 3) }}/kWh</p>
                            </article>
                            <article class="metric-card">
                                <div class="eyebrow">History Savings</div>
                                <div class="mt-3 text-4xl font-bold text-white">EUR {{ number_format($historyTotals?->savings_amount ?? 0, 2) }}</div>
                                <p class="mt-2 text-sm text-white/60">{{ number_format($historyTotals?->energy_kwh ?? 0, 1) }} kWh delivered</p>
                            </article>
                        </div>

                        <article class="glass-panel border-white/8 bg-black/10 p-5">
                            <div class="eyebrow">Operations</div>
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-white/45">
                                <span>Allowed:</span>
                                <code class="rounded-full border border-white/10 bg-white/5 px-2 py-1">app:evaluate-charging-strategy</code>
                                <code class="rounded-full border border-white/10 bg-white/5 px-2 py-1">app:execute-charging-plan</code>
                                <code class="rounded-full border border-white/10 bg-white/5 px-2 py-1">app:reset-charging-plan</code>
                                <code class="rounded-full border border-white/10 bg-white/5 px-2 py-1">app:check-home-assistant</code>
                                <code class="rounded-full border border-white/10 bg-white/5 px-2 py-1">app:dump-home-assistant-entity</code>
                                <code class="rounded-full border border-white/10 bg-white/5 px-2 py-1">about</code>
                                <code class="rounded-full border border-white/10 bg-white/5 px-2 py-1">migrate:status</code>
                                <code class="rounded-full border border-white/10 bg-white/5 px-2 py-1">schedule:list</code>
                            </div>
                            <form class="mt-4 space-y-3" method="POST" action="./artisan-run" data-artisan-runner>
                                @csrf
                                <label class="block">
                                    <span class="mb-2 block text-sm text-white/60">Run artisan command</span>
                                    <input
                                        type="text"
                                        name="command"
                                        value="{{ old('command', data_get(session('artisan_result'), 'command', 'app:evaluate-charging-strategy --until=07:00 --minimum-soc=70 --minimum-deadline=06:00')) }}"
                                        class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 font-mono text-sm text-white outline-none"
                                        placeholder="app:evaluate-charging-strategy --until=07:00 --minimum-soc=70 --minimum-deadline=06:00"
                                    />
                                </label>
                                <button type="submit" class="rounded-2xl border border-white/15 bg-black/20 px-4 py-3 text-sm font-medium text-white transition duration-150 ease-in-out hover:bg-white/5">
                                    Run Command
                                </button>
                            </form>

                        </article>
                    </div>

                    <aside class="glass-panel border-white/8 bg-black/10 p-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="eyebrow">Strategy Inputs</div>
                                <h2 class="mt-2 text-xl font-semibold text-white">{{ $settings?->charger_name ?? 'No charger configured' }}</h2>
                            </div>
                            @if ($usingPreviewPlan)
                                <span class="rounded-full border border-solar/40 bg-solar/10 px-3 py-1 text-xs font-medium text-solar">Preview plan</span>
                            @endif
                        </div>

                        @if (session('dashboard_status'))
                            <div class="mt-4 rounded-2xl border border-mint/40 bg-mint/10 px-4 py-3 text-sm text-mint">
                                {{ session('dashboard_status') }}
                            </div>
                        @endif

                        @if (session('dashboard_error'))
                            <div class="mt-4 rounded-2xl border border-white/15 bg-black/20 px-4 py-3 text-sm text-white/70">
                                {{ session('dashboard_error') }}
                            </div>
                        @endif

                        <div class="mt-5 grid gap-2 sm:grid-cols-2">
                            <form method="POST" action="./actions/plan" data-dashboard-action="plan">
                                @csrf
                                <button type="submit" class="w-full rounded-2xl border border-sky/40 bg-sky/10 px-4 py-3 text-sm font-medium text-white transition duration-150 ease-in-out hover:bg-white/5">
                                    Refresh Plan
                                </button>
                            </form>
                            <form method="POST" action="./actions/execute" data-dashboard-action="execute">
                                @csrf
                                <button type="submit" class="w-full rounded-2xl border border-mint/40 bg-mint/10 px-4 py-3 text-sm font-medium text-white transition duration-150 ease-in-out hover:bg-white/5">
                                    Execute Now
                                </button>
                            </form>
                            <form method="POST" action="./actions/reset" data-dashboard-action="reset">
                                @csrf
                                <button type="submit" class="w-full rounded-2xl border border-solar/40 bg-solar/10 px-4 py-3 text-sm font-medium text-white transition duration-150 ease-in-out hover:bg-white/5">
                                    Reset + Replan
                                </button>
                            </form>
                            <form method="POST" action="./actions/stop" data-dashboard-action="stop">
                                @csrf
                                <button type="submit" class="w-full rounded-2xl border border-white/15 bg-black/20 px-4 py-3 text-sm font-medium text-white transition duration-150 ease-in-out hover:bg-white/5">
                                    Stop Charger
                                </button>
                            </form>
                        </div>

                        <dl class="mt-6 grid gap-4 text-sm text-white/70">
                            <div class="flex items-center justify-between rounded-2xl border border-white/8 bg-white/5 px-4 py-3">
                                <dt>Battery capacity</dt>
                                <dd class="font-mono text-white">{{ number_format($settings?->battery_capacity_kwh ?? 0, 1) }} kWh</dd>
                            </div>
                            <div class="flex items-center justify-between rounded-2xl border border-white/8 bg-white/5 px-4 py-3">
                                <dt>Charger rate</dt>
                                <dd class="font-mono text-white">{{ number_format($settings?->charger_power_kw ?? 0, 1) }} kW</dd>
                            </div>
                            <div class="flex items-center justify-between rounded-2xl border border-white/8 bg-white/5 px-4 py-3">
                                <dt>Grid tariff</dt>
                                <dd class="font-mono text-white">Day {{ number_format($settings?->grid_day_rate_per_kwh ?? 0, 3) }} / Night {{ number_format($settings?->grid_night_rate_per_kwh ?? 0, 3) }}</dd>
                            </div>
                            <div class="flex items-center justify-between rounded-2xl border border-white/8 bg-white/5 px-4 py-3">
                                <dt>Weekend tariff</dt>
                                <dd class="font-mono text-white">{{ number_format($settings?->grid_weekend_rate_per_kwh ?? 0, 3) }} EUR/kWh</dd>
                            </div>
                        </dl>
                    </aside>
                </div>
            </section>

            <section class="glass-panel p-5 lg:p-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <div class="eyebrow">Timeline</div>
                        <h2 class="section-title mt-2">Executed vs planned charging</h2>
                    </div>
                    <div class="flex items-center gap-4 text-xs uppercase tracking-[0.24em] text-white/40">
                        <span class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-mint"></span>
                            Executed
                        </span>
                        <span class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-white/30"></span>
                            Planned
                        </span>
                        <span class="flex items-center gap-2">
                            <span class="h-px w-5 bg-amber-300"></span>
                            Market Price
                        </span>
                    </div>
                </div>
                <p class="mt-3 text-sm text-white/55">8 hours behind now and 24 hours ahead, split into 15-minute bars.</p>

                <div class="mt-6 overflow-x-auto pb-1">
                    @php
                        $timelineHours = $timeline->chunk(4)->values();
                        $hourWidth = 44;
                        $hourGap = 12;
                        $chartWidth = max(0, ($timelineHours->count() * $hourWidth) + (max(0, $timelineHours->count() - 1) * $hourGap));
                        $priceLinePoints = $timeline
                            ->values()
                            ->map(function (array $bin, int $index) use ($hourWidth, $hourGap) {
                                if ($bin['price_y'] === null) {
                                    return null;
                                }

                                $hourIndex = intdiv($index, 4);
                                $quarterIndex = $index % 4;
                                $x = ($hourIndex * ($hourWidth + $hourGap)) + 8.5 + ($quarterIndex * 9);

                                return number_format($x, 2, '.', '').','.number_format($bin['price_y'], 2, '.', '');
                            })
                            ->filter()
                            ->implode(' ');
                    @endphp
                    <div class="min-w-max">
                        <div class="relative rounded-[28px] border border-white/8 bg-black/10 px-5 pb-4 pt-5">
                            <div class="pointer-events-none absolute inset-x-5 bottom-14 border-t border-dashed border-white/10"></div>
                            <div class="relative" style="width: {{ $chartWidth }}px;">
                                @if ($priceLinePoints !== '')
                                    <svg
                                        class="pointer-events-none absolute inset-x-0 top-0 z-10"
                                        width="{{ $chartWidth }}"
                                        height="128"
                                        viewBox="0 0 {{ $chartWidth }} 128"
                                        fill="none"
                                        preserveAspectRatio="none"
                                    >
                                        <polyline
                                            points="{{ $priceLinePoints }}"
                                            stroke="#f7c66c"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            opacity="0.95"
                                        />
                                    </svg>
                                @endif
                                <div class="relative z-20 flex items-end gap-3">
                                @foreach ($timelineHours as $hourBins)
                                    @php
                                        $hourStart = $hourBins->first()['starts_at'];
                                        $isCurrentHour = $hourBins->contains(fn (array $bin) => $bin['is_current']);
                                        $plannedTotal = $hourBins->sum('planned_kwh');
                                        $executedTotal = $hourBins->sum('executed_kwh');
                                        $markerPercent = max(
                                            0,
                                            min(
                                                100,
                                                (($currentTime->getTimestamp() - $hourStart->getTimestamp()) / 3600) * 100,
                                            ),
                                        );
                                        $tooltip = $hourStart->format('D H:i').' to '.$hourStart->copy()->addHour()->format('H:i')
                                            .' | planned '.number_format($plannedTotal, 2).' kWh'
                                            .' | executed '.number_format($executedTotal, 2).' kWh';
                                    @endphp
                                    <div class="flex flex-col items-center gap-3">
                                        <div
                                            class="relative flex h-32 items-end gap-1 rounded-2xl px-1.5 {{ $isCurrentHour ? 'bg-sky/6' : '' }}"
                                            title="{{ $tooltip }}{{ $hourBins->avg('market_price_per_kwh') ? ' | avg market EUR '.number_format($hourBins->avg('market_price_per_kwh'), 3).'/kWh' : '' }}"
                                        >
                                            @if ($isCurrentHour)
                                                <div class="pointer-events-none absolute inset-y-0 w-px -translate-x-1/2 bg-sky/65" style="left: {{ number_format($markerPercent, 2, '.', '') }}%;"></div>
                                            @endif
                                            @foreach ($hourBins as $bin)
                                                @php
                                                    $trackColor = $bin['is_current']
                                                        ? 'rgba(110, 206, 255, 0.16)'
                                                        : 'rgba(255, 255, 255, 0.06)';
                                                    $barTitle = $bin['starts_at']->format('D H:i').' to '.$bin['ends_at']->format('H:i')
                                                        .' | planned '.number_format($bin['planned_kwh'], 2).' kWh'
                                                        .' | executed '.number_format($bin['executed_kwh'], 2).' kWh'
                                                        .($bin['market_price_per_kwh'] !== null ? ' | market EUR '.number_format($bin['market_price_per_kwh'], 3).'/kWh' : '');
                                                @endphp
                                                <div class="flex h-28 items-end" style="width: 5px; min-width: 5px;" title="{{ $barTitle }}">
                                                    <div class="flex items-end justify-center rounded-full" style="width: 5px; height: 112px; background-color: {{ $trackColor }};">
                                                        @if ($bin['executed_kwh'] > 0)
                                                            <div style="width: 5px; height: {{ max(4, $bin['executed_height']) }}px; border-radius: 9999px; background-color: #7df2c4;"></div>
                                                        @elseif ($bin['planned_kwh'] > 0)
                                                            <div style="width: 5px; height: {{ max(4, $bin['planned_height']) }}px; border-radius: 9999px; background-color: rgba(255, 255, 255, 0.35);"></div>
                                                        @else
                                                            <div style="width: 5px; height: 4px; border-radius: 9999px; background-color: transparent;"></div>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                        <div class="flex min-h-[2.5rem] flex-col items-center justify-start">
                                            <div class="font-mono text-lg text-white/72">{{ $hourStart->format('G') }}</div>
                                            @if ($loop->first || ! $hourStart->isSameDay($timelineHours[$loop->index - 1]->first()['starts_at']))
                                                <div class="mt-1 text-[10px] uppercase tracking-[0.18em] text-white/36">{{ $hourStart->format('D') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-6 lg:grid-cols-[1.3fr_0.9fr]">
                <article class="glass-panel p-5 lg:p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="eyebrow">Forward Schedule</div>
                            <h2 class="section-title mt-2">Next 36 hours of chargeable windows</h2>
                        </div>
                        <p class="text-xs uppercase tracking-[0.24em] text-white/40">Spot + grid + solar</p>
                    </div>

                    <div class="mt-6 grid gap-3">
                        @forelse ($forecasts as $forecast)
                            @php
                                $selected = $selectedSlotStarts->get($forecast->starts_at->toIso8601String());
                                $height = max(16, min(100, ($forecast->market_price_per_kwh * 650) + 16));
                            @endphp
                            <div class="grid grid-cols-[88px_1fr] gap-3 rounded-3xl border border-white/8 bg-white/4 p-3">
                                <div class="font-mono text-xs text-white/55">
                                    <div>{{ $forecast->starts_at->format('D') }}</div>
                                    <div class="mt-1 text-sm text-white">{{ $forecast->starts_at->format('H:i') }}</div>
                                </div>
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between gap-4">
                                        <div class="flex items-end gap-3">
                                            <div class="w-2 rounded-full bg-sky" style="height: {{ $height }}px"></div>
                                            <div>
                                                <div class="text-sm font-semibold text-white">EUR {{ number_format($forecast->market_price_per_kwh, 3) }}/kWh spot</div>
                                                <div class="text-xs text-white/55">{{ number_format($forecast->solar_surplus_kwh, 1) }} kWh forecast solar surplus</div>
                                            </div>
                                        </div>
                                        @if ($selected)
                                            @php
                                                $slotStatus = data_get($selected, 'display_status', data_get($selected, 'status', 'planned'));
                                                $executedEnergy = (float) data_get($selected, 'executed_energy_kwh', 0);
                                                $plannedEnergy = (float) data_get($selected, 'allocated_energy_kwh', 0);
                                            @endphp
                                            @if (in_array($slotStatus, ['completed', 'stopped'], true) && $executedEnergy > 0)
                                                <span class="rounded-full border border-mint/40 bg-mint/10 px-3 py-1 text-xs font-medium text-mint">
                                                    {{ number_format($executedEnergy, 1) }} kWh {{ $slotStatus === 'stopped' ? 'stopped' : 'executed' }}
                                                </span>
                                            @elseif ($slotStatus === 'active')
                                                <span class="rounded-full border border-sky/40 bg-sky/10 px-3 py-1 text-xs font-medium text-sky">
                                                    Running
                                                </span>
                                            @elseif ($slotStatus === 'missed')
                                                <span class="rounded-full border border-alert/40 bg-alert/10 px-3 py-1 text-xs font-medium text-alert">
                                                    Missed
                                                </span>
                                            @else
                                                <span class="rounded-full border border-mint/40 bg-mint/10 px-3 py-1 text-xs font-medium text-mint">
                                                    {{ number_format($plannedEnergy, 1) }} kWh planned
                                                </span>
                                            @endif
                                        @else
                                            <span class="rounded-full border border-white/10 bg-black/10 px-3 py-1 text-xs font-medium text-white/45">Standby</span>
                                        @endif
                                    </div>

                                    @if ($selected)
                                        <div class="rounded-2xl border border-white/8 bg-black/10 px-4 py-3 text-sm text-white/70">
                                            <div class="flex flex-wrap items-center justify-between gap-3">
                                                <span>Effective price: EUR {{ number_format(data_get($selected, 'effective_price_per_kwh', 0), 3) }}/kWh</span>
                                                <span class="font-mono text-xs uppercase tracking-[0.24em] text-white/40">
                                                    @if ($slotStatus === 'completed' && data_get($selected, 'executed_energy_kwh', 0) > 0)
                                                        Executed
                                                    @elseif ($slotStatus === 'stopped' && data_get($selected, 'executed_energy_kwh', 0) > 0)
                                                        Stopped
                                                    @elseif ($slotStatus === 'missed')
                                                        Missed
                                                    @elseif ($slotStatus === 'active')
                                                        Active
                                                    @else
                                                        {{ ucfirst(data_get($selected, 'selection_bucket', 'planned')) }}
                                                    @endif
                                                </span>
                                            </div>
                                            <p class="mt-2 text-xs leading-5 text-white/55">
                                                @if ($slotStatus === 'completed' && data_get($selected, 'executed_energy_kwh', 0) > 0)
                                                    Charged {{ number_format(data_get($selected, 'executed_energy_kwh', 0), 2) }} kWh based on charger meter readings.
                                                @elseif ($slotStatus === 'stopped' && data_get($selected, 'executed_energy_kwh', 0) > 0)
                                                    Stopped early after charging {{ number_format(data_get($selected, 'executed_energy_kwh', 0), 2) }} kWh based on charger meter readings.
                                                @elseif ($slotStatus === 'missed')
                                                    Planned slot passed without recorded charging.
                                                @else
                                                    {{ data_get($selected, 'rationale') }}
                                                @endif
                                            </p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="rounded-3xl border border-dashed border-white/15 bg-white/5 px-5 py-8 text-sm text-white/60">
                                No Nordpool or solar forecast records loaded yet.
                            </div>
                        @endforelse
                    </div>
                </article>

                <div class="space-y-6">
                    <article class="glass-panel p-5 lg:p-6">
                        <div class="eyebrow">Selected Windows</div>
                        <h2 class="section-title mt-2">Planned charge execution</h2>
                        <div class="mt-5 space-y-3">
                            @forelse ($displaySlots as $slot)
                                <div class="rounded-3xl border border-white/8 bg-white/5 px-4 py-4">
                                    <div class="flex items-center justify-between gap-4">
                                        <div>
                                            <div class="text-sm font-semibold text-white">{{ data_get($slot, 'starts_at')?->format('D H:i') }} to {{ data_get($slot, 'ends_at')?->format('H:i') }}</div>
                                            <div class="mt-1 text-xs text-white/50">{{ data_get($slot, 'rationale') }}</div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-lg font-semibold text-white">{{ number_format(data_get($slot, 'allocated_energy_kwh', 0), 1) }} kWh</div>
                                            <div class="text-xs text-white/55">EUR {{ number_format(data_get($slot, 'estimated_cost', 0), 2) }}</div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-3xl border border-dashed border-white/15 bg-white/5 px-5 py-8 text-sm text-white/60">
                                    No charging plan has been generated yet.
                                </div>
                            @endforelse
                        </div>
                    </article>

                    <article class="glass-panel p-5 lg:p-6">
                        <div class="eyebrow">History</div>
                        <h2 class="section-title mt-2">Recent charging sessions</h2>
                        <div class="mt-5 overflow-hidden rounded-3xl border border-white/8">
                            <table class="min-w-full divide-y divide-white/8 text-left text-sm">
                                <thead class="bg-black/10 text-xs uppercase tracking-[0.24em] text-white/40">
                                    <tr>
                                        <th class="px-4 py-3">Started</th>
                                        <th class="px-4 py-3">Energy</th>
                                        <th class="px-4 py-3">Avg price</th>
                                        <th class="px-4 py-3">Savings</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/8 bg-white/4 text-white/70">
                                    @forelse ($recentSessions as $session)
                                        <tr>
                                            <td class="px-4 py-3">{{ $session->started_at->format('d M H:i') }}</td>
                                            <td class="px-4 py-3">{{ number_format($session->energy_kwh, 1) }} kWh</td>
                                            <td class="px-4 py-3">EUR {{ number_format($session->average_price_per_kwh, 3) }}</td>
                                            <td class="px-4 py-3 text-mint">EUR {{ number_format($session->savings_amount, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-4 py-6 text-center text-white/50">No charging history yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </article>
                </div>
            </section>
        </main>
        <div
            class="pointer-events-none fixed inset-0 z-50 hidden items-start justify-center bg-black/40 px-4 py-6 backdrop-blur-sm"
            data-command-toast
            aria-live="polite"
        >
            <div class="pointer-events-auto mt-8 w-full max-w-2xl rounded-3xl border border-white/12 bg-[#111a23]/95 p-4 shadow-2xl shadow-black/30 backdrop-blur">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="text-xs uppercase tracking-[0.24em] text-sky" data-command-toast-label>Command finished</div>
                        <div class="mt-2 break-all font-mono text-sm text-white" data-command-toast-command></div>
                    </div>
                    <button
                        type="button"
                        class="rounded-full border border-white/10 px-3 py-1 text-xs font-medium text-white/70 transition duration-150 ease-in-out hover:bg-white/5"
                        data-command-toast-close
                    >
                        Close
                    </button>
                </div>
                <pre class="mt-3 max-h-72 overflow-auto whitespace-pre-wrap rounded-2xl border border-white/8 bg-black/20 p-4 font-mono text-xs leading-6 text-white/75" data-command-toast-output></pre>
            </div>
        </div>
        <script>
            (() => {
                const QUARTER_HOUR_MS = 15 * 60 * 1000;
                const now = new Date();
                const nextQuarter = new Date(now.getTime());

                nextQuarter.setSeconds(0, 0);
                nextQuarter.setMinutes(Math.floor(now.getMinutes() / 15) * 15 + 15);

                const delay = Math.max(1000, nextQuarter.getTime() - now.getTime() + 1500);

                window.setTimeout(() => {
                    window.location.reload();
                }, delay);
            })();

            (() => {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                document.querySelectorAll('form[data-dashboard-action]').forEach((form) => {
                    form.addEventListener('submit', async (event) => {
                        event.preventDefault();

                        const submitButton = form.querySelector('button[type="submit"]');

                        if (submitButton) {
                            submitButton.disabled = true;
                        }

                        try {
                            const response = await fetch(form.action, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': csrfToken ?? '',
                                },
                            });

                            if (! response.ok) {
                                throw new Error(`Request failed with status ${response.status}`);
                            }

                            window.location.reload();
                        } catch (error) {
                            console.error(error);

                            if (submitButton) {
                                submitButton.disabled = false;
                            }
                        }
                    });
                });
            })();

            (() => {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const form = document.querySelector('form[data-artisan-runner]');
                const toast = document.querySelector('[data-command-toast]');
                const toastCommand = document.querySelector('[data-command-toast-command]');
                const toastOutput = document.querySelector('[data-command-toast-output]');
                const toastLabel = document.querySelector('[data-command-toast-label]');
                const toastClose = document.querySelector('[data-command-toast-close]');
                let toastTimer = null;

                if (! form || ! toast || ! toastCommand || ! toastOutput || ! toastLabel || ! toastClose) {
                    return;
                }

                const showToast = (result, fallbackCommand = '') => {
                    if (toastTimer) {
                        window.clearTimeout(toastTimer);
                    }

                    const exitCode = Number(result.exit_code ?? 1);
                    toast.classList.remove('hidden');
                    toast.classList.add('flex');
                    toastCommand.textContent = result.command ?? fallbackCommand;
                    toastOutput.textContent = result.output && result.output !== '' ? result.output : 'No output.';
                    toastLabel.textContent = exitCode === 0 ? 'Command finished' : 'Command failed';
                    toastLabel.className = `text-xs uppercase tracking-[0.24em] ${exitCode === 0 ? 'text-sky' : 'text-alert'}`;

                    toastTimer = window.setTimeout(() => {
                        toast.classList.add('hidden');
                        toast.classList.remove('flex');
                    }, 9000);
                };

                toastClose.addEventListener('click', () => {
                    if (toastTimer) {
                        window.clearTimeout(toastTimer);
                    }

                    toast.classList.add('hidden');
                    toast.classList.remove('flex');
                });

                form.addEventListener('submit', async (event) => {
                    event.preventDefault();

                    const submitButton = form.querySelector('button[type="submit"]');
                    const formData = new FormData(form);

                    if (submitButton) {
                        submitButton.disabled = true;
                    }

                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': csrfToken ?? '',
                            },
                            body: formData,
                        });

                        const result = await response.json();
                        showToast(result, formData.get('command')?.toString() ?? '');
                    } catch (error) {
                        const fallbackResult = {
                            command: formData.get('command')?.toString() ?? '',
                            exit_code: 1,
                            output: error instanceof Error ? error.message : 'Command request failed.',
                        };

                        showToast(fallbackResult, fallbackResult.command);
                        console.error(error);
                    } finally {
                        if (submitButton) {
                            submitButton.disabled = false;
                        }
                    }
                });
            })();
        </script>
    </body>
</html>
