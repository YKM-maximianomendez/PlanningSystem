<?php

namespace App\UseCases\MRP;

use App\DTO\Configuration;
use App\DTO\Configuration\CompletionGroup;
use App\DTO\Configuration\Product;
use App\DTO\Entry;
use App\DTO\MRP\EngineInput;
use App\Services\MRP\CompletionService;
use App\Services\MRP\DemandService;
use App\Services\MRP\ProductionPlanningEngine;
use App\Services\MRP\ProductionService;
use App\Services\MRP\RawMaterialService;
use App\ValueObjects\PlanningRange;
use Illuminate\Database\Connection;

class RunEngineUseCase
{
    public function __construct(
        private readonly CompletionService $completionService,
        private readonly DemandService $demandService,
        private readonly RawMaterialService $rawMaterialService,
        private readonly ProductionService $productionService,
    ) {}

    public function execute(
        Connection $connection,
        Configuration $configuration,
        array $calendar,
        PlanningRange $planningRange,
        bool $isSimulation = false
    ) {
        $planningLevel = $configuration->level;
        $material = $configuration->material;
        $product = $configuration->planningProduct;
        $products = collect($configuration->products);

        $completionGroups = [
            new CompletionGroup(
                group: 1,
                completions: $this->completionService->getCompletionProducts(
                    connection: $connection,
                    products: [$product->productCode],
                ),
            ),
        ];

        $demand = $this->demandService->getDemand(
            connection: $connection,
            completionGroups: $completionGroups,
            from: $planningRange->start,
            to: $planningRange->end,
            calendar: $calendar,
            customer: $configuration->customer,
            adjustments: $configuration->adjustments,
            forecastStrategy: $configuration->forecastStrategy,
        );

        $actualProduction = $this->productionService->getActualProduction(
            connection: $connection,
            items: [$product->productCode],
            from: $planningRange->start,
            to: $planningRange->end,
        );

        $blankProduct = $planningLevel === 2
            ? $products->firstWhere('level', 1)
            : null;

        $blankActualProduction = $blankProduct
            ? $this->productionService->getActualProduction(
                connection: $connection,
                items: [$blankProduct->productCode],
                from: $planningRange->start,
                to: $planningRange->end,
            )
            : [];

        $blankProductionPlan = [];

        $weightFactor = $this->resolveWeightFactor(
            planningLevel: $planningLevel,
            product: $product,
            blankProduct: $blankProduct,
        );

        $cutOffStartDate = $material->options['cutoff_start_date'] ?? null;
        $initialQuantity = (float) ($material->options['cutoff_quantity'] ?? 0);

        $orders = $this->rawMaterialService->getShopOrders(
            connection: $connection,
            material: $material->materialCode,
            cutOffDate: $cutOffStartDate
                ? date_create_immutable($cutOffStartDate)
                : null,
        );

        $stagingOrders = $this->rawMaterialService->getStagingOrders(
            connection: $connection,
            material: $material->materialCode,
            start: $planningRange->start,
            end: $planningRange->end,
        );

        $plannedOrders = array_map(
            static fn (array $row): Entry => new Entry(
                date: date_create_immutable($row['dueDate'])?->format('Ymd'),
                quantity: $row['quantityRequired'],
            ),
            $stagingOrders,
        );

        $confirmedOrders = array_map(
            static fn (array $row): Entry => new Entry(
                date: date_create_immutable($row['date'])?->format('Ymd'),
                quantity: $row['quantity'],
            ),
            $this->rawMaterialService->getConfirmedOrders(
                materialId: $material->materialId,
                vendorId: $material->vendor->vendorId,
                start: $planningRange->start,
                end: $planningRange->end,
                stagingOrders: $stagingOrders,
            ),
        );

        $initialPlannedStock = $this->calculateInitialPlannedStock(
            orders: $orders,
            initialQuantity: $initialQuantity,
            weightFactor: $weightFactor,
        );

        $concepts = app(ProductionPlanningEngine::class)->run(
            new EngineInput(
                classCode: $material->classCode,
                level: $planningLevel,
                product: $product,
                calendar: $calendar,
                forecast: $demand['forecast'],
                firm: $demand['firm'],
                demand: $demand['demand'],
                productionPlan: [],
                actualProduction: $actualProduction,
                initialPlannedStock: $initialPlannedStock,
                orders: $orders,
                plannedOrders: $plannedOrders,
                confirmedOrders: $confirmedOrders,
                weightFactor: $weightFactor,
                metadata: [
                    'cutOffStartDate' => $cutOffStartDate,
                    'initialQuantity' => $initialQuantity,
                    'initialPlannedStock' => $initialPlannedStock,
                    'isSimulation' => $isSimulation,
                ],
                blankProduct: $blankProduct,
                blankProductionPlan: $blankProductionPlan,
                blankActualProduction: $blankActualProduction,
            ),
        );

        return [
            'concepts' => $concepts,
            'initialPlannedStock' => $initialPlannedStock,
            'orders' => $stagingOrders,
        ];
    }

    private function resolveWeightFactor(
        int $planningLevel,
        Product $product,
        ?Product $blankProduct = null
    ): float {
        if ($planningLevel === 2) {
            return (float) ($blankProduct?->quantityRequired ?? 0);
        }

        return (float) ($product->quantityRequired ?? 0);
    }

    private function calculateInitialPlannedStock(
        array $orders,
        float $initialQuantity,
        float $weightFactor
    ): int {
        $initialPlannedStock = (float) array_sum(
            array_map(
                static fn (array $order): float => (float) ($order['productionDifference'] ?? 0),
                $orders,
            ),
        );

        if ($weightFactor > 0) {
            $initialQuantity = max(
                0,
                (int) round($initialQuantity / $weightFactor),
            );

            $initialPlannedStock = max(
                0,
                $initialPlannedStock / $weightFactor,
            );
        }

        return (int) round(
            $initialPlannedStock + $initialQuantity
        );
    }
}
