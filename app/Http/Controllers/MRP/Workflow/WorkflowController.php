<?php

namespace App\Http\Controllers\MRP\Workflow;

use App\Http\Controllers\Controller;
use App\Models\Workcenter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkflowController extends Controller
{
    private array $priorityMap = [
        'Prensa Progresiva' => 1,
        'Prensa Hot Stamp' => 2,
        'Prensa de Transferencia' => 3,
    ];

    public function index(Request $request)
    {
        $groupedWorkcenters = Workcenter::with('workcenterType')
            ->whereHas('workcenterType')
            ->get()
            ->map(fn (Workcenter $workcenter) => [
                'workcenterId' => $workcenter->workcenter_id,
                'workcenterCode' => $workcenter->workcenter_code,
                'workcenterDescription' => $workcenter->workcenter_description,
                'workcenterTypeName' => $workcenter->workcenterType->workcenter_type_description,
            ])
            ->groupBy(fn (array $wc) => $wc['workcenterTypeName'])
            ->map(function ($workcenters, $title) {
                return [
                    'title' => $title,
                    'workcenters' => $workcenters->values(),
                    'priority' => $this->priorityMap[$title] ?? 999,
                ];
            })
            ->sortBy('priority')
            ->values()
            ->toArray();

        return Inertia::render('mrp/workflow/index', [
            'groupedWorkcenters' => $groupedWorkcenters,
        ]);
    }
}
