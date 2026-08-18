<?php

namespace App\Services\MRP;

use App\DTO\Concept;
use App\DTO\MRP\EngineInput;

class ProductionPlanningEngine
{
    public function __construct() {}

    public function run(EngineInput $input): array
    {
        $days = $input->getDays();
        $forecast = $input->getForecast();
        $firm = $input->getFirm();
        $demand = $input->getDemand();
        $actualProduction = $input->getActualProduction();
        $productionPlan = $input->getProductionPlan();

        $adb = $this->calculateWeeklyAdb($demand, $input->getCalendar());

        $inventory = $this->rollForwardInventory(
            days: $days,
            initial: $input->getProduct()->lastCycleCount?->getTheoricalStock() ?? 0,
            demand: $demand,
            productionPlan: $productionPlan,
            realProduction: $actualProduction,
            today: date('Ymd'),
            inventoryAdjustments: $input->getProduct()->cycleCountAdjustments,
        );

        $stockDays = $this->calculateDailyStockDays($inventory, $adb);

        $materialSource = $input->getMaterialSource();

        $materialInventory = $this->rollForwardMaterialInventory(
            days: $days,
            initial: $input->getInitialStock(),
            source: $materialSource,
            plannedProduction: $input->getBlankProductionPlan(),
            actualProduction: $input->getBlankActualProduction(),
            today: date('Ymd'),
        );

        $plannedPieces = $this->forwardPlannedPieces($input->getConfirmedOrders(), $input->getWeightFactor());
        $plannedStockPieces = $this->forwardPlannedStock(
            initialValue: $input->getInitialPlannedStock(),
            plannedPieces: $plannedPieces,
            materialSource: $materialSource,
            today: date('Ymd'),
        );

        $concepts = [
            new Concept(conceptCode: 'CALENDAR', data: $input->getCalendar(), um: 'DAY'),
            new Concept(conceptCode: 'FORECAST', data: $forecast, um: 'EA'),
            new Concept(conceptCode: 'FIRM', data: $firm, um: 'EA'),
            new Concept(conceptCode: 'DEMAND', data: $demand, um: 'EA'),
            new Concept(conceptCode: 'ADB', data: $adb, um: 'EA'),
            new Concept(conceptCode: 'INVENTORY', data: $inventory, um: 'EA'),
            new Concept(conceptCode: 'STOCK_DAYS', data: $stockDays, um: 'DAY'),
            new Concept(conceptCode: 'WEIGHT_FACTOR', data: array_fill_keys($days, $input->getWeightFactor()), um: 'KG'),
        ];

        if ($input->getTemplateCode() === 'CL2') {
            return array_merge($concepts, [
                new Concept(conceptCode: 'PRODUCTION_PLAN', data: $productionPlan, um: 'EA'),
                new Concept(conceptCode: 'ACTUAL_PRODUCTION', data: $actualProduction, um: 'EA'),
                new Concept(conceptCode: 'BLANK_INVENTORY', data: $materialInventory, um: 'EA'),
                new Concept(conceptCode: 'BLANK_PRODUCTION_PLAN', data: $input->getBlankProductionPlan(), um: 'EA'),
                new Concept(conceptCode: 'BLANK_ACTUAL_PRODUCTION', data: $input->getBlankActualProduction(), um: 'EA'),
                new Concept(conceptCode: 'BLANK_PLANNED_PIECES', data: $plannedPieces, um: 'EA'),
                new Concept(conceptCode: 'BLANK_PLANNED_STOCK_PIECES', data: $plannedStockPieces, um: 'EA'),
                new Concept(conceptCode: 'PLANNED_STEEL', data: $input->getPlannedOrders(), um: 'KG'),
                new Concept(conceptCode: 'CONFIRMED_STEEL', data: $input->getConfirmedOrders(), um: 'KG'),
            ]);
        }

        if ($input->getTemplateCode() === 'SH1') {
            return array_merge($concepts, [
                new Concept(conceptCode: 'PRODUCTION_PLAN', data: $productionPlan, um: 'EA'),
                new Concept(conceptCode: 'ACTUAL_PRODUCTION', data: $actualProduction, um: 'EA'),
                new Concept(conceptCode: 'PLANNED_SHEETS', data: $input->getPlannedOrders(), um: 'EA'),
                new Concept(conceptCode: 'CONFIRMED_SHEETS', data: $input->getConfirmedOrders(), um: 'EA'),
                new Concept(conceptCode: 'SHEETS_INVENTORY', data: $materialInventory, um: 'EA'),
            ]);
        }

        return $concepts;
    }

