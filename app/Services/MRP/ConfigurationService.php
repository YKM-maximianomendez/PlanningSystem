<?php

namespace App\Services\MRP;

use App\DTO\Configuration;
use App\DTO\Configuration\Adjustment;
use App\DTO\Configuration\Customer;
use App\DTO\Configuration\CycleCount;
use App\DTO\Configuration\Material;
use App\DTO\Configuration\MDI;
use App\DTO\Configuration\Product;
use App\DTO\Configuration\Vendor;
use App\Enums\ForecastStrategy;
use App\Enums\ShopCalendar;
use App\ValueObjects\PlanningRange;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConfigurationService
{
    public function __construct(
        private readonly CycleCountService $cycleCountService
    ) {}

    public function getConfiguration(
        Connection $connection,
        int $materialPlanningId,
        PlanningRange $planningRange,
        array $preloadedCycleCounts = [],
        ?array $rawConfiguration = null,
    ): ?Configuration {
        $rawConfiguration = $rawConfiguration ?? $this->getRawConfiguration($materialPlanningId);
        $data = collect($rawConfiguration);

        if ($data->isEmpty()) {
            return null;
        }

        $planningLevel = (int) $data->first()?->planning_level;
        $mdiId = (int) $data->first()?->planning_mdi_id;

        $products = $this->getProducts($connection, $data, $planningRange->start, $planningRange->end, $preloadedCycleCounts);
        $customer = $this->getCustomer($data);
        $material = $this->getMaterial($connection, $data, $planningRange->start);
        $forecastStrategy = $this->getForecastStrategy($materialPlanningId);
        $adjustments = $this->getAdjustments($materialPlanningId);
        $mdi = $this->getMDI($data, $planningLevel, $mdiId);

        $planningProduct = Configuration::getPlanningProduct($products, $planningLevel, $mdiId);
        $blankProduct = $planningLevel === 2
            ? collect($products)->firstWhere('level', 1)
            : null;

        return new Configuration(
            level: $planningLevel,
            material: $material,
            mdi: $mdi,
            products: $products,
            planningProduct: $planningProduct,
            customer: $customer,
            forecastStrategy: $forecastStrategy,
            calendar: $customer->isMMVO() ? ShopCalendar::MMVO : ShopCalendar::YKM0,
            adjustments: $adjustments,
            blankProduct: $blankProduct
        );
    }

    private function getProducts(
        Connection $connection,
        Collection $data,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        array $preloadedCycleCounts = []
    ): array {
        $cycleCounts = ! empty($preloadedCycleCounts)
            ? $preloadedCycleCounts
            : $this->cycleCountService->getLastCycleCounts(
                connection: $connection,
                products: $data->pluck('product')->unique()->all(),
                planningStart: $start,
            );

        $inventoryAdjustments = $this->cycleCountService->getCycleCountsAdjustments(
            connection: $connection,
            products: $data->pluck('product')->unique()->all(),
            planningStart: $start,
        );

        return $data->map(function ($item) use ($cycleCounts, $inventoryAdjustments) {
            $cycleCount = $cycleCounts[trim($item->product)] ?? new CycleCount(null, 0, 0, 0);
            $product = new Product(
                level: $item->level,
                productId: $item->product_id,
                productCode: trim($item->product),
                mdiId: $item->MDI_id,
                mdiCode: trim($item->MDI),
                workcenterId: $item->workcenter_id,
                workcenterCode: trim($item->workcenter),
                um: $item->um,
                quantityRequired: $item->quantity_required,
                isObsolete: $item->is_obsolete,
                lastCycleCount: $cycleCount,
                cycleCountAdjustments: $inventoryAdjustments[trim($item->product)] ?? [],
            );

            return $product;
        })->unique('productId')
            ->values()
            ->all();
    }

    private function getCustomer(Collection $data): Customer
    {
        return $data->map(function ($item) {
            return new Customer(
                customerId: $item->customer,
                customerCode: $item->customer,
            );
        })->first();
    }

    private function getMaterial(Connection $connection, Collection $data, DateTimeImmutable $planningStart): Material
    {
        $cutoffStartDate = $data->first()?->material_cutoff_start_date;
        $initialQuantity = 0;

        if (is_null($cutoffStartDate)) {
            $lastInventory = $this->getLastInventoryDate(
                $data->first()?->material,
                $planningStart,
                $connection
            );
            $cutoffStartDate = date_create_immutable($lastInventory?->SNAP_DATE);
            $initialQuantity = (float) ($lastInventory?->SNAP_QTY ?? 0);
        }

        return $data->map(function ($item) use ($cutoffStartDate, $initialQuantity) {
            return new Material(
                materialId: $item->material_id,
                materialCode: trim($item->material),
                classId: $item->class_id,
                classCode: $item->material_class,
                classDescription: $item->class_description,
                vendor: new Vendor(
                    vendorId: $item->vendor_id,
                    vendorCode: $item->vendor_code,
                    vendorDescription: $item->vendor_description,
                ),
                um: $item->material_um,
                isObsolete: $item->material_is_obsolete,
                options: [
                    'cutoff_start_date' => $cutoffStartDate?->format('Ymd'),
                    'cutoff_quantity' => $initialQuantity,
                ]
            );
        })->first();
    }

    private function getForecastStrategy(int $materialPlanningId): ForecastStrategy
    {
        return ForecastStrategy::SUM;
    }

    private function getAdjustments(int $materialPlanningId): array
    {
        // return [
        //     new Adjustment(
        //         adjustmentId: 1,
        //         adjustmentCode: 'A001',
        //         adjustmentValue: 6,
        //     ),
        //     new Adjustment(
        //         adjustmentId: 2,
        //         adjustmentCode: 'A002',
        //         adjustmentValue: 0,
        //     ),
        // ];
        return [];
    }

    private function getMDI(Collection $data, int $planningLevel, int $mdiId): MDI
    {
        return $data->where(fn ($item) => intval($item->level) === $planningLevel && intval($item->MDI_id) === $mdiId)
            ->map(function ($item) {
                return new MDI(
                    mdiId: $item->MDI_id,
                    mdiCode: trim($item->MDI),
                );
            })->first();
    }

    public function getRawConfiguration(int $productionPlanningId): array
    {
        $query = <<< 'SQL'
        SELECT fb.[level],
            fb.[material_id],
            fb.[material],
            fb.[material_class_id],
            fb.[material_class],
            fb.[material_class_description],
            fb.[material_workcenter],
            fb.[material_vendor],
            fb.[vendor_id],
            fb.[vendor_code],
            fb.[vendor_description],
            fb.[material_um],
            fb.[material_is_obsolete],
            fb.[material_effective_date],
            fb.[material_discontinued_date],
            fb.[material_allow_ds],
            fb.[material_cutoff_start_date],
            fb.[product_id],
            fb.[product],
            fb.[customer],
            fb.[projects],
            fb.[is_obsolete],
            fb.[effective_date],
            fb.[discontinued_date],
            fb.[um],
            fb.[class_id],
            fb.[class],
            fb.[class_description],
            fb.[workcenter_id],
            fb.[workcenter],
            fb.[quantity_required],
            fb.[path],
            fb.[MDI_id],
            fb.[MDI],
            fb.[MDI_product],
            pp.[level] AS planning_level,
            pp.[mdi_id] AS planning_mdi_id
        FROM   [dbo].[vw_masterBOM] AS fb
            INNER JOIN
            dbo.production_plannings AS pp
            ON (fb.material_id = pp.material_id)
        WHERE  pp.production_planning_id = ?;
        SQL;

        $results = DB::connection('mrp')->select($query, [$productionPlanningId]);

        return $results;
    }

    private function getLastInventoryDate(string $material, DateTimeImmutable $planningStart, Connection $connection): ?object
    {

        $placeholders = implode(',', array_fill(0, count([$material]), '?'));

        $query = <<< SQL
         WITH LASTINV_ALL AS (
            SELECT
                INV.IHPROD AS PRODUCT,
                INV.IHIDTE AS SNAP_DATE,
                INV.IHIQTY AS SNAP_QTY,
                ROW_NUMBER() OVER(PARTITION BY INV.IHPROD ORDER BY INV.IHIDTE DESC) AS RN
            FROM
                LX834FU01.YIINH INV
            WHERE
                INV.IHLOC = 'L12'
                AND INV.IHWHS = 'W10'
                AND INV.IHPROD IN ($placeholders)
                AND INV.IHIDTE <= ?
        ),
        LASTINV AS (
            SELECT PRODUCT, SNAP_DATE, SNAP_QTY FROM LASTINV_ALL WHERE RN = 1
        )
        SELECT SNAP_DATE, SNAP_QTY FROM LASTINV
        SQL;

        $resultset = $connection->selectOne($query, [$material, $planningStart->format('Ymd')]);

        return $resultset;
    }
}
