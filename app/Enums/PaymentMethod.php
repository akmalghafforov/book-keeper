<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Ds = 'ds';
    case Eo = 'eo';
    case Alif = 'alif';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'cash',
            self::Ds => 'ДС',
            self::Eo => 'ЭО',
            self::Alif => 'Алиф',
        };
    }
}
