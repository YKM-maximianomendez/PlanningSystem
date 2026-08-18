<?php

namespace App\Services\MRP;

use App\DTO\Configuration\Completion;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;

class CompletionService
{
    /**
     * Undocumented function
     *
     * @param  string[]  $products
     */
    public function getCompletionProducts(Connection $connection, array $products): array
    {
        $sortedProducts = $products;
        sort($sortedProducts);
        $cacheKey = 'completions:'.md5(implode('|', $sortedProducts));

        return Cache::remember($cacheKey, now()->addHours(2), function () use ($sortedProducts, $connection) {
            $placeholders = implode(', ', array_fill(0, count($sortedProducts), '?'));

            $query = <<< SQL
            SELECT MCFPRO,
                MCCPRO,
                CASE
                    WHEN IIM.IID <> 'IZ'
                            AND TRIM(IIM.IMPLC) <> 'OBSOLETE' THEN
                        0
                    ELSE
                        1
                END AS ISTAT
            FROM LX834FU01.YMCOM YMC
                INNER JOIN LX834F01.IIM IIM
                    ON (YMC.MCFPRO = IIM.IPROD)
            WHERE MCCPRO IN ($placeholders)
                AND MCFCLS = 'F1'
            WITH UR
            SQL;

            $resultset = $connection
                ->select($query, $sortedProducts);

            $grouped = [];

            foreach ($resultset as $row) {
                $grouped[] = new Completion(
                    product: trim($row->MCFPRO),
                    childProduct: trim($row->MCCPRO),
                    isObsolete: filter_var($row->ISTAT, FILTER_VALIDATE_BOOLEAN)
                );
            }

            return $grouped;
        });
    }
}
