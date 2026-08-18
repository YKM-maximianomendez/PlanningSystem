<?php

namespace App\Enums;

enum ShopCalendar: string
{
    case YKM0 = '000000';
    case MMVO = '200000';

    public function label(): string
    {
        return match ($this) {
            self::YKM0 => 'YKM Calendar',
            self::MMVO => 'MMMDM Calendar',
        };
    }

    public static function toSelectArray(): array
    {
        return array_reduce(self::cases(), function ($carry, $case) {
            $carry[(string) $case->value] = $case->label();

            return $carry;
        }, []);
    }
}
