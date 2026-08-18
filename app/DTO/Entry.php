<?php

namespace App\DTO;

final readonly class Entry
{
    public function __construct(
        public string $date,
        public int $quantity,
    ) {}

    /**
     * Undocumented function
     *
     * @param  Entry[]  $entries
     */
    public static function groupEntries(array $entries, array $planningDays): array
    {
        $result = array_fill_keys(array_keys($planningDays), 0);

        foreach ($entries as $item) {
            if (isset($result[$item->date])) {
                $result[$item->date] += $item->quantity;
            }
        }

        return $result;
    }
}
