<?php

namespace App\Services\MRP;

use App\DTO\Concept;
use App\DTO\MRP\EngineInput;

class ProductionPlanningEngine
{
    private const TEMPLATE_CL2 = 'CL2';

    private const TEMPLATE_SH1 = 'SH1';

    private const UM_DAY = 'DAY';

    private const UM_EA = 'EA';

    private const UM_KG = 'KG';

    public function run(EngineInput $input): array
    {
        $days = $input->getDays();
        $today = date('Ymd');

        $forecast = $input->getForecast();
        $firm = $input->getFirm();
        $demand = $input->getDemand();
        $productionPlan = $input->getProductionPlan();
        $actualProduction = $input->getActualProduction();

        $adb = $this->calculateWeeklyAdb(
            demand: $demand,
            calendar: $input->getCalendar(),
        );

        $inventory = $this->rollForwardInventory(
            days: $days,
            initial: $input->getProduct()->lastCycleCount?->getTheoricalStock() ?? 0,
            demand: $demand,
            productionPlan: $productionPlan,
            realProduction: $actualProduction,
            today: $today,
            inventoryAdjustments: $input->getProduct()->cycleCountAdjustments,
        );

        $stockDays = $this->calculateDailyStockDays(
            inventory: $inventory,
            adb: $adb,
        );

        $materialInventory = $this->rollForwardMaterialInventory(
            days: $days,
            initial: $input->getInitialStock(),
            source: $input->getMaterialSource(),
            plannedProduction: $input->getBlankProductionPlan(),
            actualProduction: $input->getBlankActualProduction(),
            today: $today,
        );

        $plannedPieces = $this->forwardPlannedPieces(
            entries: $input->getConfirmedOrders(),
            weightFactor: $input->getWeightFactor(),
        );

        $concepts = $this->buildBaseConcepts(
            input: $input,
            forecast: $forecast,
            firm: $firm,
            demand: $demand,
            adb: $adb,
            inventory: $inventory,
            stockDays: $stockDays,
            days: $days,
        );

        return match ($input->getTemplateCode()) {
            self::TEMPLATE_CL2 => array_merge(
                $concepts,
                $this->buildCl2Concepts(
                    input: $input,
                    days: $days,
                    today: $today,
                    productionPlan: $productionPlan,
                    actualProduction: $actualProduction,
                    plannedPieces: $plannedPieces,
                ),
            ),

            self::TEMPLATE_SH1 => array_merge(
                $concepts,
                $this->buildSh1Concepts(
                    input: $input,
                    productionPlan: $productionPlan,
                    actualProduction: $actualProduction,
                    materialInventory: $materialInventory,
                ),
            ),

            default => $concepts,
        };
    }

    /**
     * @return array<int, Concept>
     */
    private function buildBaseConcepts(
        EngineInput $input,
        array $forecast,
        array $firm,
        array $demand,
        array $adb,
        array $inventory,
        array $stockDays,
        array $days,
    ): array {
        return [
            new Concept(
                conceptCode: 'CALENDAR',
                data: $input->getCalendar(),
                um: self::UM_DAY,
            ),
            new Concept(
                conceptCode: 'FORECAST',
                data: $forecast,
                um: self::UM_EA,
            ),
            new Concept(
                conceptCode: 'FIRM',
                data: $firm,
                um: self::UM_EA,
            ),
            new Concept(
                conceptCode: 'DEMAND',
                data: $demand,
                um: self::UM_EA,
            ),
            new Concept(
                conceptCode: 'ADB',
                data: $adb,
                um: self::UM_EA,
            ),
            new Concept(
                conceptCode: 'INVENTORY',
                data: $inventory,
                um: self::UM_EA,
            ),
            new Concept(
                conceptCode: 'STOCK_DAYS',
                data: $stockDays,
                um: self::UM_DAY,
            ),
            new Concept(
                conceptCode: 'WEIGHT_FACTOR',
                data: array_fill_keys(
                    $days,
                    $input->getWeightFactor(),
                ),
                um: self::UM_KG,
            ),
        ];
    }

