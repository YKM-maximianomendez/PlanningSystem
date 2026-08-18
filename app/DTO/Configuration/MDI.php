<?php

namespace App\DTO\Configuration;

use Illuminate\Contracts\Support\Arrayable;

class MDI implements Arrayable
{
    public function __construct(
        public int $mdiId,
        public string $mdiCode,
    ) {}

    public function toArray(): array
    {
        return [
            'mdiId' => $this->mdiId,
            'mdiCode' => $this->mdiCode,
        ];
    }
}
