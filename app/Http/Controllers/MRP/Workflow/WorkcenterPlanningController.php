<?php

namespace App\Http\Controllers\MRP\Workflow;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class WorkcenterPlanningController extends Controller
{
    public function index(Request $request, string $workcenterCode)
    {
        $productionPlannings = DB::connection('mrp')
            ->select('EXEC productionPlanningsGetByWorkcenter ?', [$workcenterCode]);

        return Inertia::render('mrp/workflow/workcenter-planning/index', [
            'workcenterCode' => $workcenterCode,
            'productionPlannings' => array_map(function ($item) {
                return [
                    'materialId' => (int) $item->material_id,
                    'materialCode' => $item->material_code,
                    'classId' => (int) $item->class_id,
                    'classCode' => $item->class_code,
                    'classDescription' => $item->class_description,
                    'MDIId' => (int) $item->MDI_id,
                    'MDICode' => $item->MDI_code,
                    'level' => (int) $item->level,
                    'canBePlanned' => (bool) $item->canBePlanned,
                    'customerId' => (int) $item->customer_id,
                    'customerCode' => $item->customer_code,
                    'customerDescription' => $item->customer_description,
                    'productionPlanningId' => is_null($item->production_planning_id) ? null : (int) $item->production_planning_id,
                    'stockDays' => 0,
                    'lastRunAt' => is_null($item->last_runnat)
                        ? null
                        : date_create_immutable($item->last_runnat)->format('M d, Y h:i A'),
                ];
            }, $productionPlannings),
        ]);
    }
}