    /**
     * @return array<int, Concept>
     */
    private function buildCl2Concepts(
        EngineInput $input,
        array $days,
        string $today,
        array $productionPlan,
        array $actualProduction,
        array $plannedPieces,
    ): array {
        $blankProductionPlan = $input->getBlankProductionPlan();

        $blankInventory = $this->calculateBlankInventory(
            days: $days,
            today: $today,
            blankProductionPlan: $blankProductionPlan,
            initialInventory: $input->getBlankProduct()?->lastCycleCount?->getTheoricalStock() ?? 0,
            productionPlan: $productionPlan,
            actualProduction: $actualProduction,
        );

        $stockPlanned = $this->calculatePlannedStock(
            initialValue: $input->getInitialPlannedStock(),
            plannedPieces: $plannedPieces,
            blankProductionPlan: $blankProductionPlan,
        );

        return [
            new Concept(
                conceptCode: 'PRODUCTION_PLAN',
                data: $productionPlan,
                um: self::UM_EA,
            ),
            new Concept(
                conceptCode: 'ACTUAL_PRODUCTION',
                data: $actualProduction,
                um: self::UM_EA,
            ),
            new Concept(
                conceptCode: 'BLANK_INVENTORY',
                data: $blankInventory,
                um: self::UM_EA,
            ),
            new Concept(
                conceptCode: 'BLANK_PRODUCTION_PLAN',
                data: $blankProductionPlan,
                um: self::UM_EA,
            ),
            new Concept(
                conceptCode: 'BLANK_ACTUAL_PRODUCTION',
                data: $input->getBlankActualProduction(),
                um: self::UM_EA,
            ),
            new Concept(
                conceptCode: 'BLANK_PLANNED_PIECES',
                data: $plannedPieces,
                um: self::UM_EA,
            ),
            new Concept(
                conceptCode: 'BLANK_PLANNED_STOCK_PIECES',
                data: $stockPlanned,
                um: self::UM_EA,
            ),
            new Concept(
                conceptCode: 'PLANNED_STEEL',
                data: $input->getPlannedOrders(),
                um: self::UM_KG,
            ),
            new Concept(
                conceptCode: 'CONFIRMED_STEEL',
                data: $input->getConfirmedOrders(),
                um: self::UM_KG,
            ),
        ];
    }

    /**
     * @return array<int, Concept>
     */
    private function buildSh1Concepts(
        EngineInput $input,
        array $productionPlan,
        array $actualProduction,
        array $materialInventory,
    ): array {
        return [
            new Concept(
                conceptCode: 'PRODUCTION_PLAN',
                data: $productionPlan,
                um: self::UM_EA,
            ),
            new Concept(
                conceptCode: 'ACTUAL_PRODUCTION',
                data: $actualProduction,
                um: self::UM_EA,
            ),
            new Concept(
                conceptCode: 'PLANNED_SHEETS',
                data: $input->getPlannedOrders(),
                um: self::UM_EA,
            ),
            new Concept(
                conceptCode: 'CONFIRMED_SHEETS',
                data: $input->getConfirmedOrders(),
                um: self::UM_EA,
            ),
            new Concept(
                conceptCode: 'SHEETS_INVENTORY',
                data: $materialInventory,
                um: self::UM_EA,
            ),
        ];
    }

