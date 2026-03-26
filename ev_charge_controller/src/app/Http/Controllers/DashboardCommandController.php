<?php

namespace App\Http\Controllers;

use App\Services\DashboardArtisanRunner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class DashboardCommandController extends Controller
{
    public function __invoke(Request $request, DashboardArtisanRunner $runner): RedirectResponse
    {
        $validated = $request->validate([
            'command' => ['required', 'string', 'max:255'],
        ]);

        try {
            $result = $runner->run($validated['command']);

            return redirect()
                ->back(302, [], '/')
                ->with('artisan_result', $result);
        } catch (Throwable $exception) {
            return redirect()
                ->back(302, [], '/')
                ->with('artisan_result', [
                    'command' => $validated['command'],
                    'exit_code' => 1,
                    'output' => $exception->getMessage(),
                ]);
        }
    }
}
