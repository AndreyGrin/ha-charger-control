<?php

use App\Console\Commands\ExecuteChargingPlanCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(ExecuteChargingPlanCommand::class)->everyMinute();
