<?php

namespace App\Services\MRP;

use App\DTO\Entry;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

class ProductionService
{
    public function __construct() {}

    public function getActualProduction(
        Connection $connection,
        string|array $items,
        DateTimeImmutable $from,
        DateTimeImmutable $to
    ): array {
        $itemsArray = (array) $items;
        $placeholders = implode(',', array_fill(0, count($itemsArray), '?'));

        $sql = <<< SQL
        SELECT SUM(TQTY) AS TQTY, TSDTE
        FROM LX834F01.ITH
        WHERE TPROD IN ($placeholders) AND TTYPE IN ('PR', 'RJ') AND TSDTE BETWEEN ? AND ?
        GROUP BY TSDTE
        SQL;

        $params = [
            ...$itemsArray,
            $from->format('Ymd'),
            $to->format('Ymd'),
        ];

        $rows = $connection
            ->select($sql, $params);

        return array_map(
            fn ($row) => new Entry(
                date: $row->TSDTE,
                quantity: (float) $row->TQTY,
            ),
            $rows
        );
    }

    public function getProductionPlan(
        string|array $items,
        DateTimeImmutable $from,
        DateTimeImmutable $to
    ): array {
        $itemsArray = (array) $items;
        $placeholders = implode(',', array_fill(0, count($itemsArray), '?'));

        $sql = <<< SQL
        SELECT SUM(pp.quantity) AS quantity, CONVERT(VARCHAR(8), pp.planning_date, 112) AS planning_date
        FROM dbo.production_plan AS pp
        JOIN dbo.products pd
        ON (pp.product_id = pd.product_id)
        WHERE pd.product_code IN ($placeholders)
        AND pp.planning_date BETWEEN ? AND ?
        GROUP BY pp.planning_date
        SQL;

        $params = [
            ...$itemsArray,
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
        ];

        $rows = DB::connection('mrp')
            ->select($sql, $params);

        return array_map(
            fn ($row) => new Entry(
                date: $row->planning_date,
                quantity: (float) $row->quantity,
            ),
            $rows
        );
    }
}
