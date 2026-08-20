<?php

namespace App\Services\MRP;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SimulationService
{
    public function __construct() {}

    public function store(array $data): void
    {
        $orders = $data['orders'] ?? [];
        $productionPlan = $data['productionPlan'] ?? [];

        $db2Connection = DB::connection('as400');
        $mrpConnection = DB::connection('mrp');

        $db2Connection->beginTransaction();
        $mrpConnection->beginTransaction();

        try {
            foreach ($orders as $order) {
                $this->mergeOrder($db2Connection, $order);
            }

            foreach ($productionPlan as $plan) {
                $this->mergeProductionPlan($mrpConnection, $plan, $data['userId'] ?? 'system');
            }

            $db2Connection->commit();
            $mrpConnection->commit();
        } catch (\Throwable $th) {
            $db2Connection->rollBack();
            $mrpConnection->rollBack();
            Log::error('Error storing simulation data: '.$th->getMessage(), [
                'orders' => $orders,
                'productionPlan' => $productionPlan,
            ]);
            throw $th;
        }
    }

    private function mergeProductionPlan(Connection $connection, array $productionPlan, string $userId): void
    {
        $query = <<< 'SQL'
            MERGE INTO dbo.production_plan AS target
            USING (
                VALUES (
                    :workcenterId,
                    :productId,
                    :quantity,
                    CAST(:planningDate AS date),
                    :um,
                    :createdBy
                )
            ) AS source (
                workcenter_id,
                product_id,
                quantity,
                planning_date,
                um,
                created_by
            )
            ON (
                target.product_id = source.product_id
                AND target.planning_date = source.planning_date
            )

            WHEN MATCHED AND source.quantity = 0 THEN
                DELETE

            WHEN MATCHED THEN
                UPDATE SET
                    target.workcenter_id = source.workcenter_id,
                    target.quantity = source.quantity,
                    target.um = source.um,
                    target.updated_at = GETDATE(),
                    target.updated_by = source.created_by

            WHEN NOT MATCHED BY TARGET AND source.quantity > 0 THEN
                INSERT (
                    workcenter_id,
                    product_id,
                    quantity,
                    planning_date,
                    um,
                    created_at,
                    created_by
                )
                VALUES (
                    source.workcenter_id,
                    source.product_id,
                    source.quantity,
                    source.planning_date,
                    source.um,
                    GETDATE(),
                    source.created_by
                );
        SQL;

        $statement = $connection->getPdo()->prepare($query);
        $statement->execute([
            ':productId' => $productionPlan['productId'],
            ':planningDate' => $productionPlan['date'],
            ':quantity' => (float) $productionPlan['quantity'],
            ':workcenterId' => $productionPlan['workcenterId'],
            ':um' => $productionPlan['um'] ?? null,
            ':createdBy' => $userId,
        ]);
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
                target.Y6VEND = source.VEND
                AND target.Y6WC = source.WC
                AND TRIM(target.Y6PROD) = TRIM(source.PROD)
                AND target.Y6DDTE = source.DDTE
            )
            WHEN MATCHED AND source.QORD = 0 THEN
                DELETE
            WHEN MATCHED THEN
                UPDATE SET target.Y6QORD = source.QORD
            WHEN NOT MATCHED AND source.QORD > 0 THEN
                INSERT (
                    Y6VEND,
                    Y6WC,
                    Y6PROD,
                    Y6DDTE,
                    Y6QORD
                )
                VALUES (
                    source.VEND,
                    source.WC,
                    source.PROD,
                    source.DDTE,
                    source.QORD
                )
        SQL;

        $statement = $connection->getPdo()->prepare($query);
        $statement->execute([
            ':VEND' => $order['vendorCode'],
            ':WC' => $order['workcenterCode'],
            ':PROD' => $order['productCode'],
            ':DDTE' => $date,
            ':QORD' => (float) $quantity,
        ]);
    }
}
