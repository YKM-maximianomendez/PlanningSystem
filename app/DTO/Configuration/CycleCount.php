<?php

namespace App\DTO\Configuration;

use DateTimeImmutable;

class CycleCount
{
    public function __construct(
        public ?DateTimeImmutable $date,
        public float $quantity,
        public float $consumed,
        public float $theoricalQuantity,
    ) {}

    public function getEffectiveStock(): float
    {
        return $this->consumed;
    }

    public function getAvailableStock(): float
    {
        return $this->quantity;
    }

    public function getTheoricalStock(): float
    {
        return $this->theoricalQuantity;
    }

    public function toArray(): array
    {
        return [
            'date' => $this->date?->format('Ymd'),
            'quantity' => $this->quantity,
            'consumed' => $this->consumed,
            'theoricalQuantity' => $this->theoricalQuantity,
            'diffDays' => $this->date ? (new DateTimeImmutable)->diff($this->date)->days : null,
        ];
    }
}
