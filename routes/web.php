<?php

use App\Services\MRP\DeliveryInstructionService;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/test', function () {
        app(DeliveryInstructionService::class)->__construct();
        echo 0;
    })->name('test');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/web/mrp.php';
