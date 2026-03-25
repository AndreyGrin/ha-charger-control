<?php

return [
    'url' => env('HOME_ASSISTANT_URL'),
    'token' => env('HOME_ASSISTANT_TOKEN'),
    'timeout' => (int) env('HOME_ASSISTANT_TIMEOUT', 10),
    'entities' => [
        'charger_switch' => env('HOME_ASSISTANT_CHARGER_SWITCH_ENTITY_ID', 'switch.c26634_charge_control'),
        'charger_max_current' => env('HOME_ASSISTANT_CHARGER_MAX_CURRENT_ENTITY_ID', 'number.c26634_maximum_current'),
        'charger_energy_total' => env('HOME_ASSISTANT_CHARGER_ENERGY_TOTAL_ENTITY_ID', 'sensor.c26634_energy_active_import_register'),
        'vehicle_soc' => env('HOME_ASSISTANT_VEHICLE_SOC_ENTITY_ID'),
        'nordpool' => env('HOME_ASSISTANT_NORDPOOL_ENTITY_ID'),
        'solar_forecast_east' => env('HOME_ASSISTANT_SOLAR_FORECAST_EAST_ENTITY_ID'),
        'solar_forecast_west' => env('HOME_ASSISTANT_SOLAR_FORECAST_WEST_ENTITY_ID'),
        'solar_current_hour_east' => env('HOME_ASSISTANT_SOLAR_CURRENT_HOUR_EAST_ENTITY_ID'),
        'solar_current_hour_west' => env('HOME_ASSISTANT_SOLAR_CURRENT_HOUR_WEST_ENTITY_ID'),
        'solar_next_hour_east' => env('HOME_ASSISTANT_SOLAR_NEXT_HOUR_EAST_ENTITY_ID'),
        'solar_next_hour_west' => env('HOME_ASSISTANT_SOLAR_NEXT_HOUR_WEST_ENTITY_ID'),
        'house_load' => env('HOME_ASSISTANT_HOUSE_LOAD_ENTITY_ID'),
    ],
];
