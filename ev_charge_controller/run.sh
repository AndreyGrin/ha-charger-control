#!/usr/bin/with-contenv bashio

set -euo pipefail

APP_ROOT="/var/www/html"
APP_ENV_FILE="${APP_ROOT}/.env"
APP_KEY_FILE="/data/app.key"
DB_FILE="/data/charger-control.sqlite"
SCHEDULER_PID=""
PHP_FPM_PID=""

log() {
    bashio::log.info "$1"
}

option() {
    bashio::config "$1"
}

write_env() {
    mkdir -p /data "${APP_ROOT}/storage" "${APP_ROOT}/bootstrap/cache"

    local db_host
    local db_port
    local db_database
    local db_username
    local db_password
    local db_connection

    db_host="$(option 'db_host')"
    db_port="$(option 'db_port')"
    db_database="$(option 'db_database')"
    db_username="$(option 'db_username')"
    db_password="$(option 'db_password')"

    if [[ ! -f "${APP_KEY_FILE}" ]]; then
        php84 -r 'echo "base64:".base64_encode(random_bytes(32));' > "${APP_KEY_FILE}"
    fi

    if [[ -n "${db_host}" && -n "${db_database}" && -n "${db_username}" ]]; then
        db_connection="mariadb"
    else
        db_connection="sqlite"
        touch "${DB_FILE}"
    fi

    cat > "${APP_ENV_FILE}" <<EOF
APP_NAME="EV Charge Controller"
APP_ENV=$(option 'app_env')
APP_KEY=$(cat "${APP_KEY_FILE}")
APP_DEBUG=$(option 'app_debug')
APP_URL=http://127.0.0.1:8099
APP_TIMEZONE=$(option 'app_timezone')

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=$(option 'log_level')
CHARGER_CONNECTION_WEBHOOK_SECRET=$(option 'charger_connection_webhook_secret')

DB_CONNECTION=${db_connection}
DB_HOST=${db_host}
DB_PORT=${db_port}
DB_DATABASE=$( [[ "${db_connection}" == "sqlite" ]] && echo "${DB_FILE}" || echo "${db_database}" )
DB_USERNAME=${db_username}
DB_PASSWORD=${db_password}

SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync

CACHE_STORE=file

MAIL_MAILER=log
VITE_APP_NAME="EV Charge Controller"

HOME_ASSISTANT_URL=$(option 'home_assistant_url')
HOME_ASSISTANT_TOKEN=$(option 'home_assistant_token')
HOME_ASSISTANT_TIMEOUT=$(option 'home_assistant_timeout')
HOME_ASSISTANT_CHARGER_SWITCH_ENTITY_ID=$(option 'charger_switch_entity_id')
HOME_ASSISTANT_CHARGER_MAX_CURRENT_ENTITY_ID=$(option 'charger_max_current_entity_id')
HOME_ASSISTANT_CHARGER_ENERGY_TOTAL_ENTITY_ID=$(option 'charger_energy_total_entity_id')
HOME_ASSISTANT_VEHICLE_SOC_ENTITY_ID=$(option 'vehicle_soc_entity_id')
HOME_ASSISTANT_NORDPOOL_ENTITY_ID=$(option 'nordpool_entity_id')
HOME_ASSISTANT_SOLAR_FORECAST_EAST_ENTITY_ID=$(option 'solar_forecast_east_entity_id')
HOME_ASSISTANT_SOLAR_FORECAST_WEST_ENTITY_ID=$(option 'solar_forecast_west_entity_id')
HOME_ASSISTANT_SOLAR_CURRENT_HOUR_EAST_ENTITY_ID=$(option 'solar_current_hour_east_entity_id')
HOME_ASSISTANT_SOLAR_CURRENT_HOUR_WEST_ENTITY_ID=$(option 'solar_current_hour_west_entity_id')
HOME_ASSISTANT_SOLAR_NEXT_HOUR_EAST_ENTITY_ID=$(option 'solar_next_hour_east_entity_id')
HOME_ASSISTANT_SOLAR_NEXT_HOUR_WEST_ENTITY_ID=$(option 'solar_next_hour_west_entity_id')
HOME_ASSISTANT_HOUSE_LOAD_ENTITY_ID=$(option 'house_load_entity_id')

CHARGER_NAME=$(option 'charger_name')
CHARGER_BATTERY_CAPACITY_KWH=$(option 'charger_battery_capacity_kwh')
CHARGER_DAILY_MIN_SOC_PERCENT=$(option 'charger_daily_min_soc_percent')
CHARGER_TARGET_SOC_PERCENT=$(option 'charger_target_soc_percent')
CHARGER_DAILY_MIN_DEADLINE=$(option 'charger_daily_min_deadline')
CHARGER_POWER_KW=$(option 'charger_power_kw')
CHARGER_MIN_CURRENT_AMPS=$(option 'charger_min_current_amps')
CHARGER_MAX_CURRENT_AMPS=$(option 'charger_max_current_amps')
CHARGER_EFFICIENCY=$(option 'charger_efficiency')

GRID_DAY_RATE_PER_KWH=$(option 'grid_day_rate_per_kwh')
GRID_NIGHT_RATE_PER_KWH=$(option 'grid_night_rate_per_kwh')
GRID_WEEKEND_RATE_PER_KWH=$(option 'grid_weekend_rate_per_kwh')
GRID_PRICES_IN_CENTS=$(option 'grid_prices_in_cents')
GRID_PRICES_INCLUDE_VAT=$(option 'grid_prices_include_vat')
GRID_VAT_RATE=$(option 'grid_vat_rate')
GRID_DAY_RATE_STARTS_AT=$(option 'grid_day_rate_starts_at')
GRID_NIGHT_RATE_STARTS_AT=$(option 'grid_night_rate_starts_at')
EOF
}

