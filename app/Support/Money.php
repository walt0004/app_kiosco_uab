<?php

namespace App\Support;

/**
 * Cálculos de dinero con enteros (centavos) para evitar errores de redondeo
 * de los números decimales.
 */
class Money
{
    public static function toCents(float|int|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function fromCents(int $cents): float
    {
        return round($cents / 100, 2);
    }

    /**
     * Importe de una línea (precio × cantidad) en centavos, redondeado hacia arriba
     * en la mitad. La cantidad admite hasta 3 decimales (0,250 kg).
     */
    public static function lineCents(float|int|string $price, float|int|string $quantity): int
    {
        $milli = (int) round(((float) $quantity) * 1000);

        return intdiv(self::toCents($price) * $milli + 500, 1000);
    }
}
