<?php

namespace App\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CalendarService
{
    public function __construct() {}

    public function getCalendar(
        string $year,
        ?string $workcenter = null
    ): array {
        $key = "calendar:{$year}:".($workcenter ?? 0);

        return Cache::remember($key, now()->addHours(12), function () use ($year, $workcenter) {
            $calendarString = DB::connection('as400')->scalar('SELECT SCTYPE FROM LX834F01.FSC WHERE SCYEAR = ? AND SCRKC = ?',
                [$year, empty($workcenter) ? 0 : $workcenter]
            );

            if ($calendarString === false) {
                return [];
            }

            return str_split((string) $calendarString);
        });
    }

    /**
     * Get calendar fragment from INFORLX based on start and end Julian dates
     */
    public function getCalendarFragment(
        string $year,
        int $startJulian,
        int $endJulian,
        ?string $workcenter = null
    ): array {

        if ($endJulian < $startJulian) {
            throw new InvalidArgumentException('End date must be greater than or equal to start date.');
        }

        $calendar = $this->getCalendar($year, $workcenter);

        if ($calendar === []) {
            return [];
        }

        $fragment = array_slice($calendar, $startJulian - 1, ($endJulian - $startJulian) + 1);
        $isDefaultCalendar = empty($workcenter);
        $result = [];

        foreach ($fragment as $index => $char) {
            $julianDay = $startJulian + $index;

            $date = (new DateTimeImmutable)
                ->setDate((int) $year, 1, 1)
                ->modify('+'.($julianDay - 1).' days');

            $value = match (trim($char)) {
                '' => 1.0,
                'A' => 0.5,
                default => 0.0,
            };

            if ($isDefaultCalendar && (int) $date->format('N') >= 6) {
                $value = 0.0;
            }

            $result[$date->format('Ymd')] = $value;
        }

        return $result;
    }
}
