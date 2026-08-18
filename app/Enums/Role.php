<?php

namespace App\Enums;

enum Role: string
{
    case ADMIN = 'administrator';
    case STEEL_PLANNER_SPECIALIST = 'steel_planner_specialist';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrator',
            self::STEEL_PLANNER_SPECIALIST => 'Steel Planner Specialist',
        };
    }
}
