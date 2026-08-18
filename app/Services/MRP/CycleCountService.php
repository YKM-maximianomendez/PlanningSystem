<?php

namespace App\Services\MRP;

use App\DTO\Configuration\CycleCount;
use DateTimeImmutable;
use Illuminate\Database\Connection;

readonly class CycleCountService
{
    public function getLastCycleCounts(Connection $connection, array $products, DateTimeImmutable $planningStart): array
    {
        $transactionTypes = ['PR', 'RJ', 'CI', 'QC'];
        $transactionTypesPlaceholder = implode(',', array_fill(0, count($transactionTypes), '?'));

        $placeholders = implode(',', array_fill(0, count($products), '?'));

        $fromStr = $planningStart->format('Ymd');

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
        ),
        ITH AS (
            SELECT
                ITH.TPROD AS PRODUCT,
                SUM(ITH.TQTY) AS DIFF_QTY
            FROM LX834F01.ITH ITH
            INNER JOIN LASTINV ON ITH.TPROD = LASTINV.PRODUCT
            WHERE ITH.TTYPE IN ($transactionTypesPlaceholder)
                AND ITH.TID = 'TH'
                AND ITH.TSDTE BETWEEN LASTINV.SNAP_DATE AND ?
            GROUP BY ITH.TPROD
        )
        SELECT
            LI.PRODUCT,
            LI.SNAP_DATE,
            LI.SNAP_QTY,
            COALESCE(TH.DIFF_QTY, 0) AS DIFF_QTY
        FROM LASTINV LI
        LEFT JOIN ITH TH ON LI.PRODUCT = TH.PRODUCT
        WITH UR
        SQL;

        $resultset = $connection
            ->select($query, [...$products, $fromStr, ...$transactionTypes, $fromStr]);

        $cycleCounts = [];

        foreach ($resultset as $row) {
            $snapQty = (float) $row->SNAP_QTY;
            $diffQty = (float) $row->DIFF_QTY;

            $cycleCounts[trim($row->PRODUCT)] = new CycleCount(
                date: new DateTimeImmutable($row->SNAP_DATE),
                quantity: $snapQty,
                consumed: $diffQty,
                theoricalQuantity: $snapQty + $diffQty
            );
        }

        return $cycleCounts;
    }

    public function getCycleCountsAdjustments(Connection $connection, array $products, DateTimeImmutable $planningStart): array
    {
        $productPlaceholders = implode(',', array_fill(0, count($products), '?'));
        $planningStartStr = $planningStart->format('Ymd');

        $query = <<< SQL
        SELECT 
            IHPROD AS PRODUCT,
            SNAP_DATE,
            SNAP_QTY
        FROM (
            SELECT
                INV.IHPROD,
                INV.IHIDTE AS SNAP_DATE,
                INV.IHIQTY AS SNAP_QTY,
                ROW_NUMBER() OVER (PARTITION BY INV.IHPROD, INV.IHIDTE ORDER BY INV.IHIDTE ASC) AS ROWNM
            FROM LX834FU01.YIINH INV
            WHERE INV.IHLOC = 'L12'
                AND INV.IHWHS = 'W10'
                AND INV.IHPROD IN ($productPlaceholders)
                AND INV.IHIDTE >= ?
        ) WN
        WHERE ROWNM = 1
        ORDER BY PRODUCT, SNAP_DATE ASC
        SQL;

        $params = [...$products, $planningStartStr];
        $rows = $connection->select($query, $params);

        $adjustmentsByProduct = [];

        foreach ($rows as $row) {
            $adjustmentsByProduct[trim($row->PRODUCT)][] = [
                'date' => $row->SNAP_DATE,
                'value' => (float) $row->SNAP_QTY,
            ];
        }

        return $adjustmentsByProduct;
    }
}
