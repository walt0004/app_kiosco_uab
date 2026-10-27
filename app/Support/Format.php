<?php

namespace App\Support;

/**
 * Formato de números para la interfaz (Bolivia: coma decimal, punto de millar).
 */
class Format
{
    public static function money(float|int|string|null $amount): string
    {
        return 'Bs '.number_format((float) $amount, 2, ',', '.');
    }

    /**
     * Cantidad sin ceros sobrantes: 12 -> "12", 0.250 -> "0,25", 1.5 -> "1,5".
     */
    public static function qty(float|int|string|null $quantity): string
    {
        $formatted = number_format((float) $quantity, 3, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }

    public static function percent(float|int|string|null $value): string
    {
        return number_format((float) $value, 1, ',', '.').' %';
    }

    private const MONTHS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    private const WEEKDAYS = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];

    /** "Octubre 2026" */
    public static function monthYear(\DateTimeInterface $date): string
    {
        return ucfirst(self::MONTHS[(int) $date->format('n') - 1]).' '.$date->format('Y');
    }

    /** "lun 26/10/2026" */
    public static function dayWithWeekday(\DateTimeInterface $date): string
    {
        return self::WEEKDAYS[(int) $date->format('w')].' '.$date->format('d/m/Y');
    }
}
