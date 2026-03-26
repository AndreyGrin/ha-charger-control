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

## Notes

- The add-on stores its SQLite database, logs, and generated app key under `/data`.
- The dashboard auto-refreshes every quarter hour.
- The Laravel scheduler runs continuously inside the add-on.
