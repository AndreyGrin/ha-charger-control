<?php

namespace App\Http\Controllers;

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
        ChargingSettingsService $chargingSettings,
        HomeAssistantService $homeAssistant,
    ): RedirectResponse|JsonResponse {
        try {
            $message = match ($action) {
                'plan' => $this->runStrategy(),
                'execute' => $this->runExecution(),
                'stop' => $this->stopCharging($chargingSettings, $homeAssistant),
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

    private function stopCharging(
        ChargingSettingsService $chargingSettings,
        HomeAssistantService $homeAssistant,
    ): string {
        $settings = $chargingSettings->resolve();

        $homeAssistant->stopCharging($settings);

        return 'Charging stopped from the dashboard.';
    }
}
