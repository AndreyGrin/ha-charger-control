<?php

namespace App\Services;

use App\Models\ChargingPlan;
use App\Models\ChargingPlanSlot;
use App\Models\ChargingSession;
use App\Models\ChargingSetting;
use Carbon\CarbonImmutable;

class ChargingExecutionService
{
    public function execute(ChargingSetting $settings, CarbonImmutable $now, ?float $currentMeterKwh = null): array
    {
        $this->finalizeEndedSlots($now, $currentMeterKwh);

        $plan = ChargingPlan::query()
            ->with('slots')
            ->where('status', 'planned')
            ->latest('generated_at')
            ->first();

        if ($plan === null) {
            return [
                'action' => 'noop',
                'reason' => 'No active plan found.',
            ];
        }

        $activeSlot = $plan->slots
            ->first(fn (ChargingPlanSlot $slot) => $slot->starts_at->lessThanOrEqualTo($now) && $slot->ends_at->greaterThan($now));

        if ($activeSlot === null || $activeSlot->allocated_energy_kwh <= 0) {
            $this->markMissedSlots($plan, $now);

            return [
                'action' => 'stop',
                'reason' => 'No scheduled charging slot is active right now.',
            ];
        }

        $this->startSlotIfNeeded($activeSlot, $now, $currentMeterKwh);

        $amps = $this->resolveTargetAmps($settings, $activeSlot);

        return [
            'action' => 'charge',
            'amps' => $amps,
            'slot_id' => $activeSlot->id,
            'starts_at' => $activeSlot->starts_at,
            'ends_at' => $activeSlot->ends_at,
            'reason' => sprintf(
                'Active slot %d from %s to %s.',
                $activeSlot->id,
                $activeSlot->starts_at->format('H:i'),
                $activeSlot->ends_at->format('H:i'),
            ),
        ];
    }

    public function interruptActiveSlot(CarbonImmutable $now, ?float $currentMeterKwh = null, string $status = 'stopped'): ?ChargingPlanSlot
    {
        $slot = ChargingPlanSlot::query()
            ->whereNotNull('execution_started_at')
            ->whereNull('execution_finished_at')
            ->where('status', 'active')
            ->latest('starts_at')
            ->first();

        if ($slot === null) {
            return null;
        }

        $executedEnergy = $slot->executed_energy_kwh;

        if ($currentMeterKwh !== null && $slot->meter_started_kwh !== null) {
            $executedEnergy = max(0, round($currentMeterKwh - $slot->meter_started_kwh, 3));
        }

        $slot->forceFill([
            'status' => $executedEnergy > 0 ? $status : 'cancelled',
            'execution_finished_at' => $now,
            'meter_finished_kwh' => $currentMeterKwh,
            'executed_energy_kwh' => $executedEnergy,
        ])->save();

        $this->storeChargingSession($slot, $executedEnergy, $status);

        return $slot->fresh();
    }

    private function resolveTargetAmps(ChargingSetting $settings, ChargingPlanSlot $slot): int
    {
        $ratio = $settings->charger_power_kw > 0
            ? min(1, max(0, $slot->recommended_power_kw / $settings->charger_power_kw))
            : 1;

        $amps = (int) round($settings->charger_max_current_amps * $ratio);

        return max($settings->charger_min_current_amps, min($settings->charger_max_current_amps, $amps));
    }

    private function startSlotIfNeeded(ChargingPlanSlot $slot, CarbonImmutable $now, ?float $currentMeterKwh): void
    {
        if ($slot->execution_started_at !== null) {
            if ($slot->status !== 'active') {
                $slot->forceFill(['status' => 'active'])->save();
            }

            return;
        }

        $slot->forceFill([
            'status' => 'active',
            'execution_started_at' => $now,
            'meter_started_kwh' => $currentMeterKwh,
        ])->save();
    }

    private function finalizeEndedSlots(CarbonImmutable $now, ?float $currentMeterKwh): void
    {
        ChargingPlanSlot::query()
            ->whereNotNull('execution_started_at')
            ->whereNull('execution_finished_at')
            ->where('ends_at', '<=', $now)
            ->get()
            ->each(function (ChargingPlanSlot $slot) use ($now, $currentMeterKwh): void {
                $executedEnergy = $slot->executed_energy_kwh;

                if ($currentMeterKwh !== null && $slot->meter_started_kwh !== null) {
                    $executedEnergy = max(0, round($currentMeterKwh - $slot->meter_started_kwh, 3));
                }

                $slot->forceFill([
                    'status' => 'completed',
                    'execution_finished_at' => $now,
                    'meter_finished_kwh' => $currentMeterKwh,
                    'executed_energy_kwh' => $executedEnergy,
                ])->save();

                $this->storeChargingSession($slot, $executedEnergy, 'completed');
            });
    }

    private function markMissedSlots(ChargingPlan $plan, CarbonImmutable $now): void
    {
        $plan->slots
            ->filter(fn (ChargingPlanSlot $slot) => $slot->ends_at->lessThanOrEqualTo($now) && $slot->execution_started_at === null && $slot->status === 'planned')
            ->each(fn (ChargingPlanSlot $slot) => $slot->forceFill(['status' => 'missed'])->save());
    }

    private function storeChargingSession(ChargingPlanSlot $slot, float $executedEnergy, string $status): void
    {
        if ($executedEnergy <= 0) {
            return;
        }

        $estimatedCost = round($executedEnergy * $slot->effective_price_per_kwh, 2);

        ChargingSession::query()->updateOrCreate(
            [
                'started_at' => $slot->execution_started_at ?? $slot->starts_at,
                'ended_at' => $slot->execution_finished_at ?? $slot->ends_at,
            ],
            [
                'status' => $status,
                'energy_kwh' => $executedEnergy,
                'average_price_per_kwh' => $slot->effective_price_per_kwh,
                'estimated_peak_price_per_kwh' => $slot->market_price_per_kwh,
                'estimated_cost' => $estimatedCost,
                'estimated_peak_cost' => round($executedEnergy * $slot->market_price_per_kwh, 2),
                'savings_amount' => round(max(0, ($executedEnergy * $slot->market_price_per_kwh) - $estimatedCost), 2),
                'meta' => [
                    'source' => 'execution-meter',
                    'charging_plan_slot_id' => $slot->id,
                    'charging_plan_id' => $slot->charging_plan_id,
                ],
            ],
        );
    }
}
