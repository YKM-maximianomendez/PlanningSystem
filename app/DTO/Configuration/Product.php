<?php

namespace App\DTO\Configuration;

use Illuminate\Contracts\Support\Arrayable;

final class Product implements Arrayable
{
    public function __construct(
        public readonly int $level,
        public readonly int $productId,
        public readonly string $productCode,
        public readonly int $mdiId,
        public readonly string $mdiCode,
        public readonly float $quantityRequired,
        public readonly bool $isObsolete,
        public readonly CycleCount $lastCycleCount,
        public readonly array $cycleCountAdjustments = [],
    ) {}

    public function toArray(): array
    {
        return [
            'level' => $this->level,
            'productId' => $this->productId,
            'productCode' => $this->productCode,
            'mdiId' => $this->mdiId,
            'mdiCode' => $this->mdiCode,
            'quantityRequired' => $this->quantityRequired,
            'isObsolete' => $this->isObsolete,
            'lastCycleCount' => $this->lastCycleCount?->toArray() ?? null,
            'cycleCountAdjustments' => $this->cycleCountAdjustments,
        ];
    }

    public function isActive(): bool
    {
        return ! $this->isObsolete;
    }
}
