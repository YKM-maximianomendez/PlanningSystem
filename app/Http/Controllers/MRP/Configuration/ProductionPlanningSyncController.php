<?php

namespace App\Http\Controllers\MRP\Configuration;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProductionPlanningSyncController extends Controller
{
    public function __invoke(Request $request, string $workcenterCode)
    {
        $query = "SELECT DISTINCT
            m.[material_id],
            mdi.[MDI_id] AS [mdi_id],
            w.[workcenter_id],
            r.[level],
            CASE
                WHEN c.[class_code] IN ('T1', 'T2') AND r.[level] = 1 THEN 'CL1'
                WHEN c.[class_code] IN ('T1', 'T2') AND r.[level] = 2 THEN 'CL2'
                WHEN c.[class_code] = 'T3'        AND r.[level] = 1 THEN 'SH1'
                WHEN c.[class_code] = 'T3'        AND r.[level] = 2 THEN 'SH2'
                ELSE ''
            END AS [template_code]
        FROM [dbo].[vw_bomPlanning] AS r
        INNER JOIN [dbo].[workcenters] AS w
            ON TRIM(r.[planningWorkcenter]) = TRIM(w.[workcenter_code])
        INNER JOIN [dbo].[materials] AS m
            ON r.[material] = m.[material_code]
        INNER JOIN [dbo].[classes] AS c
            ON m.[class_id] = c.[class_id]
        INNER JOIN [dbo].[MDI] AS mdi
            ON mdi.[MDI_code] = r.[mdi]
            AND mdi.[workcenter_id] = w.[workcenter_id]
        WHERE
            r.[material_is_obsolete] = 0
            AND r.[level] = r.[planningLevel]
            AND r.[canBePlanned] = 1
            AND r.[material_allow_ds] = 0
            AND r.[planningWorkcenter] = ?";

        $results = \DB::connection('mrp')->select($query, [$workcenterCode]);

        foreach ($results as $result) {
            \Log::info("Synchronizing production planning for workcenter {$workcenterCode}, material ID {$result->material_id}, MDI ID {$result->mdi_id}, level {$result->level}, template code {$result->template_code}");
        }

        return back()->with('success', "Production planning for workcenter {$workcenterCode} has been synchronized successfully.");
    }
}
