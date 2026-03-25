<?php

namespace App\Console\Commands;

use App\Services\HomeAssistantService;
use Illuminate\Console\Command;
use Throwable;

class CheckHomeAssistantCommand extends Command
{
    protected $signature = 'app:check-home-assistant';

    protected $description = 'Validate the Home Assistant API connection and dump the configured entity states.';

    public function handle(HomeAssistantService $homeAssistant): int
    {
        try {
            $states = $homeAssistant->configuredStates();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($states->isEmpty()) {
            $this->warn('Home Assistant is reachable, but no entity IDs are configured.');

            return self::SUCCESS;
        }

        $this->table(
            ['Alias', 'Entity ID', 'State', 'Updated'],
            $states->map(function (?array $state, string $alias): array {
                return [
                    $alias,
                    $state['entity_id'] ?? 'missing',
                    $state['state'] ?? 'missing',
                    $state['last_updated'] ?? 'missing',
                ];
            })->values()->all()
        );

        return self::SUCCESS;
    }
}
