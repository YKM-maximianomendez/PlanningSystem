<?php

namespace App\DTO\MRP;

use App\DTO\Configuration\Product;
use App\DTO\Entry;

final class EngineInput
{
    public function __construct(
        private string $classCode,
        private int $level,
        private Product $product,
        private array $calendar,
        private array $forecast,
        private array $firm,
        private array $demand,
        private array $productionPlan,
        private array $actualProduction,
        private int $initialPlannedStock,
        private array $orders,
        private array $plannedOrders,
        private array $confirmedOrders,
        private float $weightFactor,
        private array $metadata = [],
        private ?Product $blankProduct = null,
        private array $blankProductionPlan = [],
        private array $blankActualProduction = [],
    ) {}

    public function getLevel(): int
    {
        return $this->level;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getCalendar(): array
    {
        return $this->calendar;
    }

    public function getForecast(): array
    {
        return Entry::groupEntries($this->forecast, $this->calendar);
    }

    public function getFirm(): array
    {
        return Entry::groupEntries($this->firm, $this->calendar);
    }

    public function getDemand(): array
    {
        return Entry::groupEntries($this->demand, $this->calendar);
    }

    public function getProductionPlan(): array
    {
        return Entry::groupEntries($this->productionPlan, $this->calendar);
    }

    public function getActualProduction(): array
    {
        return Entry::groupEntries($this->actualProduction, $this->calendar);
    }

    public function getInitialPlannedStock(): int
    {
        return $this->initialPlannedStock;
    }

    public function getOrders(): array
    {
        return $this->orders;
    }

    public function getPlannedOrders(): array
    {
        return Entry::groupEntries($this->plannedOrders, $this->calendar);
    }

    public function getConfirmedOrders(): array
    {
        return Entry::groupEntries($this->confirmedOrders, $this->calendar);
    }

    public function getWeightFactor(): float
    {
        return $this->weightFactor;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getBlankProduct(): ?Product
    {
        return $this->blankProduct;
    }

    public function getBlankProductionPlan(): array
    {
        return Entry::groupEntries($this->blankProductionPlan, $this->calendar);
    }

    public function getBlankActualProduction(): array
    {
        return Entry::groupEntries($this->blankActualProduction, $this->calendar);
    }

    public function getDays(): array
    {
        return array_keys($this->calendar);
    }

    public function getTemplateCode(): string
    {
        if ($this->level === 1) {
            return in_array($this->classCode, ['T1', 'T2']) ? 'CL1' : 'SH1';
        }

        return in_array($this->classCode, ['T1', 'T2']) ? 'CL2' : 'SH2';
    }

    public function getClassCode(): string
    {
        return $this->classCode;
    }

    public function getMaterialSource(): array
    {
        $result = [];
        $today = date('Ymd');
        $adjustments = $this->blankProduct !== null
            ? ($this->blankProduct->cycleCountAdjustments ?? [])
            : [];

        $actual = $this->blankProduct !== null
            ? $this->getBlankActualProduction()
            : $this->getConfirmedOrders();

        $planned = $this->blankProduct !== null
            ? $this->getBlankProductionPlan()
            : $this->getPlannedOrders();

        foreach ($this->getDays() as $day) {
            if (isset($adjustments[$day])) {
                $balance = (float) $adjustments[$day];
            } else {
                $balance = 0;
            }
            $result[$day] = $day < $today
                ? ($actual[$day] ?? 0) + $balance
                : ($planned[$day] ?? 0);
        }

        return $result;
    }

    public function getInitialStock(): float
    {
        return $this->blankProduct !== null
            ? (float) ($this->blankProduct->lastCycleCount?->getTheoricalStock() ?? 0)
            : (float) $this->initialPlannedStock;
    }
}
