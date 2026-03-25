<?php

namespace App\Console\Commands;

use App\Services\HomeAssistantService;
use Illuminate\Console\Command;
use Throwable;

class DumpHomeAssistantEntityCommand extends Command
{
    protected $signature = 'app:dump-home-assistant-entity
        {entity? : Config alias or exact Home Assistant entity_id}
        {--pretty : Pretty-print the JSON output}';

    protected $description = 'Dump raw Home Assistant entity JSON for one configured entity or all configured entities.';

    public function handle(HomeAssistantService $homeAssistant): int
    {
        try {
            $entity = $this->argument('entity');

            if (is_string($entity) && $entity !== '') {
                $entityId = $homeAssistant->resolveConfiguredEntityId($entity);
                $payload = $homeAssistant->entityState($entityId);

                $this->line($this->encodeJson([$entity => $payload]));

                return self::SUCCESS;
            }

            $states = $homeAssistant->configuredStates();

            if ($states->isEmpty()) {
                $this->warn('No Home Assistant entity IDs are configured.');

                return self::SUCCESS;
            }

            $this->line($this->encodeJson($states->all()));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function encodeJson(array $payload): string
    {
        $flags = JSON_UNESCAPED_SLASHES;

        if ((bool) $this->option('pretty')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return json_encode($payload, $flags) ?: '{}';
    }
}
