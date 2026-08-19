<?php

namespace App\Http\Controllers\MRP\Workflow;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DraftController extends Controller
{
    public function index(string $workcenterCode)
    {
        $orders = DB::connection('as400')
            ->select(<<< 'SQL'
            	SELECT COALESCE(Y60.Y6DDTE, Y61.Y61DTE) AS PSDTE,
                    COALESCE(Y60.Y6QORD, Y61.Y61QTY) AS PQORD,
                    COALESCE(Y60.Y6PROD, Y61.Y61PRO) AS PPROD,
                    COALESCE(Y61.Y61ORD, 0) AS PORD,
                    COALESCE(Y61.Y61LIN, 0) AS PLINE,
                    CASE
                        WHEN Y60.Y6PROD IS NOT NULL THEN
                            'ED'
                        WHEN Y61.Y61STS = 'E' THEN
                            'PE'
                        WHEN Y61.Y61ORD IS NOT NULL THEN
                            'PO'
                    ELSE 'UN'
                    END AS PSTAT,
                    COALESCE(Y61.Y61TXT, '') AS PTXT,
                    CASE
                        WHEN Y60.Y6PROD IS NOT NULL THEN
                            'YH0160'
                        WHEN Y61.Y61STS = 'E' THEN
                            'YH0161'
                        WHEN Y61.Y61ORD IS NOT NULL THEN
                            'YH0161'
                        ELSE
                            'HPO'
                    END AS PLOC,
                    AVM.VENDOR,
                    AVM.VNDNAM
                FROM LX834FU02.YH016 Y60
                    FULL OUTER JOIN LX834FU02.YH0161 Y61
                        ON Y60.Y6VEND = Y61.Y61VND
                        AND Y60.Y6WC = Y61.Y61WC
                        AND Y60.Y6PROD = Y61.Y61PRO
                        AND Y60.Y6DDTE = Y61.Y61DTE
                        AND Y60.Y6QORD = Y61.Y61QTY
                    JOIN LX834F01.AVM AVM
                        ON (AVM.VENDOR = COALESCE(Y60.Y6VEND, Y61.Y61VND))
                WHERE COALESCE(Y60.Y6WC, Y61.Y61WC) = ?
                ORDER BY PSDTE, PPROD, PORD, PLINE
                WITH UR
            SQL, [$workcenterCode]);

        return Inertia::render('mrp/workflow/draft/index', [
            'workcenterCode' => $workcenterCode,
            'orders' => array_map(function ($order) {
                return [
                    'productCode' => trim($order->PPROD),
                    'quantityOrdered' => (float) $order->PQORD,
                    'dueDate' => date_create_from_format('Ymd', (string) $order->PSDTE)->format('Y-m-d'),
                    'vendorCode' => trim($order->VENDOR),
                    'vendorName' => trim($order->VNDNAM),
                    'status' => $order->PSTAT,
                    'text' => $order->PTXT,
                    'location' => $order->PLOC,
                    'orderNumber' => empty($order->PORD) ? null : str_pad($order->PORD, 8, '0', STR_PAD_LEFT),
                    'lineNumber' => empty($order->PLINE) ? null : str_pad($order->PLINE, 4, '0', STR_PAD_LEFT),
                    'order' => empty($order->PORD) ? null : str_pad($order->PORD, 8, '0', STR_PAD_LEFT).''.str_pad($order->PLINE, 4, '0', STR_PAD_LEFT),
                ];
            }, $orders),
        ]);
    }

    public function store(Request $request)
    {
        $workcenterCode = $request->input('workcenterCode');
    }
}
