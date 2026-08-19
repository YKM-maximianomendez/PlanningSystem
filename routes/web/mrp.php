<?php

use App\Http\Controllers\MRP\Configuration\ProductionPlanningSyncController;
use App\Http\Controllers\MRP\SimulationController;
use App\Http\Controllers\MRP\Workflow\DraftController;
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
                Route::post('/{productionPlanningId}', 'store')->name('store');
            });

        Route::prefix('mrp/workflow')
            ->name('mrp.workflow.')
            ->group(function () {
                Route::get('/', [WorkflowController::class, 'index'])->name('index');
                Route::get('/{workcenterCode}', [WorkcenterPlanningController::class, 'index'])->name('workcenter-planning.index');
            });

        Route::prefix('mrp/configuration')
            ->name('mrp.configuration.')
            ->group(function () {
                Route::post('/sync-production-planning/{workcenterCode}', [ProductionPlanningSyncController::class, '__invoke'])
                    ->name('sync-production-planning');
            });

        Route::prefix('mrp/workflow/draft')
            ->name('mrp.workflow.draft.')
            ->group(function () {
                Route::get('/{workcenterCode}', [DraftController::class, 'index'])->name('index');
            });
    });
