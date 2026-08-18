<?php

namespace App\Services\MRP;

use App\DTO\Concept;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use PDO;

class SnapshotService
{
    public function __construct() {}

    public function syncSnapshot(int $productionPlanningId, array $concepts, DateTimeImmutable $start, DateTimeImmutable $end): void
    {
        $data = Concept::toTVP($concepts, $productionPlanningId);
        $tvpInput = ['snapshotUDT' => $data];
        $query = 'EXEC dbo.snapshotSync ?, ?, ?, ?';
        $startStr = $start->format('Ymd');
        $endStr = $end->format('Ymd');

        $statement = DB::connection('mrp')->getPdo()->prepare($query);
        $statement->bindParam(1, $productionPlanningId, PDO::PARAM_INT);
        $statement->bindParam(2, $startStr, PDO::PARAM_STR);
        $statement->bindParam(3, $endStr, PDO::PARAM_STR);
        $statement->bindParam(4, $tvpInput, PDO::PARAM_LOB);
        $rc = $statement->execute();
    }
}
