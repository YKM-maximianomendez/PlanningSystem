<?php

use App\Http\Controllers\MRP\SimulationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->group(function () {
        Route::controller(SimulationController::class)
            ->prefix('mrp/simulation')
            ->name('mrp.simulation.')
            ->group(function () {
                Route::get('/{productionPlanningId}', 'index')->name('index');
            });
    });
