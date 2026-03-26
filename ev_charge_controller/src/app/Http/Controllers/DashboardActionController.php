<?php

namespace App\Http\Controllers;

use App\Services\ChargingExecutionService;
use App\Services\ChargingSettingsService;
use App\Services\HomeAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class DashboardActionController extends Controller
{
    public function __invoke(
        Request $request,
        string $action,
        ChargingExecutionService $execution,
        ChargingSettingsService $chargingSettings,
        HomeAssistantService $homeAssistant,
    ): RedirectResponse|JsonResponse {
        try {
            $message = match ($action) {
                'plan' => $this->runStrategy(),
                'execute' => $this->runExecution(),
                'reset' => $this->resetPlan(),
                'stop' => $this->stopCharging($execution, $chargingSettings, $homeAssistant),
                default => abort(404),
            };

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'message' => $message,
                ]);
            }

            return redirect()
                ->back(302, [], '/')
                ->with('dashboard_status', $message);
        } catch (Throwable $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => $exception->getMessage(),
                ], 500);
            }

            return redirect()
                ->back(302, [], '/')
                ->with('dashboard_error', $exception->getMessage());
        }
    }

    private function runStrategy(): string
    {
        Artisan::call('app:evaluate-charging-strategy');

        $output = trim(Artisan::output());

        return $output !== '' ? $output : 'Charging strategy refreshed.';
    }

    private function runExecution(): string
    {
        Artisan::call('app:execute-charging-plan');

        $output = trim(Artisan::output());

        return $output !== '' ? $output : 'Execution command completed.';
    }

    private function resetPlan(): string
    {
        Artisan::call('app:reset-charging-plan', ['--replan' => true]);

        $output = trim(Artisan::output());

        return $output !== '' ? $output : 'Charging plan reset and rebuilt.';
    }

    private function stopCharging(
        ChargingExecutionService $execution,
        ChargingSettingsService $chargingSettings,
        HomeAssistantService $homeAssistant,
    ): string {
        $settings = $chargingSettings->resolve();
        $currentMeterKwh = null;

        try {
            $currentMeterKwh = $homeAssistant->chargerEnergyTotalKwh();
        } catch (Throwable) {
            $currentMeterKwh = null;
        }

        $stoppedSlot = $execution->interruptActiveSlot(now()->toImmutable(), $currentMeterKwh, 'stopped');

        $homeAssistant->stopCharging($settings);

        if ($stoppedSlot !== null) {
            return sprintf(
                'Charging stopped from the dashboard. Recorded %.2f kWh before stop.',
                (float) $stoppedSlot->executed_energy_kwh,
            );
        }

        return 'Charging stopped from the dashboard.';
    }
}
