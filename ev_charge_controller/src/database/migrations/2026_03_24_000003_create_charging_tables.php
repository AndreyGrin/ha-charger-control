<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charging_settings', function (Blueprint $table) {
            $table->id();
            $table->string('charger_name')->default('default');
            $table->string('ha_charge_control_entity_id')->default('switch.c26634_charge_control');
            $table->string('ha_maximum_current_entity_id')->default('number.c26634_maximum_current');
            $table->decimal('battery_capacity_kwh', 8, 3);
            $table->decimal('current_soc_percent', 5, 2);
            $table->decimal('daily_minimum_soc_percent', 5, 2);
            $table->decimal('target_soc_percent', 5, 2);
            $table->time('daily_minimum_deadline');
            $table->decimal('charger_power_kw', 6, 2);
            $table->unsignedTinyInteger('charger_min_current_amps')->default(6);
            $table->unsignedTinyInteger('charger_max_current_amps')->default(16);
            $table->decimal('charger_efficiency', 4, 2)->default(0.92);
            $table->decimal('grid_day_rate_per_kwh', 8, 4);
            $table->decimal('grid_night_rate_per_kwh', 8, 4);
            $table->decimal('grid_weekend_rate_per_kwh', 8, 4);
            $table->time('day_rate_starts_at');
            $table->time('night_rate_starts_at');
            $table->timestamps();
        });

        Schema::create('price_forecasts', function (Blueprint $table) {
            $table->id();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at');
            $table->decimal('market_price_per_kwh', 8, 4);
            $table->decimal('solar_surplus_kwh', 8, 3)->default(0);
            $table->string('source')->nullable();
        });

        Schema::create('charging_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charging_setting_id')->nullable()->constrained('charging_settings')->nullOnDelete();
            $table->string('status')->default('planned')->index();
            $table->dateTime('generated_at')->index();
            $table->dateTime('deadline_at');
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->decimal('minimum_energy_kwh', 8, 3)->default(0);
            $table->decimal('target_energy_kwh', 8, 3)->default(0);
            $table->decimal('planned_energy_kwh', 8, 3)->default(0);
            $table->decimal('estimated_cost', 8, 2)->default(0);
            $table->decimal('average_price_per_kwh', 8, 4)->default(0);
            $table->json('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('charging_plan_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charging_plan_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at');
            $table->decimal('market_price_per_kwh', 8, 4);
            $table->decimal('grid_price_per_kwh', 8, 4);
            $table->decimal('import_price_per_kwh', 8, 4);
            $table->decimal('effective_price_per_kwh', 8, 4);
            $table->decimal('solar_surplus_kwh', 8, 3)->default(0);
            $table->decimal('allocated_energy_kwh', 8, 3)->default(0);
            $table->decimal('allocated_import_energy_kwh', 8, 3)->default(0);
            $table->decimal('allocated_solar_energy_kwh', 8, 3)->default(0);
            $table->decimal('recommended_power_kw', 6, 2)->default(0);
            $table->decimal('estimated_cost', 8, 2)->default(0);
            $table->string('selection_bucket')->nullable();
            $table->text('rationale')->nullable();
        });

        Schema::create('charging_sessions', function (Blueprint $table) {
            $table->id();
            $table->dateTime('started_at')->index();
            $table->dateTime('ended_at')->nullable();
            $table->string('status')->default('completed')->index();
            $table->decimal('energy_kwh', 8, 3)->default(0);
            $table->decimal('average_price_per_kwh', 8, 4)->default(0);
            $table->decimal('estimated_peak_price_per_kwh', 8, 4)->default(0);
            $table->decimal('estimated_cost', 8, 2)->default(0);
            $table->decimal('estimated_peak_cost', 8, 2)->default(0);
            $table->decimal('savings_amount', 8, 2)->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charging_sessions');
        Schema::dropIfExists('charging_plan_slots');
        Schema::dropIfExists('charging_plans');
        Schema::dropIfExists('price_forecasts');
        Schema::dropIfExists('charging_settings');
    }
};