    /**
     * Calculate the Average Daily Balance (ADB) for each day
     * based on weekly demand and calendar data.
     *
     * @param  array<string, float|int>  $demand
     * @param  array<string, float|int>  $calendar
     * @return array<string, int>
     */
    private function calculateWeeklyAdb(
        array $demand,
        array $calendar,
    ): array {
        $result = [];
        $dates = array_keys($calendar);
        $totalDays = count($dates);

        for ($weekStart = 0; $weekStart < $totalDays; $weekStart += 7) {
            $weekDemand = array_slice($demand, $weekStart, 7);
            $weekCalendar = array_slice($calendar, $weekStart, 7);

            $workingDays = ceil(array_sum($weekCalendar));

            $adb = $workingDays > 0
                ? (int) ceil(array_sum($weekDemand) / $workingDays)
                : 0;

            $weekEnd = min($weekStart + 7, $totalDays);

            for ($index = $weekStart; $index < $weekEnd; $index++) {
                $result[$dates[$index]] = $adb;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, float|int>  $inventory
     * @param  array<string, float|int>  $adb
     * @return array<string, float>
     */
    private function calculateDailyStockDays(
        array $inventory,
        array $adb,
    ): array {
        $result = [];
        $lastValidValue = 0.0;

        foreach ($inventory as $date => $balance) {
            $adbValue = (float) ($adb[$date] ?? 0.0);

            if ($adbValue === 0.0) {
                $result[$date] = $lastValidValue;

                continue;
            }

            $calculatedValue = round(
                (float) $balance / $adbValue,
                2,
            );

            $result[$date] = $calculatedValue;
            $lastValidValue = $calculatedValue;
        }

        return $result;
    }

    /**
     * @param  array<string, float|int>  $days
     * @param  array<string, float|int>  $demand
     * @param  array<string, float|int>  $productionPlan
     * @param  array<string, float|int>  $realProduction
     * @param  array<string, float|int>|null  $inventoryAdjustments
     * @return array<string, float>
     */
    private function rollForwardInventory(
        array $days,
        float $initial,
        array $demand,
        array $productionPlan,
        array $realProduction,
        string $today,
        ?array $inventoryAdjustments = [],
    ): array {
        $balance = $initial;
        $result = [];
        $todayInt = (int) $today;

        foreach ($days as $day) {
            if (isset($inventoryAdjustments[$day])) {
                $balance = (float) $inventoryAdjustments[$day];
            }

            $production = (int) $day < $todayInt
                ? ($realProduction[$day] ?? 0.0)
                : ($productionPlan[$day] ?? 0.0);

            $balance += (float) $production;
            $balance -= (float) ($demand[$day] ?? 0.0);

            $result[$day] = $balance;
        }

        return $result;
    }

    /**
     * @return array<string, float>
     */
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

    /**
     * @param  array<string, float|int>  $entries
     * @return array<string, int|float>
     */
    private function forwardPlannedPieces(
        array $entries,
        float|int $weightFactor,
    ): array {
        $result = [];

        foreach ($entries as $date => $value) {
            $calculatedValue = $weightFactor != 0
                ? $value / $weightFactor
                : $value;

            $result[$date] = floor($calculatedValue);
        }

        return $result;
    }

    /**
     * @return array<string, float>
     */
    private function calculateBlankInventory(
        array $days,
        string $today,
        array $blankProductionPlan,
        int $initialInventory,
        array $productionPlan,
        array $actualProduction,
    ): array {
        $balance = $initialInventory;
        $result = [];
        $todayInt = (int) $today;

        foreach ($days as $day) {
            $production = (int) $day < $todayInt
                ? ($actualProduction[$day] ?? 0.0)
                : ($productionPlan[$day] ?? 0.0);

            $balance += (float) ($blankProductionPlan[$day] ?? 0.0);
            $balance -= (float) $production;

            $result[$day] = $balance;
        }

        return $result;
    }

    /**
     * @return array<string, float>
     */
    private function calculatePlannedStock(
        float $initialValue,
        array $plannedPieces,
        array $blankProductionPlan,
    ): array {
        $balance = $initialValue;
        $result = [];

        foreach ($plannedPieces as $date => $pieces) {
            $balance += (float) $pieces;
            $balance -= (float) ($blankProductionPlan[$date] ?? 0.0);

            $result[$date] = $balance;
        }

        return $result;
    }

    private function forwardPlannedStock(
        float $initialValue,
        array $plannedPieces,
        array $materialSource,
        string $today,
    ): array {
        $balance = $initialValue;
        $result = [];

        foreach ($plannedPieces as $date => $pieces) {
            $balance += (float) $pieces;
            $balance -= (float) ($materialSource[$date] ?? 0.0);

            $result[$date] = $balance;
        }

        return $result;
    }
}
