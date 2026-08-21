<?php

namespace App\Console\Commands\MRP;

use App\Jobs\MRP\PlanningEngineJob;
use App\Services\MRP\PlanningEngineDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncPlanningPrograms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-planning-programs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Syncing planning programs...');

        $ids = DB::connection('mrp')
            ->table('production_plannings')
            ->take(10)
            ->pluck('production_planning_id');

        $jobs = [];

        $connection = DB::connection('as400');

        foreach ($ids as $id) {
            $dataConfiguration = app(PlanningEngineDataService::class)->getData($connection, $id);
            $jobs[] = new PlanningEngineJob(
                productionPlanningId: $id,
                configuration: $dataConfiguration['configuration'],
                calendar: $dataConfiguration['calendar'],
                planningRange: $dataConfiguration['planningRange'],
            );
        }

        $this->info('Dispatching jobs...');

        Bus::batch($jobs)
            ->then(function ($batch) {
                Log::info('Recálculo masivo completado.');
            })
            ->catch(function ($batch, $e) {
                Log::error('Error en el lote de recálculo: '.$e->getMessage());
            })
            ->finally(function ($batch) {
                // Se ejecuta al final, pase lo que pase
            })
            ->name('MATERIAL_PLANNING_RECALCULATION_BATCH')
            ->dispatch();
    }
}
