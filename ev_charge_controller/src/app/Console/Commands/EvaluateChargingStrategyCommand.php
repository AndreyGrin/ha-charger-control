<?php

namespace App\Console\Commands;

use App\Models\PriceForecast;
use App\Services\ChargingPlanner;
use App\Services\ChargingSettingsService;
use App\Services\HomeAssistantService;
use App\Services\PriceForecastImportService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class EvaluateChargingStrategyCommand extends Command
{
    protected $signature = 'app:evaluate-charging-strategy
        {--horizon-minutes=720 : Planning horizon in minutes from now}
        {--until= : Absolute end time today, for example 17:00}';

    protected $description = 'Build the next cheapest charging plan from tariffs, Nordpool prices, and solar surplus.';

    public function handle(
        ChargingPlanner $planner,
        PriceForecastImportService $forecastImporter,
        ChargingSettingsService $chargingSettings,
        HomeAssistantService $homeAssistant,
    ): int {
        $now = now()->toImmutable();
        $horizonEndsAt = $this->resolveHorizonEnd($now);
        $settings = $chargingSettings->resolve();

        try {
            $settings = $homeAssistant->updateSettingFromVehicleSoc($settings);
        } catch (Throwable $exception) {
            $this->warn(sprintf('Home Assistant sync skipped: %s', $exception->getMessage()));
        }

        try {
            $forecastImporter->importFromHomeAssistant($homeAssistant, $now);
        } catch (Throwable $exception) {
            $this->warn(sprintf('Home Assistant forecast import skipped: %s', $exception->getMessage()));
        }

        $forecasts = PriceForecast::query()
            ->where('ends_at', '>', $now)
            ->where('starts_at', '<', $horizonEndsAt)
            ->where(fn ($query) => $query->whereNull('source')->orWhere('source', '!=', 'seeded-demo'))
            ->orderBy('starts_at')
            ->get();

        if ($forecasts->isEmpty()) {
            $this->warn('No price forecasts available.');

            return self::FAILURE;
        }

        $planData = $planner->build($settings, $forecasts, $now);
        $plan = $planner->persist($settings, $planData);

        $this->info(sprintf(
            'Planned %.1f kWh across %d windows until %s at an estimated cost of EUR %.2f.',
            $plan->planned_energy_kwh,
            $plan->slots->count(),
            $horizonEndsAt->format('H:i'),
            $plan->estimated_cost,
        ));

        return self::SUCCESS;
    }

    private function resolveHorizonEnd(CarbonImmutable $now): CarbonImmutable
    {
        $until = $this->option('until');

        if (is_string($until) && $until !== '') {
            $endsAt = $now->setTimeFromTimeString($until);

            return $endsAt->lessThanOrEqualTo($now) ? $endsAt->addDay() : $endsAt;
        }

        $horizonMinutes = max(15, (int) $this->option('horizon-minutes'));

        return $now->addMinutes($horizonMinutes);
    }
}
