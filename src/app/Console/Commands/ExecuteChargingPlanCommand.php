<?php

namespace App\Console\Commands;

use App\Services\ChargingExecutionService;
use App\Services\ChargingSettingsService;
use App\Services\HomeAssistantService;
use Illuminate\Console\Command;
use Throwable;

class ExecuteChargingPlanCommand extends Command
{
    protected $signature = 'app:execute-charging-plan {--dry-run : Do not send commands to Home Assistant}';

    protected $description = 'Execute the current charging plan by turning the charger on or off in Home Assistant.';

    public function handle(
        ChargingExecutionService $execution,
        ChargingSettingsService $chargingSettings,
        HomeAssistantService $homeAssistant,
    ): int {
        $settings = $chargingSettings->resolve();
        $currentMeterKwh = null;

        try {
            $settings = $homeAssistant->updateSettingFromVehicleSoc($settings);
        } catch (Throwable $exception) {
            $this->warn(sprintf('Vehicle SoC sync skipped: %s', $exception->getMessage()));
        }

        try {
            $currentMeterKwh = $homeAssistant->chargerEnergyTotalKwh();
        } catch (Throwable $exception) {
            $this->warn(sprintf('Energy meter sync skipped: %s', $exception->getMessage()));
        }

        $decision = $execution->execute($settings, now()->toImmutable(), $currentMeterKwh);

        if (($decision['action'] ?? null) === 'charge') {
            if (! $this->option('dry-run')) {
                try {
                    $homeAssistant->applyChargingCommand($settings, (int) $decision['amps'], true);
                } catch (Throwable $exception) {
                    $this->error($exception->getMessage());

                    return self::FAILURE;
                }
            }

            $this->info(sprintf(
                'Charging at %dA. %s',
                $decision['amps'],
                $decision['reason'],
            ));

            return self::SUCCESS;
        }

        if (($decision['action'] ?? null) === 'stop') {
            if (! $this->option('dry-run')) {
                try {
                    $homeAssistant->stopCharging($settings);
                } catch (Throwable $exception) {
                    $this->error($exception->getMessage());

                    return self::FAILURE;
                }
            }

            $this->info($decision['reason']);

            return self::SUCCESS;
        }

        $this->info($decision['reason'] ?? 'Nothing to do.');

        return self::SUCCESS;
    }
}
