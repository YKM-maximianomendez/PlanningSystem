<?php

namespace App\Services\MRP;

use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

class RawMaterialService
{
    public function __construct() {}

    public function getShopOrders(Connection $connection, string $material, ?DateTimeImmutable $cutOffDate = null): array
    {
        if ($cutOffDate === null) {
            return [];
        }

        $query = <<< 'SQL'
        WITH PRH_BASE AS (
            SELECT 
                MBM.BPROD,
                HPO.PDDTE AS PSDTE,
                HPO.PORD,
                HPO.PLINE,
                HPO.PSTAT,
                HPO.PQORD,
                HPO.PQREC,
                HPO.PUM,
                HPO.PCLAS,
                LEAD(HPO.PDDTE) OVER (
                    PARTITION BY MBM.BPROD
                    ORDER BY HPO.PDDTE
                ) AS NEXT_PDDTE,
                MBM.BQREQ
            FROM LX834F01.HPO HPO
            INNER JOIN LX834F01.MBM MBM
                ON (HPO.PPROD = MBM.BCHLD)
            WHERE HPO.PPROD = ?
                AND HPO.PDDTE >= ?
        ),
        PRH AS (
            SELECT
                BPROD,
                PSDTE,
                PORD,
                PLINE,
                PSTAT,
                PQORD,
                PQREC,
                PUM,
                PCLAS,
                BQREQ,
                CASE
                    WHEN NEXT_PDDTE IS NULL THEN 99991231
                    ELSE INTEGER(
                        VARCHAR_FORMAT(
                            DATE(TIMESTAMP_FORMAT(CHAR(NEXT_PDDTE), 'YYYYMMDD')) - 1 DAY,
                            'YYYYMMDD'
                        )
                    )
                END AS PEDTE
            FROM PRH_BASE
        ),
        TPP AS (
            SELECT
                PRH.BPROD,
                PRH.PSDTE,
                PRH.PEDTE,
                PRH.PORD,
                PRH.PLINE,
                PRH.PSTAT,
                PRH.PQORD,
                PRH.PQREC,
                PRH.PUM,
                PRH.PCLAS,
                PRH.BQREQ,
                COALESCE(SUM(ITH.TQTY), 0) AS TTRAN,
                DECIMAL((COALESCE(SUM(ITH.TQTY), 0) * PRH.BQREQ), 18, 3) AS TTRSN
            FROM PRH PRH
            LEFT JOIN LX834F01.ITH ITH
                ON (PRH.BPROD = ITH.TPROD)
            AND ITH.TSDTE >= PRH.PSDTE
            AND ITH.TSDTE < 
                COALESCE(
                    PRH.PEDTE,
                    INTEGER(VARCHAR_FORMAT(CURRENT DATE, 'YYYYMMDD'))
                )
            AND ITH.TTYPE IN ('PR', 'RJ')
            GROUP BY 
                PRH.BPROD, PRH.PSDTE, PRH.PEDTE, PRH.PORD, PRH.PLINE, 
                PRH.PSTAT, PRH.PQORD, PRH.PQREC, PRH.PUM, PRH.PCLAS, PRH.BQREQ
        )
        SELECT
            LISTAGG(TRIM(TPP.BPROD), ', ') WITHIN GROUP(ORDER BY TPP.BPROD) AS PPROS,
            TPP.PSDTE,
            CASE WHEN TPP.PEDTE = 99991231 THEN NULL ELSE TPP.PEDTE END AS PEDTE,
            TPP.PSTAT,
            MAX(TPP.TTRAN) AS TTRAN,
            SUM(TPP.TTRSN) AS TTRSN,
            TPP.PQORD,
            TPP.PQREC,
            TPP.PUM,
            TPP.PCLAS,
            SUM(TPP.BQREQ) AS BQREQ,
            TPP.PORD,
            TPP.PLINE,
            (TPP.PQREC - SUM(TPP.TTRSN)) AS PRDIF
        FROM TPP TPP
        GROUP BY 
            TPP.PSDTE,
            TPP.PEDTE,
            TPP.PORD,
            TPP.PLINE,
            TPP.PSTAT,
            TPP.PQORD,
            TPP.PQREC,
            TPP.PUM,
            TPP.PCLAS
        ORDER BY 
            TPP.PSDTE
        WITH UR
        SQL;

        $resultset = $connection
            ->select($query, [$material, $cutOffDate->format('Ymd')]);

        $data = [];

        foreach ($resultset as $row) {
            $data[] = [
                'product' => $row->PPROS,
                'startDate' => $row->PSDTE,
                'endDate' => $row->PEDTE,
                'status' => $row->PSTAT,
                'totalTransaction' => (float) $row->TTRAN,
                'totalTransactionReason' => (float) $row->TTRSN,
                'quantityOrdered' => (float) $row->PQORD,
                'quantityReceived' => (float) $row->PQREC,
                'unitOfMeasure' => $row->PUM,
                'class' => $row->PCLAS,
                'requiredQuantity' => (float) $row->BQREQ,
                'orderNumber' => $row->PORD,
                'lineNumber' => $row->PLINE,
                'productionDifference' => (float) $row->PRDIF,
            ];
        }

        return $data;
    }

