<?php

namespace App\DTO\Configuration;

use Illuminate\Contracts\Support\Arrayable;

final readonly class Completion implements Arrayable
{
    public function __construct(
        public string $product,
        public string $childProduct,
        public bool $isObsolete
    ) {}

    public function toArray(): array
    {
        return [
            'product' => $this->product,
            'childProduct' => $this->childProduct,
            'isObsolete' => $this->isObsolete,
        ];
    }

    public function isActive(): bool
    {
        return ! $this->isObsolete;
    }
}
