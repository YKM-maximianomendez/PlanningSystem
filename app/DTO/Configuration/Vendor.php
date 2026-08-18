<?php

namespace App\DTO\Configuration;

use Illuminate\Contracts\Support\Arrayable;

final readonly class Vendor implements Arrayable
{
    public function __construct(
        public int $vendorId,
        public string $vendorCode,
        public string $vendorDescription,
    ) {}

    public function toArray(): array
    {
        return [
            'vendorId' => $this->vendorId,
            'vendorCode' => $this->vendorCode,
            'vendorDescription' => $this->vendorDescription,
        ];
    }
}
