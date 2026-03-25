<?php

return [
    'defaults' => [
        'charger_name' => env('CHARGER_NAME', 'c26634'),
        'battery_capacity_kwh' => (float) env('CHARGER_BATTERY_CAPACITY_KWH', 77),
        'daily_minimum_soc_percent' => (float) env('CHARGER_DAILY_MIN_SOC_PERCENT', 70),
        'target_soc_percent' => (float) env('CHARGER_TARGET_SOC_PERCENT', 80),
        'daily_minimum_deadline' => env('CHARGER_DAILY_MIN_DEADLINE', '07:00'),
        'charger_power_kw' => (float) env('CHARGER_POWER_KW', 11),
        'charger_min_current_amps' => (int) env('CHARGER_MIN_CURRENT_AMPS', 6),
        'charger_max_current_amps' => (int) env('CHARGER_MAX_CURRENT_AMPS', 16),
        'charger_efficiency' => (float) env('CHARGER_EFFICIENCY', 0.92),
        'grid_day_rate_per_kwh' => (float) env('GRID_DAY_RATE_PER_KWH', 8.1),
        'grid_night_rate_per_kwh' => (float) env('GRID_NIGHT_RATE_PER_KWH', 4.6),
        'grid_weekend_rate_per_kwh' => (float) env('GRID_WEEKEND_RATE_PER_KWH', 5.2),
        'day_rate_starts_at' => env('GRID_DAY_RATE_STARTS_AT', '07:00'),
        'night_rate_starts_at' => env('GRID_NIGHT_RATE_STARTS_AT', '22:00'),
    ],
    'rates' => [
        'prices_in_cents' => (bool) env('GRID_PRICES_IN_CENTS', true),
        'prices_include_vat' => (bool) env('GRID_PRICES_INCLUDE_VAT', false),
        'vat_rate' => (float) env('GRID_VAT_RATE', 0.24),
    ],
    'webhooks' => [
        'connection_secret' => env('CHARGER_CONNECTION_WEBHOOK_SECRET', 'change-me'),
    ],
];
