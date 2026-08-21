<?php

namespace App\Http\Controllers\MRP;

use App\DTO\Concept;
use App\Http\Controllers\Controller;
use App\Jobs\MRP\PlanningEngineJob;
use App\Services\ConceptService;
use App\Services\MRP\PlanningEngineDataService;
use App\Services\MRP\ProductionPlanningService;
use App\Services\MRP\SimulationService;
use App\UseCases\MRP\RunEngineUseCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class SimulationController extends Controller
{
    public function index(Request $request, int $productionPlanningId): Response
    {
        $productionPlanning = app(ProductionPlanningService::class)
            ->getProductionPlanning($productionPlanningId);

        $conceptsMap = app(ConceptService::class)
            ->getConceptsMap($productionPlanning->template_code);

        $as400Connection = DB::connection('as400');

        $planningEngineData = app(PlanningEngineDataService::class)->getData($as400Connection, $productionPlanningId);
        $planningRange = $planningEngineData['planningRange'];
        $configuration = $planningEngineData['configuration'];
        $calendar = $planningEngineData['calendar'];

        $output = app(RunEngineUseCase::class)->execute(
            connection: $as400Connection,
            configuration: $configuration,
            calendar: $calendar,
            planningRange: $planningRange,
        );

        // dd($output['steelConsumption'] ?? []);

        $concepts = Concept::toKeyValue($output['concepts'] ?? [], $productionPlanningId);

        return Inertia::render('mrp/simulation/index', [
            'productionPlanningId' => $productionPlanningId,
            'concepts' => $concepts,
            'conceptsMap' => $conceptsMap,
            'planningRange' => $planningRange,
            'configuration' => $configuration,
            'orders' => array_map(function ($order) {
                return [
                    'date' => $order['dueDate'] ?? '',
                    'quantity' => $order['quantityRequired'],
                    'orderStatus' => $order['globalStatus'] ?? '',
                    'orderLocation' => $order['location'] ?? '',
                ];
            }, $output['orders'] ?? []),
        ]);
    }

    public function store(Request $request, int $productionPlanningId)
    {
        try {
            app(SimulationService::class)->store([
                'orders' => $request->input('orders', []),
                'productionPlan' => $request->input('productionPlan', []),
                'userId' => '00000',
            ]);

            $connection = DB::connection('as400');
            $dataConfiguration = app(PlanningEngineDataService::class)->getData($connection, $productionPlanningId);

            dispatch(new PlanningEngineJob(
                productionPlanningId: $productionPlanningId,
                configuration: $dataConfiguration['configuration'],
                calendar: $dataConfiguration['calendar'],
                planningRange: $dataConfiguration['planningRange'],
            ));

            return back()->with('success', 'Simulation data stored successfully.');
        } catch (\Throwable $th) {
            Log::error('Error storing simulation data: '.$th->getMessage(), [
                'productionPlanningId' => $productionPlanningId,
                'requestData' => $request->all(),
            ]);

            return back()->with('error', 'Failed to store simulation data. Please try again.');
        }
    }
}
