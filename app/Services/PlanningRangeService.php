<?php

namespace App\Services;

use App\ValueObjects\PlanningRange;
use Carbon\Carbon;
use Carbon\Constants\UnitValue;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use Exception;

readonly class PlanningRangeService
{
    private const PLANNING_DAYS = 27;

    /**
     * Generate the planning range for MRP
     */
    public function getPlanningRange(): PlanningRange
    {
        $start = $this->getPlanningStartSunday()->toDateTimeImmutable();

        $end = $start->modify('+'.self::PLANNING_DAYS.' days');

        return new PlanningRange(
            start: $start,
            end: $end,
            today: new DateTimeImmutable
        );
    }

    /**
     * @return array<string>
     *
     * @throws Exception
     */
    public static function generateDaysDateRange(string $startDate, string $endDate): array
    {
        $dates = [];

        $start = new DateTime($startDate);
        $end = new DateTime($endDate);

        while ($start <= $end) {
            $dates[] = $start->format('Ymd');
            $start->add(new DateInterval('P1D'));
        }

        return $dates;
    }

    /**
     * Get planning start day
     */
    private function getPlanningStartSunday(): Carbon
    {
        return Carbon::now()->previous(UnitValue::SUNDAY)
            ->startOfDay();
    }
}
