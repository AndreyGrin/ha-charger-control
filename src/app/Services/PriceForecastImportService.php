<?php

namespace App\Services;

use App\Models\PriceForecast;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class PriceForecastImportService
{
    public function importFromHomeAssistant(HomeAssistantService $homeAssistant, CarbonImmutable $now): Collection
    {
        $nordpoolEntityId = config('home_assistant.entities.nordpool');

        if (! is_string($nordpoolEntityId) || $nordpoolEntityId === '') {
            throw new InvalidArgumentException('Nordpool entity is not configured.');
        }

        $nordpoolState = $homeAssistant->entityState($nordpoolEntityId);
        $attributes = $nordpoolState['attributes'] ?? [];

        $rows = collect()
            ->merge($this->normalizeRawPrices($attributes['raw_today'] ?? [], $attributes))
            ->merge($this->normalizeRawPrices($attributes['raw_tomorrow'] ?? [], $attributes))
            ->sortBy('starts_at')
            ->values();

        if ($rows->isEmpty()) {
            throw new InvalidArgumentException('Nordpool raw_today/raw_tomorrow attributes are empty.');
        }

        $rows = $this->applyNearTermSolar($rows, $homeAssistant, $now);

        $persisted = $rows->map(function (array $row) {
            return PriceForecast::query()->updateOrCreate(
                [
                    'starts_at' => $row['starts_at'],
                    'ends_at' => $row['ends_at'],
                ],
                [
                    'market_price_per_kwh' => $row['market_price_per_kwh'],
                    'solar_surplus_kwh' => $row['solar_surplus_kwh'],
                    'source' => 'home-assistant',
                ]
            );
        });

        PriceForecast::query()
            ->where('source', 'home-assistant')
            ->where('ends_at', '<', $now->subDay())
            ->delete();

        return $persisted;
    }

    private function normalizeRawPrices(array $rawPrices, array $attributes): Collection
    {
        $pricesInCents = (bool) ($attributes['price_in_cents'] ?? false);
        $vatRate = (float) config('charging.rates.vat_rate', 0.24);

        return collect($rawPrices)
            ->filter(fn ($row) => isset($row['start'], $row['end'], $row['value']))
            ->map(function (array $row) use ($pricesInCents, $vatRate): array {
                $marketPrice = (float) $row['value'];

                if ($pricesInCents) {
                    $marketPrice /= 100;
                }

                $marketPrice *= 1 + $vatRate;

                return [
                    'starts_at' => CarbonImmutable::parse($row['start']),
                    'ends_at' => CarbonImmutable::parse($row['end']),
                    'market_price_per_kwh' => round($marketPrice, 4),
                    'solar_surplus_kwh' => 0.0,
                ];
            });
    }

    private function applyNearTermSolar(Collection $rows, HomeAssistantService $homeAssistant, CarbonImmutable $now): Collection
    {
        $currentHourSolar = $homeAssistant->combinedSolarCurrentHourState();
        $nextHourSolar = $homeAssistant->combinedSolarNextHourState();

        return $rows->map(function (array $row) use ($now, $currentHourSolar, $nextHourSolar): array {
            $slotStart = $row['starts_at'];
            $slotMinutes = max(1, $slotStart->diffInMinutes($row['ends_at']));

            $solarSurplus = 0.0;

            if ($currentHourSolar !== null && $slotStart->isSameHour($now)) {
                $solarSurplus = round($currentHourSolar * ($slotMinutes / 60), 3);
            } elseif ($nextHourSolar !== null && $slotStart->isSameHour($now->addHour())) {
                $solarSurplus = round($nextHourSolar * ($slotMinutes / 60), 3);
            }

            $row['solar_surplus_kwh'] = $solarSurplus;

            return $row;
        });
    }
}
