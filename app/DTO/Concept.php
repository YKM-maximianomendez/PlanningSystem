<?php

namespace App\DTO;

final readonly class Concept
{
    public function __construct(
        public string $conceptCode,
        public array $data,
        public string $um,
        public ?string $metadata = null,
    ) {}

    public static function toConceptArray(array $concepts, int $productionPlanningId): array
    {
        $result = [];
        foreach ($concepts as $concept) {
            foreach ($concept->data as $date => $value) {
                $result[] = [
                    'production_planning_id' => $productionPlanningId,
                    'concept' => $concept->conceptCode,
                    'snapshot_date' => $date,
                    'snapshot_quantity' => (float) $value,
                    'unit' => $concept->um,
                ];
            }
        }

        return $result;
    }

    public static function toKeyValue(array $concepts, int $productionPlanningId): array
    {
        return collect(self::toConceptArray($concepts, $productionPlanningId))
            ->groupBy('concept')
            ->map(function ($items) {
                return $items->mapWithKeys(function ($item) {
                    return [
                        $item['snapshot_date'] => (float) $item['snapshot_quantity'],
                    ];
                });
            })
            ->toArray();
    }

    public static function toTVP(array $data, int $productionPlanningId): array
    {
        $data = self::toConceptArray($data, $productionPlanningId);
        foreach ($data as &$row) {
            $row = array_values($row);
        }

        return $data;
    }
}
