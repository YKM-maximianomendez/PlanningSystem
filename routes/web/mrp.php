<?php

use App\Http\Controllers\MRP\SimulationController;
use App\Http\Controllers\MRP\Workflow\WorkcenterPlanningController;
use App\Http\Controllers\MRP\Workflow\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->group(function () {
        Route::controller(SimulationController::class)
            ->prefix('mrp/simulation')
            ->name('mrp.simulation.')
            ->group(function () {
                Route::get('/{productionPlanningId}', 'index')->name('index');
            });

        Route::prefix('mrp/workflow')
            ->name('mrp.workflow.')
            ->group(function () {
                Route::get('/', [WorkflowController::class, 'index'])->name('index');
                Route::get('/{workcenterCode}', [WorkcenterPlanningController::class, 'index'])->name('workcenter-planning.index');
            });
    });
