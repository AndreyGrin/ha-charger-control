<?php

namespace App\Services;

use App\Models\ChargingPlan;
use App\Models\ChargingPlanSlot;
use App\Models\PriceForecast;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ChargingConnectionService
{
    public function __construct(
        private readonly ChargingSettingsService $chargingSettings,
        private readonly HomeAssistantService $homeAssistant,
        private readonly ChargingExecutionService $execution,
        private readonly PriceForecastImportService $forecastImporter,
        private readonly ChargingPlanner $planner,
    ) {}

    public function handle(bool $connected, CarbonImmutable $now): array
    {
        return $connected
            ? $this->handleConnected($now)
            : $this->handleDisconnected($now);
    }

    private function handleConnected(CarbonImmutable $now): array
    {
        $settings = $this->chargingSettings->resolve();
        $settings = $this->homeAssistant->updateSettingFromVehicleSoc($settings);
        $this->forecastImporter->importFromHomeAssistant($this->homeAssistant, $now);

        $forecasts = PriceForecast::query()
            ->where('ends_at', '>', $now)
            ->where(fn ($query) => $query->whereNull('source')->orWhere('source', '!=', 'seeded-demo'))
            ->orderBy('starts_at')
            ->get();

        if ($forecasts->isEmpty()) {
            return [
                'state' => 'connected',
                'message' => 'Vehicle connected, but no forecasts were available for replanning.',
            ];
        }

        $planData = $this->planner->build($settings, $forecasts, $now);
        $plan = $this->planner->persist($settings, $planData);

        return [
            'state' => 'connected',
            'message' => sprintf('Vehicle connected. Rebuilt plan with %d slots.', $plan->slots->count()),
            'plan_id' => $plan->id,
        ];
    }

    private function handleDisconnected(CarbonImmutable $now): array
    {
        $settings = $this->chargingSettings->resolve();
        $currentMeterKwh = $this->homeAssistant->chargerEnergyTotalKwh();
        $this->homeAssistant->stopCharging($settings);

        $interruptedSlot = DB::transaction(function () use ($now, $currentMeterKwh) {
            $slot = $this->execution->interruptActiveSlot($now, $currentMeterKwh, 'cancelled');

            ChargingPlanSlot::query()
                ->whereHas('plan', fn ($query) => $query->where('status', 'planned'))
                ->where('starts_at', '>=', $now)
                ->where('status', 'planned')
                ->update(['status' => 'cancelled']);

            ChargingPlan::query()
                ->where('status', 'planned')
                ->update(['status' => 'cancelled']);

            return $slot;
        });

        return [
            'state' => 'disconnected',
            'message' => $interruptedSlot
                ? sprintf('Vehicle disconnected. Active slot %d stopped and future charges cleared.', $interruptedSlot->id)
                : 'Vehicle disconnected. Charger stopped and future charges cleared.',
        ];
    }
}
