<?php

namespace App\Services\MRP;

use Exception;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use PDOException;

final readonly class YPU008C
{
    public function __construct()
    {
        ini_set('max_execution_time', 600);
    }

    /**
     * @throws Exception
     */
    public function execute(?string $workcenter = null): void
    {
        $workcenter = $workcenter !== null ? trim($workcenter) : null;
        $workcenter = $workcenter === '' ? null : $workcenter;

        $as400 = DB::connection('as400');

        $count = $this->Y160($as400, $workcenter);

        if ($count === 0) {
            throw new Exception('No pending orders found to process for this workcenter.');
        }

        if ($this->Y162($as400) > 0) {
            // / DELETE FROM LX834FU02.YH0162
            // $as400->delete('DELETE FROM LX834FU02.YH0162');
        }

        $command = $workcenter === null
            ? 'CALL PGM(LX834OU02/YPU008C)'
            : sprintf(
                'CALL PGM(LX834OU02/YPU008C1) PARM((%d (*DEC 6 0)))',
                (int) $workcenter
            );

        $sql = "CALL QSYS2.QCMDEXC('$command')";

        try {
            $statement = DB::connection('as400')->getPdo()->prepare($sql);
            $statement->execute();

            if ($statement->errorCode() !== '00000') {
                $errorInfo = $statement->errorInfo();
                throw new PDOException(
                    "Stored procedure execution failed: {$errorInfo[2]}",
                    (int) $errorInfo[0]
                );

            }
        } catch (PDOException $e) {
            throw new PDOException(
                "Failed to execute purchase order program '{$command}': {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    private function Y160(Connection $connection, string $workcenter): int
    {
        $sql = <<< 'SQL'
            SELECT COUNT(*)
            FROM LX834FU02.YH016
            WHERE Y6WC = ?
        SQL;

        $result = $connection->scalar($sql, [$workcenter]);

        return (int) $result;
    }

    private function Y162(Connection $connection): int
    {
        $sql = <<< 'SQL'
            SELECT COUNT(*)
            FROM LX834FU02.YH0162
        SQL;

        $result = $connection->scalar($sql);

        return (int) $result;
    }
}
