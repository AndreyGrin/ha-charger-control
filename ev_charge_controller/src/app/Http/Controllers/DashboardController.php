<?php

namespace App\Http\Controllers;

use App\Models\ChargingPlan;
use App\Models\ChargingPlanSlot;
use App\Models\ChargingSession;
use App\Models\PriceForecast;
use App\Services\ChargingPlanner;
use App\Services\ChargingSettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function __invoke(ChargingPlanner $planner, ChargingSettingsService $chargingSettings): View
    {
        $now = now()->toImmutable();
        $settings = $chargingSettings->resolve();
        $forecasts = PriceForecast::query()
            ->where('starts_at', '>=', now()->startOfHour())
            ->where(fn ($query) => $query->whereNull('source')->orWhere('source', '!=', 'seeded-demo'))
            ->orderBy('starts_at')
            ->limit(36)
            ->get();

        $currentPlan = ChargingPlan::query()->with('slots')->latest('generated_at')->first();
        $preview = null;

        if ($settings && $forecasts->isNotEmpty()) {
            $preview = $planner->build($settings, $forecasts, $now);
        }

        $displayPlan = $currentPlan;
        $displaySlots = $currentPlan?->slots ?? collect();

        if ($displayPlan === null && $preview !== null) {
            $displayPlan = $preview['plan'];
            $displaySlots = $preview['slots'];
        }

        $scheduleWindowStart = $now->startOfHour();
        $scheduleWindowEnd = $scheduleWindowStart->addHours(36);
        $selectedSlotStarts = ChargingPlanSlot::query()
            ->with('plan')
            ->where('starts_at', '>=', $scheduleWindowStart)
            ->where('starts_at', '<', $scheduleWindowEnd)
            ->get()
            ->sortByDesc(fn (ChargingPlanSlot $slot) => [
                $slot->executed_energy_kwh > 0 ? 1 : 0,
                $slot->execution_started_at?->getTimestamp() ?? 0,
                $slot->plan?->generated_at?->getTimestamp() ?? 0,
            ])
            ->unique(fn (ChargingPlanSlot $slot) => $slot->starts_at->toIso8601String())
            ->keyBy(fn (ChargingPlanSlot $slot) => $slot->starts_at->toIso8601String());

        $realSessions = ChargingSession::query()
            ->latest('started_at')
            ->get()
            ->reject(fn (ChargingSession $session) => data_get($session->meta, 'source') === 'seeded-demo')
            ->values();

        $recentSessions = $realSessions->take(8);
        $historyTotals = (object) [
            'energy_kwh' => $realSessions->sum('energy_kwh'),
            'estimated_cost' => $realSessions->sum('estimated_cost'),
            'savings_amount' => $realSessions->sum('savings_amount'),
        ];
        $timeline = $this->buildTimeline($displaySlots, $realSessions, $now);

        return view('dashboard', [
            'settings' => $settings,
            'forecasts' => $forecasts,
            'displayPlan' => $displayPlan,
            'displaySlots' => $displaySlots,
            'selectedSlotStarts' => $selectedSlotStarts,
            'recentSessions' => $recentSessions,
            'historyTotals' => $historyTotals,
            'timeline' => $timeline,
            'usingPreviewPlan' => $currentPlan === null && $preview !== null,
        ]);
    }

    private function buildTimeline(Collection $displaySlots, Collection $sessions, CarbonImmutable $now): Collection
    {
        $windowStart = $now->subHours(8)->startOfHour();
        $windowEnd = $now->addHours(24)->startOfHour();

        $bins = collect();

        for ($cursor = $windowStart; $cursor->lessThan($windowEnd); $cursor = $cursor->addMinutes(15)) {
            $binStart = $cursor;
            $binEnd = $cursor->addMinutes(15);

            $executed = $sessions->sum(function (ChargingSession $session) use ($binStart, $binEnd) {
                return $this->energyOverlap(
                    $session->started_at,
                    $session->ended_at ?? $session->started_at,
                    $session->energy_kwh,
                    $binStart,
                    $binEnd,
                );
            });

            $planned = $displaySlots->sum(function ($slot) use ($binStart, $binEnd) {
                $slotStart = data_get($slot, 'starts_at');
                $slotEnd = data_get($slot, 'ends_at');
                $allocated = (float) data_get($slot, 'allocated_energy_kwh', 0);

                if (! $slotStart || ! $slotEnd) {
                    return 0;
                }

                return $this->energyOverlap($slotStart, $slotEnd, $allocated, $binStart, $binEnd);
            });

            $bins->push([
                'starts_at' => $binStart,
                'ends_at' => $binEnd,
                'executed_kwh' => round($executed, 3),
                'planned_kwh' => round($planned, 3),
                'is_past' => $binEnd->lessThanOrEqualTo($now),
                'is_current' => $binStart->lessThanOrEqualTo($now) && $binEnd->greaterThan($now),
            ]);
        }

        $maxEnergy = max(1, (float) $bins->max(fn (array $bin) => max($bin['executed_kwh'], $bin['planned_kwh'])));

        return $bins->map(function (array $bin) use ($maxEnergy) {
            $bin['executed_height'] = $bin['executed_kwh'] > 0
                ? max(6, (int) round(($bin['executed_kwh'] / $maxEnergy) * 84))
                : 0;
            $bin['planned_height'] = $bin['planned_kwh'] > 0
                ? max(6, (int) round(($bin['planned_kwh'] / $maxEnergy) * 84))
                : 0;

            return $bin;
        });
    }

    private function energyOverlap(
        CarbonImmutable $sourceStart,
        CarbonImmutable $sourceEnd,
        float $sourceEnergy,
        CarbonImmutable $binStart,
        CarbonImmutable $binEnd,
    ): float {
        if ($sourceEnergy <= 0 || $sourceEnd->lessThanOrEqualTo($sourceStart)) {
            return 0;
        }

        $overlapStart = $sourceStart->greaterThan($binStart) ? $sourceStart : $binStart;
        $overlapEnd = $sourceEnd->lessThan($binEnd) ? $sourceEnd : $binEnd;

        if ($overlapEnd->lessThanOrEqualTo($overlapStart)) {
            return 0;
        }

        $overlapMinutes = $overlapStart->diffInMinutes($overlapEnd);
        $sourceMinutes = max(1, $sourceStart->diffInMinutes($sourceEnd));

        return $sourceEnergy * ($overlapMinutes / $sourceMinutes);
    }
}
