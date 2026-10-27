<?php

namespace App\Support;

/**
 * Da formato a una celda de reporte según el tipo de columna.
 */
class ReportCell
{
    public static function format(string $type, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($type) {
            'money' => Format::money($value),
            'qty' => Format::qty($value),
            'int' => number_format((int) $value, 0, ',', '.'),
            'percent' => Format::percent($value),
            default => (string) $value,
        };
    }

    /** Valor numérico para ordenar la columna en DataTables ('' si no es número). */
    public static function sortValue(string $type, mixed $value): string
    {
        return in_array($type, ['money', 'qty', 'int', 'percent'], true) && is_numeric($value)
            ? (string) $value
            : (is_string($value) ? mb_strtolower($value) : '');
    }

    public static function isNumeric(string $type): bool
    {
        return in_array($type, ['money', 'qty', 'int', 'percent'], true);
    }
}
