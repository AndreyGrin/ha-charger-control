# 🤖 Project Agents: EV Charge Controller

This document defines the automated logic providers (Agents) responsible for monitoring, calculating, and executing charging commands via the Home Assistant API.

---

## 1. The Strategy Agent (Policy Engine)
**Role:** The "Brain" that decides *when* to charge based on external data.
* **Inputs:** * Electricity Price (e.g., `sensor.nordpool`)
    * Solar Forecast (e.g., `sensor.solcast`)
    * Vehicle State of Charge (SoC)
* **Logic:** Executes every 30 minutes. It calculates the "Golden Hours" (lowest cost windows) and sets the target charge rate.
* **Implementation:** Laravel Command `app:evaluate-charging-strategy` scheduled via `Task Scheduler`.

---

## 2. The Watchdog Agent (Safety & Limits)
**Role:** The "Protector" that ensures the house's physical limits aren't exceeded.
* **Inputs:** * Real-time house load (`sensor.c26634_current_import`)
    * Charger current draw (`sensor.charger_power`)
* **Logic:** Runs on a high-frequency heartbeat. If `current_import` exceeds the breaker limit (e.g., 25A), it immediately sends an emergency `HassTurnOff` command.
* **Implementation:** Laravel Job dispatched with a short delay or a dedicated loop in a Daemon command.

---

## 3. The State Sync Agent (HA Bridge)
**Role:** The "Messenger" between the Laravel Database and Home Assistant.
* **Responsibility:** * Listens for incoming Webhooks from HA (e.g., manual "Boost" button pressed in UI).
    * Pushes Laravel-calculated data (e.g., "Time to Full") back to HA `input_text` or `sensor` entities.
* **Implementation:** Laravel `WebhookController` and `HomeAssistantService` wrapper.

---

## 4. The Analytics Agent (Data Reporter)
**Role:** The "Accountant" for charging efficiency and costs.
* **Responsibility:** * Summarizes total energy used (kWh) at the end of every session.
    * Calculates financial savings vs. the day's peak electricity price.
* **Trigger:** Observes the `ChargingSession` model; fires when status transitions to `completed`.
* **Implementation:** Laravel Model Observer `SessionObserver`.

---

## Technical Stack for Agents

| Component | Technology |
| :--- | :--- |
| **Scheduler** | Laravel Task Scheduling (`php artisan schedule:run`) |
| **Queue** | Database Queue (via MariaDB Add-on) |
| **Communication** | Laravel HTTP Client (Guzzle) targeting HA REST API |
| **Persistence** | MariaDB (Tables: `sessions`, `price_history`, `safety_logs`) |

---

## Agent Lifecycle
1. **Monitor:** Agents poll or receive webhooks from Home Assistant.
2. **Process:** Logic determines if the current state violates safety or follows strategy.
3. **Act:** Service calls are sent back to HA to toggle the `switch.c26634_charge_control`.
4. **Log:** Every action is recorded in MariaDB for future analytics.

## Changelog updates
When a new version of the EV Charge Controller is prepared, the changelog is updated with the new version number and release datails.