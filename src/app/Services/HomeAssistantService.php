<?php

namespace App\Services;

use App\Models\ChargingSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class HomeAssistantService
{
    public function configuredEntities(): array
    {
        return array_filter(config('home_assistant.entities', []));
    }

    public function allStates(): Collection
    {
        return collect($this->request()->get('/api/states')->throw()->json());
    }

    public function configuredStates(): Collection
    {
        $configuredEntities = $this->configuredEntities();

        if ($configuredEntities === []) {
            return collect();
        }

        $states = $this->allStates()->keyBy('entity_id');

        return collect($configuredEntities)->mapWithKeys(
            fn (string $entityId, string $alias) => [$alias => $states->get($entityId)]
        );
    }

    public function resolveConfiguredEntityId(string $aliasOrEntityId): string
    {
        $configured = config('home_assistant.entities', []);

        if (isset($configured[$aliasOrEntityId]) && is_string($configured[$aliasOrEntityId])) {
            return $configured[$aliasOrEntityId];
        }

        return $aliasOrEntityId;
    }

    public function solarForecastStates(): Collection
    {
        return $this->configuredStates()
            ->only(['solar_forecast_east', 'solar_forecast_west'])
            ->filter();
    }

    public function combinedSolarForecastState(): ?float
    {
        $values = $this->solarForecastStates()
            ->map(fn (array $state) => isset($state['state']) && is_numeric($state['state']) ? (float) $state['state'] : null)
            ->filter(fn (?float $value) => $value !== null);

        if ($values->isEmpty()) {
            return null;
        }

        return round($values->sum(), 3);
    }

    public function solarCurrentHourStates(): Collection
    {
        return $this->configuredStates()
            ->only(['solar_current_hour_east', 'solar_current_hour_west'])
            ->filter();
    }

    public function combinedSolarCurrentHourState(): ?float
    {
        return $this->sumNumericEntityStates($this->solarCurrentHourStates());
    }

    public function solarNextHourStates(): Collection
    {
        return $this->configuredStates()
            ->only(['solar_next_hour_east', 'solar_next_hour_west'])
            ->filter();
    }

    public function combinedSolarNextHourState(): ?float
    {
        return $this->sumNumericEntityStates($this->solarNextHourStates());
    }

    public function entityState(string $entityId): array
    {
        return $this->request()->get("/api/states/{$entityId}")->throw()->json();
    }

    public function entityNumericState(string $entityId): ?float
    {
        $state = $this->entityState($entityId);

        if (! isset($state['state']) || ! is_numeric($state['state'])) {
            return null;
        }

        return (float) $state['state'];
    }

    public function updateSettingFromVehicleSoc(ChargingSetting $settings): ChargingSetting
    {
        $vehicleSocEntityId = config('home_assistant.entities.vehicle_soc');

        if (! is_string($vehicleSocEntityId) || $vehicleSocEntityId === '') {
            return $settings;
        }

        $soc = $this->entityNumericState($vehicleSocEntityId);

        if ($soc === null) {
            return $settings;
        }

        $settings->forceFill([
            'current_soc_percent' => $soc,
        ])->save();

        return $settings->fresh();
    }

    public function chargerEnergyTotalKwh(): ?float
    {
        $entityId = config('home_assistant.entities.charger_energy_total');

        if (! is_string($entityId) || $entityId === '') {
            return null;
        }

        return $this->entityNumericState($entityId);
    }

    public function setMaximumCurrent(ChargingSetting $settings, int $amps): void
    {
        $clampedAmps = max($settings->charger_min_current_amps, min($settings->charger_max_current_amps, $amps));

        $this->callService('number', 'set_value', [
            'entity_id' => $settings->ha_maximum_current_entity_id,
            'value' => $clampedAmps,
        ]);
    }

    public function startCharging(ChargingSetting $settings): void
    {
        $this->callService('switch', 'turn_on', [
            'entity_id' => $settings->ha_charge_control_entity_id,
        ]);
    }

    public function stopCharging(ChargingSetting $settings): void
    {
        $this->callService('switch', 'turn_off', [
            'entity_id' => $settings->ha_charge_control_entity_id,
        ]);
    }

    public function applyChargingCommand(ChargingSetting $settings, int $amps, bool $enabled): void
    {
        $this->setMaximumCurrent($settings, $amps);

        if ($enabled) {
            $this->startCharging($settings);

            return;
        }

        $this->stopCharging($settings);
    }

    private function callService(string $domain, string $service, array $payload): void
    {
        $this->request()
            ->post("/api/services/{$domain}/{$service}", $payload)
            ->throw();
    }

    private function sumNumericEntityStates(Collection $states): ?float
    {
        $values = $states
            ->map(fn (array $state) => isset($state['state']) && is_numeric($state['state']) ? (float) $state['state'] : null)
            ->filter(fn (?float $value) => $value !== null);

        if ($values->isEmpty()) {
            return null;
        }

        return round($values->sum(), 3);
    }

    private function request(): PendingRequest
    {
        $url = rtrim((string) config('home_assistant.url'), '/');
        $token = (string) config('home_assistant.token');

        if ($url === '' || $token === '') {
            throw new InvalidArgumentException('Home Assistant URL or token is not configured.');
        }

        return Http::baseUrl(rtrim((string) config('home_assistant.url'), '/'))
            ->timeout((int) config('home_assistant.timeout', 10))
            ->acceptJson()
            ->withToken((string) config('home_assistant.token'));
    }
}
