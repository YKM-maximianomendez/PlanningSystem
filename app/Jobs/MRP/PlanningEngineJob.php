<?php

namespace App\Jobs\MRP;

use App\DTO\Concept;
use App\DTO\Configuration;
use App\UseCases\MRP\RunEngineUseCase;
use App\ValueObjects\PlanningRange;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;

class PlanningEngineJob implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $productionPlanningId,
        public Configuration $configuration,
        public array $calendar,
        public PlanningRange $planningRange,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $conn = DB::connection('as400');

        try {
            $output = app(RunEngineUseCase::class)->execute(
                connection: $conn,
                configuration: $this->configuration,
                calendar: $this->calendar,
                planningRange: $this->planningRange,
            );

            $concepts = Concept::toTVP($output['concepts'] ?? [], $this->productionPlanningId);
            $tvpInput = ['snapshotUDT' => $concepts, 'dbo'];
            $statement = DB::connection('mrp')->getPdo()
                ->prepare('EXEC [dbo].[snapshotSync] ?, ?, ?, ?');

            $start = $this->planningRange->getStart();
            $end = $this->planningRange->getEnd();

            $statement->bindParam(1, $this->productionPlanningId, PDO::PARAM_INT);
            $statement->bindParam(2, $start);
            $statement->bindParam(3, $end);
            $statement->bindParam(4, $tvpInput, PDO::PARAM_LOB);
            $statement->execute();
        } catch (\Throwable $th) {
            // throw $th;
            Log::error('Error executing PlanningEngineJob: '.$th->getMessage(), [
                'productionPlanningId' => $this->productionPlanningId,
                'configuration' => $this->configuration,
                'calendar' => $this->calendar,
                'planningRange' => $this->planningRange,
            ]);
        }
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping(
                "planning:{$this->productionPlanningId}"
            ))->expireAfter(3600),
        ];
    }
}
