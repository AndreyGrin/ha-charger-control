# Changelog

## 0.1.9
- Treat past unexecuted planned slots as missed in the dashboard instead of leaving them visually planned
- Exclude stale past planned energy from the executed-vs-planned timeline
- Fix the timeline current-time marker so it reflects the real minute position inside the active hour

## 0.1.8
- Add dashboard artisan runner with whitelisted command execution and inline output
- Add reset-and-replan dashboard action and command
- Stop active charging sessions immediately when manually stopping the charger
- Record charged energy up to stop time from the charger meter and show stopped status in the UI

## 0.1.7
- Add optional MariaDB configuration in Home Assistant add-on settings
- Keep SQLite as the fallback when MariaDB settings are not provided
- Fix SQLite file ownership for write access inside the add-on
- Add PHP MySQL driver to the add-on runtime

## 0.1.5
- Upgrade to PHP 8.4

## 0.1.4
- Add Home Assistant repository-compatible add-on layout
- Build Composer dependencies and frontend assets inside the add-on image
- Fix Laravel storage framework bootstrap directories in add-on startup
- Fix PHP-FPM pool user configuration for Home Assistant runtime
- Add ingress-safe dashboard action buttons
- Add charger connection webhook handling for disconnect/reconnect plan control
