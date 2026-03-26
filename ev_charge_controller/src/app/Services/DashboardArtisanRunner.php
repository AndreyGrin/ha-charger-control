<?php

namespace App\Services;

use Illuminate\Contracts\Console\Kernel;
use InvalidArgumentException;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

class DashboardArtisanRunner
{
    private const ALLOWED_COMMANDS = [
        'about',
        'app:check-home-assistant',
        'app:dump-home-assistant-entity',
        'app:evaluate-charging-strategy',
        'app:execute-charging-plan',
        'app:reset-charging-plan',
        'migrate:status',
        'schedule:list',
    ];

    public function __construct(
        private readonly Kernel $kernel,
    ) {}

    public function run(string $commandLine): array
    {
        $normalized = trim($commandLine);

        if ($normalized === '') {
            throw new InvalidArgumentException('Enter an artisan command.');
        }

        $command = preg_split('/\s+/', $normalized)[0] ?? '';

        if (! in_array($command, self::ALLOWED_COMMANDS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Command "%s" is not allowed from the dashboard.',
                $command,
            ));
        }

        $input = new StringInput($normalized);
        $output = new BufferedOutput;
        $exitCode = $this->kernel->handle($input, $output);
        $this->kernel->terminate($input, $exitCode);

        return [
            'command' => $normalized,
            'exit_code' => $exitCode,
            'output' => trim($output->fetch()),
        ];
    }

    public function allowedCommands(): array
    {
        return self::ALLOWED_COMMANDS;
    }
}
