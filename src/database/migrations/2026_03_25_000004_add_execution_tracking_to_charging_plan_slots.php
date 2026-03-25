<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charging_plan_slots', function (Blueprint $table) {
            $table->string('status')->default('planned')->after('rationale');
            $table->dateTime('execution_started_at')->nullable()->after('status');
            $table->dateTime('execution_finished_at')->nullable()->after('execution_started_at');
            $table->decimal('meter_started_kwh', 10, 3)->nullable()->after('execution_finished_at');
            $table->decimal('meter_finished_kwh', 10, 3)->nullable()->after('meter_started_kwh');
            $table->decimal('executed_energy_kwh', 10, 3)->default(0)->after('meter_finished_kwh');
        });
    }

    public function down(): void
    {
        Schema::table('charging_plan_slots', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'execution_started_at',
                'execution_finished_at',
                'meter_started_kwh',
                'meter_finished_kwh',
                'executed_energy_kwh',
            ]);
        });
    }
};
