<?php

namespace App\Http\Controllers;

use App\Services\ChargingConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HomeAssistantWebhookController extends Controller
{
    public function connection(
        Request $request,
        string $secret,
        ChargingConnectionService $connectionService,
    ): JsonResponse {
        abort_unless(
            hash_equals((string) config('charging.webhooks.connection_secret'), $secret),
            Response::HTTP_FORBIDDEN,
        );

        $connected = $this->resolveConnectionState($request);
        $result = $connectionService->handle($connected, now()->toImmutable());

        return response()->json([
            'ok' => true,
            'connected' => $connected,
            ...$result,
        ]);
    }

    private function resolveConnectionState(Request $request): bool
    {
        if ($request->has('connected')) {
            return filter_var($request->input('connected'), FILTER_VALIDATE_BOOLEAN);
        }

        $status = strtolower(trim((string) $request->input('status', $request->input('state', ''))));

        return in_array($status, ['on', 'connected', 'plugged', 'plugged_in', 'true', '1'], true);
    }
}
