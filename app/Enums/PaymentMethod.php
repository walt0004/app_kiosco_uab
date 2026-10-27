<?php

namespace App\Enums;

/**
 * Métodos de pago. Solo se registran; el sistema no se conecta a pasarelas.
 */
enum PaymentMethod: string
{
    case Efectivo = 'efectivo';
    case Qr = 'qr';
    case Transferencia = 'transferencia';

    public function label(): string
    {
        return match ($this) {
            self::Efectivo => 'Efectivo',
            self::Qr => 'QR',
            self::Transferencia => 'Transferencia',
        };
    }
}
