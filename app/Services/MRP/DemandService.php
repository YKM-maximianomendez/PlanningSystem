<?php

namespace App\Services\MRP;

use App\DTO\Configuration\Adjustment;
use App\DTO\Configuration\CompletionGroup;
use App\DTO\Configuration\Customer;
use App\DTO\Entry;
use App\Enums\ForecastStrategy;
use DateTimeImmutable;
use Illuminate\Database\Connection;

final readonly class DemandService
{
    /**
     * Get the demand for a given set of completion groups, date range, calendar, and customer.
     *
     * @param  CompletionGroup[]  $completionGroups
     * @param  array<string, int|float>  $calendar
     * @param  Adjustment[]  $adjustments
     * @return array{forecast: array<int, Entry>, firm: array<int, Entry>, demand: array<int, Entry>}
     */
    public function getDemand(
        Connection $connection,
        array $completionGroups,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        array $calendar,
        Customer $customer,
        array $adjustments = [],
        ForecastStrategy $forecastStrategy = ForecastStrategy::SUM,
    ): array {
        $fromStr = $from->format('Ymd');
        $toStr = $to->format('Ymd');

        $result = [
            'forecast' => [],
            'firm' => [],
            'demand' => [],
        ];

        $completions = CompletionGroup::extractCompletions($completionGroups);

        if (empty($completions)) {
            return $result;
        }

        $days = array_keys($calendar);

        $netAdjustments = Adjustment::resolveNetAdjustment($adjustments);

        if ($forecastStrategy === ForecastStrategy::MIX) {
            [$forecast, $firm] = [[], []];
        } else {
            [$forecast, $firm] = $this->getForecastAndFirm(
                $connection,
                $forecastStrategy->value,
                $completions,
                $fromStr,
                $toStr,
                $netAdjustments
            );
        }

        $forecastResult = [];
        $firmResult = [];
        $demandResult = [];

        foreach ($days as $day) {
            $forecastQuantity = (float) ($forecast[$day] ?? 0.0);
            $firmQuantity = (float) ($firm[$day] ?? 0.0);

            $forecastResult[$day] = $forecastQuantity;
            $firmResult[$day] = $firmQuantity;

            $demandResult[$day] = $firmQuantity > 0
                ? $firmQuantity
                : $forecastQuantity;
        }

        $demand = [];
        if (! $customer->isMMVO()) {
            $demand = $this->redistributeDemandByWorkingDays(
                $demandResult,
                $calendar
            );
        } else {
            $demand = $demandResult;
        }

        return [
            'forecast' => array_map(
                fn (string $date, float $quantity) => new Entry(
                    date: $date,
                    quantity: (int) $quantity,
                ),
                array_keys($forecastResult),
                $forecastResult
            ),
            'firm' => array_map(
                fn (string $date, float $quantity) => new Entry(
                    date: $date,
                    quantity: (int) $quantity,
                ),
                array_keys($firmResult),
                $firmResult
            ),
            'demand' => array_map(
                fn (string $date, float $quantity) => new Entry(
                    date: $date,
                    quantity: (int) $quantity,
                ),
                array_keys($demand),
                $demand
            ),
        ];
    }

    /**
     * @return array{
     *     0: array<string, float>,
     *     1: array<string, float>
     * }
     */
    private function getForecastAndFirm(
        Connection $connection,
        string $method,
        array $completions,
        string $from,
        string $to,
        float $netAdjustments
    ): array {
        $forecast = [];
        $firm = [];

        if (empty($completions)) {
            return [[], []];
        }

        $placeholders = implode(
            ',',
            array_fill(0, count($completions), '?')
        );

        $query = <<< SQL
        SELECT
            'forecast' AS ROW_TYPE,
            RIGHT(DIGITS(CAST(FLOOR(MRDTE / 10000) AS DEC(4,0))), 4)
                || SUBSTR(TRIM(MRCNO), 1, 4) AS DATE,
            {$method}(MQTY) AS QTY
        FROM LX834F01.KMR
        WHERE MRDTE BETWEEN ? AND ?
            AND MRCNO <> ''
            AND MPROD IN ($placeholders)
        GROUP BY
            RIGHT(DIGITS(CAST(FLOOR(MRDTE / 10000) AS DEC(4,0))), 4)
                || SUBSTR(TRIM(MRCNO), 1, 4)
        UNION ALL
        SELECT
            'firm' AS ROW_TYPE,
            RIGHT(DIGITS(CAST(FLOOR(LRDTE / 10000) AS DEC(4,0))), 4)
                || SUBSTR(TRIM(CLDOCK), 1, 4) AS DATE,
            {$method}(LQORD) AS QTY
        FROM LX834F01.ECL
        WHERE LRDTE BETWEEN ? AND ?
            AND CLDOCK <> ''
            AND LPROD IN ($placeholders)
        GROUP BY
            RIGHT(DIGITS(CAST(FLOOR(LRDTE / 10000) AS DEC(4,0))), 4)
                || SUBSTR(TRIM(CLDOCK), 1, 4)
        ORDER BY 1, 2
        WITH UR
        SQL;

        $resultset = $connection->select(
            $query,
            [
                $from,
                $to,
                ...$completions,
                $from,
                $to,
                ...$completions,
            ]
        );

        foreach ($resultset as $row) {
            $quantity = (float) $row->QTY + $netAdjustments;
            $date = (string) $row->DATE;

            if ($row->ROW_TYPE === 'forecast') {
                $forecast[$date] = $quantity;
            } else {
                $firm[$date] = $quantity;
            }
        }

        return [$forecast, $firm];
    }

    private function redistributeDemandByWorkingDays(array $demandByDate, array $calendarByDate): array
    {
        $dates = array_keys($demandByDate);
        $demandValues = array_values($demandByDate);
        $calendarValues = array_values($calendarByDate);

        $demandWeeks = array_chunk($demandValues, 7);
        $calendarWeeks = array_chunk($calendarValues, 7);

        $result = [];
        $dateIndex = 0;

        foreach ($demandWeeks as $weekIndex => $week) {
            $weeklyTotal = array_sum($week);
            $totalWorkingDays = array_sum($calendarWeeks[$weekIndex] ?? []);

            if ($weeklyTotal <= 0 || $totalWorkingDays <= 0) {
                foreach ($week as $dayInWeek => $value) {
                    if (isset($dates[$dateIndex])) {
                        $result[$dates[$dateIndex]] = 0.0;
                        $dateIndex++;
                    }
                }

                continue;
            }

            $dailyValue = (float) floor($weeklyTotal / $totalWorkingDays);

            foreach ($week as $dayInWeek => $value) {
                if (isset($dates[$dateIndex])) {
                    $shifts = $calendarWeeks[$weekIndex][$dayInWeek] ?? 0;
                    $result[$dates[$dateIndex]] = $shifts > 0 ? $dailyValue : 0.0;
                    $dateIndex++;
                }
            }
        }

        return $result;
    }
}
