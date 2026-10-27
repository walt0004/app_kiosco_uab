<?php

namespace App\Enums;

enum SaleStatus: string
{
    case Completada = 'completada';
    case Anulada = 'anulada';

    public function label(): string
    {
        return match ($this) {
            self::Completada => 'Completada',
            self::Anulada => 'Anulada',
        };
    }
}
