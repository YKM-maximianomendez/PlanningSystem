<?php

namespace App\DTO\Configuration;

use Illuminate\Contracts\Support\Arrayable;

class Customer implements Arrayable
{
    public function __construct(
        public int $customerId,
        public string $customerCode,
    ) {}

    public function isMMVO(): bool
    {
        return $this->customerCode === '200000';
    }

    public function toArray(): array
    {
        return [
            'customerId' => $this->customerId,
            'customerCode' => $this->customerCode,
        ];
    }
}
