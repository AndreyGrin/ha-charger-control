<?php

use App\Console\Commands\EvaluateChargingStrategyCommand;
use App\Console\Commands\ExecuteChargingPlanCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(EvaluateChargingStrategyCommand::class)->everyFifteenMinutes();
Schedule::command(ExecuteChargingPlanCommand::class)->everyMinute();