prepare_storage() {
    rm -rf "${APP_ROOT}/storage/app" "${APP_ROOT}/storage/framework" "${APP_ROOT}/storage/logs"
    mkdir -p \
        /data/storage/app \
        /data/storage/framework/cache/data \
        /data/storage/framework/sessions \
        /data/storage/framework/testing \
        /data/storage/framework/views \
        /data/storage/logs
    ln -sfn /data/storage/app "${APP_ROOT}/storage/app"
    ln -sfn /data/storage/framework "${APP_ROOT}/storage/framework"
    ln -sfn /data/storage/logs "${APP_ROOT}/storage/logs"
    if [[ -f "${DB_FILE}" ]]; then
        chown nginx:nginx "${DB_FILE}"
        chmod 664 "${DB_FILE}"
    fi
    chown -R nginx:nginx /data/storage "${APP_ROOT}/bootstrap/cache"
    chmod -R 775 /data/storage "${APP_ROOT}/bootstrap/cache"
}

bootstrap_laravel() {
    cd "${APP_ROOT}"

    php84 artisan optimize:clear
    php84 artisan migrate --force
    php84 artisan config:cache
    php84 artisan route:cache
    php84 artisan view:cache
}

start_scheduler() {
    cd "${APP_ROOT}"
    php84 artisan schedule:work &
    SCHEDULER_PID=$!
}

cleanup() {
    if [[ -n "${SCHEDULER_PID}" ]] && kill -0 "${SCHEDULER_PID}" 2>/dev/null; then
        kill "${SCHEDULER_PID}" 2>/dev/null || true
    fi

    if [[ -n "${PHP_FPM_PID}" ]] && kill -0 "${PHP_FPM_PID}" 2>/dev/null; then
        kill "${PHP_FPM_PID}" 2>/dev/null || true
    fi
}

trap cleanup EXIT TERM INT

log "Writing runtime environment"
write_env

log "Preparing persistent storage"
prepare_storage

log "Bootstrapping Laravel"
bootstrap_laravel

log "Starting Laravel scheduler"
start_scheduler

log "Starting PHP-FPM"
php-fpm84 -F &
PHP_FPM_PID=$!

log "Starting Nginx"
exec nginx -g 'daemon off;'
