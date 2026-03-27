<?php

namespace App\Services;

use App\Models\ChargingPlan;
use App\Models\ChargingSetting;
use App\Models\PriceForecast;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ChargingPlanner
{
    public function build(ChargingSetting $settings, Collection $forecasts, CarbonImmutable $now): array
    {
        $deadline = $this->resolveDeadline($settings, $now);
        $currentEnergy = $settings->battery_capacity_kwh * ($settings->current_soc_percent / 100);
        $minimumEnergy = max(0, ($settings->battery_capacity_kwh * ($settings->daily_minimum_soc_percent / 100)) - $currentEnergy);
        $targetEnergy = max($minimumEnergy, ($settings->battery_capacity_kwh * ($settings->target_soc_percent / 100)) - $currentEnergy);

        $slots = $forecasts
            ->sortBy('starts_at')
            ->values()
            ->map(fn (PriceForecast $forecast) => $this->makeSlot($settings, $forecast, $deadline, $now));

        $slots = $this->allocateEnergy($slots, $minimumEnergy, true, 'minimum');
        $remainingForTarget = max(0, round($targetEnergy - $slots->sum('allocated_energy_kwh'), 3));
        $slots = $this->allocateEnergy($slots, $remainingForTarget, false, 'target');

        $selectedSlots = $slots
            ->filter(fn (array $slot) => $slot['allocated_energy_kwh'] > 0)
            ->values();

        $plannedEnergy = round($selectedSlots->sum('allocated_energy_kwh'), 3);
        $estimatedCost = round($selectedSlots->sum('estimated_cost'), 2);

        return [
            'plan' => [
                'generated_at' => $now,
                'deadline_at' => $deadline,
                'starts_at' => $selectedSlots->min('starts_at'),
                'ends_at' => $selectedSlots->max('ends_at'),
                'minimum_energy_kwh' => round($minimumEnergy, 3),
                'target_energy_kwh' => round($targetEnergy, 3),
                'planned_energy_kwh' => $plannedEnergy,
                'estimated_cost' => $estimatedCost,
                'average_price_per_kwh' => $plannedEnergy > 0 ? round($estimatedCost / $plannedEnergy, 4) : 0.0,
                'notes' => [
                    'minimum_window_count' => $selectedSlots->where('selection_bucket', 'minimum')->count(),
                    'solar_energy_kwh' => round($selectedSlots->sum('allocated_solar_energy_kwh'), 3),
                    'import_energy_kwh' => round($selectedSlots->sum('allocated_import_energy_kwh'), 3),
                    'cheapest_effective_price' => $selectedSlots->min('effective_price_per_kwh'),
                ],
            ],
            'slots' => $selectedSlots,
            'all_slots' => $slots,
        ];
    }

    public function persist(ChargingSetting $settings, array $planData): ChargingPlan
    {
        ChargingPlan::query()
            ->where('status', 'planned')
            ->update(['status' => 'superseded']);

        $plan = ChargingPlan::query()->create([
            'charging_setting_id' => $settings->id,
            ...$planData['plan'],
            'status' => 'planned',
        ]);

        $plan->slots()->createMany(
            $planData['slots']->map(fn (array $slot) => collect($slot)
                ->except(['capacity_kwh', 'remaining_capacity_kwh', 'remaining_solar_kwh', 'before_deadline'])
                ->all()
            )->all()
        );

        return $plan->load('slots');
    }

    private function allocateEnergy(Collection $slots, float $energyNeeded, bool $beforeDeadlineFirst, string $bucket): Collection
    {
        if ($energyNeeded <= 0) {
            return $slots;
        }

        $candidateIndexes = $slots
            ->map(fn (array $slot, int $index) => ['index' => $index, ...$slot])
            ->sort(function (array $left, array $right) use ($beforeDeadlineFirst): int {
                $deadlineComparison = ($beforeDeadlineFirst ? ($left['before_deadline'] ? 0 : 1) : 0)
                    <=> ($beforeDeadlineFirst ? ($right['before_deadline'] ? 0 : 1) : 0);

                if ($deadlineComparison !== 0) {
                    return $deadlineComparison;
                }

                $effectivePriceComparison = $left['effective_price_per_kwh'] <=> $right['effective_price_per_kwh'];

                if ($effectivePriceComparison !== 0) {
                    return $effectivePriceComparison;
                }

                $marketPriceComparison = $left['market_price_per_kwh'] <=> $right['market_price_per_kwh'];

                if ($marketPriceComparison !== 0) {
                    return $marketPriceComparison;
                }

                return $left['starts_at']->getTimestamp() <=> $right['starts_at']->getTimestamp();
            })
            ->pluck('index');

        foreach ($candidateIndexes as $index) {
            if ($energyNeeded <= 0) {
                break;
            }

            $slot = $slots->get($index);

            if ($slot['remaining_capacity_kwh'] <= 0) {
                continue;
            }

            $allocatedEnergy = min($energyNeeded, $slot['remaining_capacity_kwh']);
            $solarEnergy = min($allocatedEnergy, $slot['remaining_solar_kwh']);
            $importEnergy = $allocatedEnergy - $solarEnergy;

            $slot['remaining_capacity_kwh'] = round($slot['remaining_capacity_kwh'] - $allocatedEnergy, 3);
            $slot['remaining_solar_kwh'] = round($slot['remaining_solar_kwh'] - $solarEnergy, 3);
            $slot['allocated_energy_kwh'] = round($slot['allocated_energy_kwh'] + $allocatedEnergy, 3);
            $slot['allocated_solar_energy_kwh'] = round($slot['allocated_solar_energy_kwh'] + $solarEnergy, 3);
            $slot['allocated_import_energy_kwh'] = round($slot['allocated_import_energy_kwh'] + $importEnergy, 3);
            $slot['estimated_cost'] = round($slot['estimated_cost'] + ($importEnergy * $slot['import_price_per_kwh']), 2);
            $slot['selection_bucket'] = $slot['selection_bucket'] === null ? $bucket : ($slot['selection_bucket'] === $bucket ? $bucket : 'mixed');
            $slot['rationale'] = $this->buildRationale($slot);

            $slots->put($index, $slot);
            $energyNeeded = round($energyNeeded - $allocatedEnergy, 3);
        }

        return $slots;
    }

    private function makeSlot(
        ChargingSetting $settings,
        PriceForecast $forecast,
        CarbonImmutable $deadline,
        CarbonImmutable $now,
    ): array {
        $usableStart = $forecast->starts_at->greaterThan($now) ? $forecast->starts_at : $now;
        $durationMinutes = max(0, $usableStart->diffInMinutes($forecast->ends_at, false));
        $durationHours = max(0, $durationMinutes / 60);
        $capacity = round($settings->charger_power_kw * $durationHours, 3);
        $solar = min($capacity, max(0, $forecast->solar_surplus_kwh));
        $gridTariff = $this->gridTariffFor($settings, $forecast->starts_at);
        $importPrice = round(($forecast->market_price_per_kwh + $gridTariff) / max(0.1, $settings->charger_efficiency), 4);
        $effectivePrice = $capacity > 0
            ? round($importPrice * (max(0, $capacity - $solar) / $capacity), 4)
            : $importPrice;

        return [
            'starts_at' => $forecast->starts_at,
            'ends_at' => $forecast->ends_at,
            'market_price_per_kwh' => $forecast->market_price_per_kwh,
            'grid_price_per_kwh' => $gridTariff,
            'import_price_per_kwh' => $importPrice,
            'effective_price_per_kwh' => $effectivePrice,
            'solar_surplus_kwh' => $solar,
            'allocated_energy_kwh' => 0.0,
            'allocated_import_energy_kwh' => 0.0,
            'allocated_solar_energy_kwh' => 0.0,
            'recommended_power_kw' => $settings->charger_power_kw,
            'estimated_cost' => 0.0,
            'selection_bucket' => null,
            'rationale' => '',
            'capacity_kwh' => $capacity,
            'remaining_capacity_kwh' => $capacity,
            'remaining_solar_kwh' => $solar,
            'before_deadline' => $forecast->starts_at->lessThanOrEqualTo($deadline),
        ];
    }

    private function buildRationale(array $slot): string
    {
        if ($slot['allocated_solar_energy_kwh'] > 0) {
            return sprintf(
                'Solar covers %.1f kWh and limits imports to the cheapest remainder.',
                $slot['allocated_solar_energy_kwh'],
            );
        }

        return $slot['grid_price_per_kwh'] <= 0.055
            ? 'Low-tariff import window selected for minimum delivered cost.'
            : 'Selected because the spot price still ranks well in the current horizon.';
    }

    private function resolveDeadline(ChargingSetting $settings, CarbonImmutable $now): CarbonImmutable
    {
        $deadline = $now->setTimeFromTimeString($settings->daily_minimum_deadline);

        return $deadline->lessThanOrEqualTo($now) ? $deadline->addDay() : $deadline;
    }

    private function gridTariffFor(ChargingSetting $settings, CarbonImmutable $startsAt): float
    {
        if ($startsAt->isWeekend()) {
            return $settings->grid_weekend_rate_per_kwh;
        }

        $dayStartsAt = $startsAt->setTimeFromTimeString($settings->day_rate_starts_at);
        $nightStartsAt = $startsAt->setTimeFromTimeString($settings->night_rate_starts_at);

        return $startsAt->greaterThanOrEqualTo($dayStartsAt) && $startsAt->lessThan($nightStartsAt)
            ? $settings->grid_day_rate_per_kwh
            : $settings->grid_night_rate_per_kwh;
    }
}
