<?php

namespace App\Services\MRP;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

class SimulationService
{
    public function __construct() {}

    public function store(array $data): void
    {
        $orders = $data['orders'] ?? [];
        $productionPlan = $data['productionPlan'] ?? [];

        $db2Connection = DB::connection('as400');

        $db2Connection->beginTransaction();

        try {
            foreach ($orders as $order) {
                $this->mergeOrder($db2Connection, $order);
            }
            $db2Connection->commit();
        } catch (\Throwable $th) {
            $db2Connection->rollBack();
            \Log::error('Error storing simulation data: '.$th->getMessage(), [
                'orders' => $orders,
                'productionPlan' => $productionPlan,
            ]);
            throw $th;
        }
    }

    private function mergeOrder(Connection $connection, array $order): void
    {
        $date = $order['date'];
        $quantity = $order['quantity'];

        $query = <<< 'SQL'
            MERGE INTO LX834FU02.YH016 AS target
            USING (
                VALUES (
                    CAST(:VEND AS DECIMAL(8, 0)),
                    CAST(:WC AS DECIMAL(6, 0)),
                    CAST(:PROD AS CHAR(35)),
                    CAST(:DDTE AS DECIMAL(8, 0)),
                    CAST(:QORD AS DECIMAL(11, 3))
                )
            ) AS source (VEND, WC, PROD, DDTE, QORD)
            ON (
                target.Y6VEND = source.VEND AND
                target.Y6WC = source.WC AND
                target.Y6PROD = source.PROD AND
                target.Y6DDTE = source.DDTE
            )
            WHEN MATCHED AND source.QORD = 0 THEN
                DELETE
            WHEN MATCHED THEN
                UPDATE SET target.Y6QORD = source.QORD
            WHEN NOT MATCHED AND source.QORD > 0 THEN
                INSERT (Y6VEND, Y6WC, Y6PROD, Y6DDTE, Y6QORD)
                VALUES (source.VEND, source.WC, source.PROD, source.DDTE, source.QORD)
        SQL;

        $statement = $connection->getPdo()->prepare($query);
        $statement->execute([
            ':VEND' => $order['vendorCode'],
            ':WC' => $order['workcenterCode'],
            ':PROD' => $order['productCode'],
            ':DDTE' => $date,
            ':QORD' => $quantity,
        ]);
    }
}
