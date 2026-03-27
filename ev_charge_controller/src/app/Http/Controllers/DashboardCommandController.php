<?php

namespace App\Http\Controllers;

use App\Services\DashboardArtisanRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class DashboardCommandController extends Controller
{
    public function __invoke(Request $request, DashboardArtisanRunner $runner): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'command' => ['required', 'string', 'max:255'],
        ]);

        try {
            $result = $runner->run($validated['command']);

            if ($request->expectsJson()) {
                return response()->json($result);
            }

            return redirect()
                ->back(302, [], '/')
                ->with('artisan_result', $result);
        } catch (Throwable $exception) {
            $result = [
                'command' => $validated['command'],
                'exit_code' => 1,
                'output' => $exception->getMessage(),
            ];

            if ($request->expectsJson()) {
                return response()->json($result, 422);
            }

            return redirect()
                ->back(302, [], '/')
                ->with('artisan_result', $result);
        }
    }
}
