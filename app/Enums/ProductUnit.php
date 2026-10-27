<?php

namespace App\Enums;

/**
 * Unidades en las que se vende un producto.
 * Las unidades "por peso" o "por volumen" aceptan cantidades decimales
 * (por ejemplo 0.250 kg); las demás solo cantidades enteras.
 */
enum ProductUnit: string
{
    case Unidad = 'unidad';
    case Paquete = 'paquete';
    case Caja = 'caja';
    case Docena = 'docena';
    case Kilogramo = 'kg';
    case Litro = 'litro';

    public function label(): string
    {
        return match ($this) {
            self::Unidad => 'Unidad',
            self::Paquete => 'Paquete',
            self::Caja => 'Caja',
            self::Docena => 'Docena',
            self::Kilogramo => 'Kilogramo (kg)',
            self::Litro => 'Litro',
        };
    }

    public function short(): string
    {
        return match ($this) {
            self::Unidad => 'und',
            self::Paquete => 'paq',
            self::Caja => 'caja',
            self::Docena => 'doc',
            self::Kilogramo => 'kg',
            self::Litro => 'L',
        };
    }

    public function allowsDecimals(): bool
    {
        return in_array($this, [self::Kilogramo, self::Litro], true);
    }
}