    /**
     * Calculate the Average Daily Balance (ADB) for each day based on weekly demand and calendar data.
     *
     * @param  array<string, float|int>  $demand
     * @param  array<string, float|int>  $calendar
     * @return array<string, int>
     */
    private function calculateWeeklyAdb(array $demand, array $calendar): array
    {
        $result = [];
        $dates = array_keys($calendar);
        $totalDays = count($dates);

        for ($i = 0; $i < $totalDays; $i += 7) {
            $weekDemand = array_slice($demand, $i, 7);
            $weekCalendar = array_slice($calendar, $i, 7);
            $t = ceil(array_sum($weekCalendar));

            $k = ($t > 0) ? (int) ceil(array_sum($weekDemand) / $t) : 0;

            for ($j = $i; $j < $i + 7 && $j < $totalDays; $j++) {
                $date = $dates[$j];
                $result[$date] = $k;
            }
        }

        return $result;
    }

    private function calculateDailyStockDays(array $inventory, array $adb): array
    {
        $result = [];
        $lastValidValue = 0.0;

        foreach ($inventory as $date => $balance) {
            $adbValue = (float) ($adb[$date] ?? 0.0);

            if ($adbValue === 0.0) {
                $result[$date] = $lastValidValue;
            } else {
                $calculatedValue = round($balance / $adbValue, 2);
                $result[$date] = $calculatedValue;
                $lastValidValue = $calculatedValue;
            }
        }

        return $result;
    }

    private function rollForwardInventory(
        array $days,
        float $initial,
        array $demand,
        array $productionPlan,
        array $realProduction,
        string $today,
        ?array $inventoryAdjustments = []
    ): array {
        $balance = $initial;
        $result = [];
        $todayInt = (int) $today;

        foreach ($days as $day) {
            $currentDayInt = (int) $day;

            if (isset($inventoryAdjustments[$day])) {
                $balance = (float) $inventoryAdjustments[$day];
            }

            $production = ($currentDayInt < $todayInt)
                ? ($realProduction[$day] ?? 0.0)
                : ($productionPlan[$day] ?? 0.0);

            $balance += (float) $production - (float) ($demand[$day] ?? 0.0);

            $result[$day] = $balance;
        }

        return $result;
    }

    private function rollForwardMaterialInventory(
        array $days,
        float $initial,
        array $source,
        array $plannedProduction,
        array $actualProduction,
        string $today,
    ): array {
        $balance = $initial;
        $result = [];
        $todayInt = (int) $today;

        foreach ($days as $day) {
            $production = (int) $day < $todayInt
                ? ($actualProduction[$day] ?? 0.0)
                : ($plannedProduction[$day] ?? 0.0);

            $balance += (float) ($source[$day] ?? 0.0);
            $balance -= (float) $production;

            $result[$day] = $balance;
        }

        return $result;
    }

    private function forwardPlannedPieces(
        array $entries,
        float|int $weightFactor
    ): array {
        $result = [];
        foreach ($entries as $date => $value) {
            $calculatedValue = ($weightFactor != 0) ? ($value / $weightFactor) : $value;
            $result[$date] = floor($calculatedValue);
        }

        return $result;
    }

    private function forwardPlannedStock(float $initialValue, array $plannedPieces, array $materialSource, string $today): array
    {
        $balance = $initialValue;
        $result = [];
        $todayInt = (int) $today;

        foreach ($plannedPieces as $date => $pieces) {
            $currentDayInt = (int) $date;

            $transformable = ($currentDayInt < $todayInt)
                ? ($materialSource[$date] ?? 0.0)
                : ($materialSource[$date] ?? 0.0);

            $balance += (float) $pieces - $transformable;
            $result[$date] = $balance;
        }

        return $result;
    }
}
