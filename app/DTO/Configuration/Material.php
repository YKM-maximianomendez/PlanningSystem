<?php

namespace App\DTO\Configuration;

use Illuminate\Contracts\Support\Arrayable;

final class Material implements Arrayable
{
    public function __construct(
        public int $materialId,
        public string $materialCode,
        public int $classId,
        public string $classCode,
        public string $classDescription,
        public Vendor $vendor,
        public string $um,
        public bool $isObsolete,
        public array $options = [],
    ) {}

    public function toArray(): array
    {
        return [
            'materialId' => $this->materialId,
            'materialCode' => $this->materialCode,
            'classId' => $this->classId,
            'classCode' => $this->classCode,
            'classDescription' => $this->classDescription,
            'vendor' => $this->vendor->toArray(),
            'um' => $this->um,
            'isObsolete' => $this->isObsolete,
            'options' => $this->options,
        ];
    }

    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }
}
