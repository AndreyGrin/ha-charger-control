# EV Charge Controller

This add-on runs the Laravel EV charging planner inside Home Assistant ingress.

## What it does

- Imports Nordpool spot prices from Home Assistant entities
- Applies grid day/night/weekend tariffs
- Uses east/west solar forecast and short-term solar estimates
- Builds a charging plan and executes it through Home Assistant switch/number entities
- Tracks executed energy from the charger energy register
- Shows planning, execution, and history in the built-in dashboard

## Database

By default, the add-on uses SQLite stored under `/data`.

If you fill in the MariaDB connection settings in the add-on configuration:

- `db_host`
- `db_port`
- `db_database`
- `db_username`
- `db_password`

the add-on will use MariaDB instead of SQLite on the next restart.

## Install

1. Copy this repository into your Home Assistant custom add-ons directory, or add it as a local custom add-on source.
2. Open the add-on config and fill in your entity IDs and Home Assistant token.
3. Start the add-on.
4. Open the add-on via the Home Assistant sidebar.

## Required Home Assistant entities

- Charger enable switch
- Charger maximum current number
- Charger cumulative energy meter
- Vehicle SoC sensor
- Nordpool price entity with `raw_today` and `raw_tomorrow`
- Solar current/next hour east-west sensors

## Connection webhook

Use a Home Assistant automation triggered by your Nissan or charger connection entity and POST to:

`/ha/webhook/<your-secret>/connection`

Accepted payload examples:

```json
{ "status": "connected" }
```

```json
{ "status": "disconnected" }
```

or:

```json
{ "connected": true }
```

When disconnected, the add-on stops the charger and clears pending planned slots. When connected, it syncs SoC and rebuilds the plan immediately.

## Artisan commands

Run commands inside the add-on container from `/var/www/html` with `php84 artisan ...`.

### `app:check-home-assistant`

Validates the Home Assistant API connection and prints the configured entity states.

Parameters:
- none

Example:

```bash
php84 artisan app:check-home-assistant
```

### `app:dump-home-assistant-entity`

Dumps raw Home Assistant JSON for one configured entity alias or one exact `entity_id`.

Parameters:
- `entity` optional: configured alias such as `nordpool`, `vehicle_soc`, `solar_current_hour_east`, or a raw HA entity id
- `--pretty`: pretty-print the JSON response

Examples:

```bash
php84 artisan app:dump-home-assistant-entity nordpool --pretty
php84 artisan app:dump-home-assistant-entity sensor.nordpool_kwh_ee_eur_3_10_024 --pretty
php84 artisan app:dump-home-assistant-entity --pretty
```

### `app:evaluate-charging-strategy`

Imports forecast data from Home Assistant, refreshes vehicle SoC, and builds the next cheapest charging plan.

Parameters:
- `--horizon-minutes=720`: planning horizon in minutes from now
- `--until=`: absolute end time for the planning horizon, for example `17:00`
- `--minimum-soc=`: one-off override for the required minimum SoC percent for this run
- `--minimum-deadline=`: one-off override for the minimum SoC deadline time in `HH:MM` format for this run

Notes:
- `--until` overrides `--horizon-minutes`
- `--minimum-soc` and `--minimum-deadline` do not change the saved add-on settings; they apply only to the current command run

Examples:

```bash
php84 artisan app:evaluate-charging-strategy
php84 artisan app:evaluate-charging-strategy --horizon-minutes=180
php84 artisan app:evaluate-charging-strategy --until=07:00
php84 artisan app:evaluate-charging-strategy --until=07:00 --minimum-soc=70 --minimum-deadline=06:00
```

### `app:execute-charging-plan`

Checks the current plan against the current time, updates slot execution state, and starts or stops the charger in Home Assistant.

Parameters:
- `--dry-run`: calculate the action without sending commands to Home Assistant

Examples:

```bash
php84 artisan app:execute-charging-plan
php84 artisan app:execute-charging-plan --dry-run
```

### `app:reset-charging-plan`

Cancels the current active and planned charging state, optionally keeps the charger running, and can immediately rebuild a fresh plan.

Parameters:
- `--replan`: rebuild a new plan immediately after reset
- `--keep-charger-running`: do not force the charger off during reset

Examples:

```bash
php84 artisan app:reset-charging-plan
php84 artisan app:reset-charging-plan --replan
php84 artisan app:reset-charging-plan --replan --keep-charger-running
```

## Dashboard command runner

The dashboard includes a restricted artisan runner. It allows these commands:

- `about`
- `app:check-home-assistant`
- `app:dump-home-assistant-entity`
- `app:evaluate-charging-strategy`
- `app:execute-charging-plan`
- `app:reset-charging-plan`
- `migrate:status`
- `schedule:list`

## Notes

- The add-on stores its SQLite database, logs, and generated app key under `/data`.
- The dashboard auto-refreshes every quarter hour.
- The Laravel scheduler runs continuously inside the add-on.
