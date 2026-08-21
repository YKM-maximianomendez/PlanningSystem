<?php

namespace App\Services\MRP;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

class DeliveryInstructionService
{
    private Connection $connection;

    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        $this->connection = DB::connection('as400');
    }

    public function getDeliveryInstructions(string $vendorCode): array
    {
        $query = <<< 'SQL'
            SELECT * FROM LX834F01.AVM
            WHERE VENDOR = :vendorCode
            WITH UR
        SQL;

        return [];
    }
}
