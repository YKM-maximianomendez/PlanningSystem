<?php

namespace App\Services\MRP;

use Illuminate\Support\Facades\DB;

class ProductionPlanningService
{
    public function __construct() {}

    public function getProductionPlanning(int $productionPlanningId): ?object
    {
        $productionPlanning = DB::connection('mrp')->table('production_plannings')
            ->where('production_planning_id', $productionPlanningId)
            ->select('template_code', 'level')
            ->first();

        return $productionPlanning ? $productionPlanning : null;
    }
}
