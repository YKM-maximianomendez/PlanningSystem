<?php

namespace App\ValueObjects;

use Carbon\Carbon;
use Carbon\CarbonInterface as UnitValue;
use DateTimeImmutable;
use Illuminate\Contracts\Support\Arrayable;
use RuntimeException;

final readonly class PlanningRange implements Arrayable
{
    public function __construct(
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
        public DateTimeImmutable $today,
    ) {}

    /**
     * Get the planning window
     *
     * @return array{start: DateTimeImmutable, end: DateTimeImmutable}
     */
    public function getWindow(): array
    {
        $startDate = Carbon::instance($this->start);
        $endDate = Carbon::instance($this->end);

        if ($startDate->diffInWeeks($endDate) < 1) {
            throw new RuntimeException('Range must cover at least two weeks to calculate the window.');
        }

        $tuesday = $endDate->copy()
            ->subWeek()
            ->startOfWeek(UnitValue::MONDAY)
            ->next(UnitValue::TUESDAY);

        $wednesday = $endDate->copy()
            ->startOfWeek(UnitValue::MONDAY)
            ->next(UnitValue::WEDNESDAY);

        return [
            'start' => $tuesday->toDateTimeImmutable(),
            'end' => $wednesday->toDateTimeImmutable(),
        ];
    }

    public function generateDaysDateRange(): array
    {
        $dates = [];

        $current = Carbon::instance($this->start);
        $end = Carbon::instance($this->end);

        while ($current <= $end) {
            $dates[] = $current->format('Ymd');
            $current->addDay();
        }

        return $dates;
    }

    /**
     * Return the start date in format 'YYYYMMDD'
     */
    public function getStart(): string
    {
        return $this->start->format('Ymd');
    }

    public function getEnd(): string
    {
        return $this->end->format('Ymd');
    }

    /**
     * Get current date in format 'YYYYMMDD'
     */
    public function getToday(): string
    {
        return $this->today->format('Ymd');
    }

    public function getStartJulianDay(): int
    {
        return Carbon::instance($this->start)
            ->dayOfYear;
    }

    public function getEndJulianDay(): int
    {
        return Carbon::instance($this->end)
            ->dayOfYear;
    }

    public function toArray(): array
    {
        return [
            'start' => $this->getStart(),
            'end' => $this->getEnd(),
            'today' => $this->getToday(),
            'startJulianDay' => $this->getStartJulianDay(),
            'endJulianDay' => $this->getEndJulianDay(),
            'window' => [
                'start' => $this->getWindow()['start']->format('Ymd'),
                'end' => $this->getWindow()['end']->format('Ymd'),
            ],
        ];
    }
}
