<?php

namespace App\Enums;

enum Role: string
{
    case Administrador = 'administrador';
    case Vendedor = 'vendedor';

    public function label(): string
    {
        return match ($this) {
            self::Administrador => 'Administrador',
            self::Vendedor => 'Vendedor',
        };
    }
}
