<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Inicial = 'inicial';
    case Entrada = 'entrada';
    case Salida = 'salida';
    case Correccion = 'correccion';
    case Venta = 'venta';
    case Anulacion = 'anulacion';

    public function label(): string
    {
        return match ($this) {
            self::Inicial => 'Stock inicial',
            self::Entrada => 'Entrada (reposición)',
            self::Salida => 'Salida (merma/pérdida)',
            self::Correccion => 'Corrección de inventario',
            self::Venta => 'Venta',
            self::Anulacion => 'Devolución por anulación',
        };
    }
}
