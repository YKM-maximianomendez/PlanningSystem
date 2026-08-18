<?php

namespace App\DTO\Configuration;

use Illuminate\Contracts\Support\Arrayable;

final readonly class Adjustment implements Arrayable
{
    public function __construct(
        public int $adjustmentId,
        public string $adjustmentCode,
        public float $adjustmentValue
    ) {}

    public function toArray(): array
    {
        return [
            'adjustmentId' => $this->adjustmentId,
            'adjustmentCode' => $this->adjustmentCode,
            'adjustmentValue' => $this->adjustmentValue,
        ];
    }

    /**
     * Resolve net adjustment from an array of adjustments.
     *
     * @param  Adjustment[]  $adjustments
     */
    public static function resolveNetAdjustment(array $adjustments): float
    {
        return (float) array_sum(
            array_map(fn (Adjustment $adjustment) => $adjustment->adjustmentValue, $adjustments)
        );
    }
}
