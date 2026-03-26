<?php

namespace App\Console\Commands;

use App\Models\ChargingPlan;
use App\Models\ChargingPlanSlot;
use App\Services\ChargingExecutionService;
use App\Services\ChargingSettingsService;
use App\Services\HomeAssistantService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

class ResetChargingPlanCommand extends Command
{
    protected $signature = 'app:reset-charging-plan
        {--replan : Immediately rebuild a fresh charging plan after reset}
        {--keep-charger-running : Do not force the charger off during reset}';

    protected $description = 'Cancel current planned slots, stop charging, and optionally rebuild a fresh plan.';

    public function handle(
        ChargingExecutionService $execution,
        ChargingSettingsService $chargingSettings,
        HomeAssistantService $homeAssistant,
    ): int {
        $settings = $chargingSettings->resolve();
        $now = now()->toImmutable();
        $currentMeterKwh = null;

        try {
            $currentMeterKwh = $homeAssistant->chargerEnergyTotalKwh();
        } catch (Throwable $exception) {
            $this->warn(sprintf('Energy meter sync skipped: %s', $exception->getMessage()));
        }

        DB::transaction(function () use ($execution, $now, $currentMeterKwh): void {
            $execution->interruptActiveSlot($now, $currentMeterKwh, 'cancelled');

            ChargingPlanSlot::query()
                ->whereIn('status', ['planned', 'active'])
                ->update(['status' => 'cancelled']);

            ChargingPlan::query()
                ->whereIn('status', ['planned'])
                ->update(['status' => 'cancelled']);
        });

        if (! $this->option('keep-charger-running')) {
            try {
                $homeAssistant->stopCharging($settings);
            } catch (Throwable $exception) {
                $this->warn(sprintf('Stop charger skipped: %s', $exception->getMessage()));
            }
        }

        $this->info('Cancelled current charging plan state.');

        if (! $this->option('replan')) {
            return self::SUCCESS;
        }

        $exitCode = Artisan::call('app:evaluate-charging-strategy');
        $this->line(trim(Artisan::output()));

        return $exitCode;
    }
}
