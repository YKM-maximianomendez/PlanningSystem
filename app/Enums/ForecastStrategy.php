<?php

namespace App\Enums;

enum ForecastStrategy: string
{
    case MAX = 'MAX';
    case MIX = 'MIX';
    case SUM = 'SUM';

    public function label(): string
    {
        return match ($this) {
            self::MAX => 'L/R FINALES DIFERENTES',
            self::MIX => 'MAX + C.OVER',
            self::SUM => 'SINGLE -- L/R MISMO FINAL',
        };
    }
}
