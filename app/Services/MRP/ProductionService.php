<?php

namespace App\Services\MRP;

use App\DTO\Entry;
use DateTimeImmutable;
use Illuminate\Database\Connection;

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
        SELECT SUM(TQTY) AS TQTY,TSDTE
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
}