    public function getStagingOrders(
        Connection $connection,
        string $material,
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): array {
        $query = <<< 'SQL'
        SELECT COALESCE(HPO.PDDTE, Y61.Y61DTE, Y60.Y6DDTE) AS PSDTE,
            COALESCE(HPO.PQORD, Y61.Y61QTY, Y60.Y6QORD) AS QTY_REQUIRED,
            COALESCE(HPO.PQREC, 0) AS QTY_RECEIVED,
            COALESCE(HPO.PPROD, Y61.Y61PRO, Y60.Y6PROD) AS PROD,
            COALESCE(HPO.PORD, Y61.Y61ORD) AS PORD,
            COALESCE(HPO.PLINE, Y61.Y61LIN) AS PLINE,
            CASE
                WHEN HPO.PORD IS NOT NULL THEN
                        HPO.PSTAT
                WHEN Y61.Y61STS = 'E' THEN
                    'POSTED WITH ERRORS'
                WHEN Y61.Y61ORD IS NOT NULL THEN
                    'POSTING'
                ELSE
                    'IN STAGE'
            END AS STATUS,
            CASE
                WHEN HPO.PORD IS NOT NULL THEN
                    'HPO'
                WHEN Y61.Y61STS = 'E' THEN
                    'YH0161'
                WHEN Y61.Y61ORD IS NOT NULL THEN
                    'YH0161'
                ELSE
                    'YH016'
            END AS LOCATION
        FROM LX834F01.HPO HPO
            FULL OUTER JOIN LX834FU02.YH0161 Y61
                ON HPO.PORD = Y61.Y61ORD
                AND HPO.PLINE = Y61.Y61LIN
                AND HPO.PPROD = Y61.Y61PRO
                AND HPO.PQORD = Y61.Y61QTY
                AND HPO.PDDTE = Y61.Y61DTE
                AND HPO.PVEND = Y61.Y61VND
            FULL OUTER JOIN LX834FU02.YH016 Y60
                ON Y60.Y6VEND = COALESCE(Y61.Y61VND, HPO.PVEND)
                AND Y60.Y6WC = Y61.Y61WC
                AND Y60.Y6PROD = COALESCE(Y61.Y61PRO, HPO.PPROD)
                AND Y60.Y6DDTE = COALESCE(Y61.Y61DTE, HPO.PDDTE)
                AND Y60.Y6QORD = COALESCE(Y61.Y61QTY, HPO.PQORD)
        WHERE COALESCE(HPO.PPROD, Y61.Y61PRO, Y60.Y6PROD) = ?
            AND COALESCE(HPO.PDDTE, Y61.Y61DTE, Y60.Y6DDTE)
            BETWEEN ? AND ?
        ORDER BY COALESCE(HPO.PDDTE, Y61.Y61DTE, Y60.Y6DDTE),
                COALESCE(HPO.PORD, Y61.Y61ORD)
        WITH UR
        SQL;

        $resultset = $connection
            ->select($query, [$material, $start->format('Ymd'), $end->format('Ymd')]);

        $data = [];

        foreach ($resultset as $row) {
            $data[] = [
                'dueDate' => date_create_immutable($row->PSDTE)?->format('Ymd'),
                'quantityRequired' => (float) $row->QTY_REQUIRED,
                'quantityReceived' => (float) $row->QTY_RECEIVED,
                'globalStatus' => $row->STATUS,
                'location' => $row->LOCATION,
            ];
        }

        return $data;
    }

    public function getConfirmedOrders(int $materialId, int $vendorId, DateTimeImmutable $start, DateTimeImmutable $end, array $stagingOrders): array
    {
        $query = <<< 'SQL'
        SELECT   CONVERT (CHAR (8), confirmed_date, 112) AS date_confirmed,
                SUM(confirmed_quantity) AS quantity_confirmed
        FROM     dbo.orders
        WHERE    material_id = ?
                AND vendor_id = ?
                AND required_date BETWEEN ? AND ?
        GROUP BY confirmed_date
        SQL;

        $resultset = DB::connection('mrp')
            ->select($query, [$materialId, $vendorId, $start->format('Y-m-d'), $end->format('Y-m-d')]);

        $confirmedByDate = collect($resultset)
            ->keyBy('date_confirmed');

        $result = [];

        foreach ($stagingOrders as $order) {
            $date = $order['dueDate'];

            if ($confirmedByDate->has($date)) {
                $result[$date] = (float) $confirmedByDate[$date]->quantity_confirmed;

                continue;
            }

            $result[] = [
                'date' => $date,
                'quantity' => $order['globalStatus'] != '0'
                    ? (float) $order['quantityReceived']
                    : (float) $order['quantityRequired'],
            ];
        }

        return $result;

    }
}
