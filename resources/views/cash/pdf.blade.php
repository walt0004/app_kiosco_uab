@php
    use App\Enums\PaymentMethod;
    use App\Support\Format;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cierre de caja #{{ $session->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        h2 { font-size: 13px; margin: 18px 0 6px; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; }
        .muted { color: #64748b; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 4px 0; border-bottom: 1px solid #e2e8f0; }
        td.r { text-align: right; }
        .strong { font-weight: bold; }
        .box { border: 1px solid #cbd5e1; padding: 8px 10px; margin-top: 10px; }
        .footer { margin-top: 30px; font-size: 9px; color: #64748b; }
        .sign { margin-top: 50px; }
        .sign td { border: 0; text-align: center; padding-top: 30px; }
        .line { border-top: 1px solid #1e293b; padding-top: 3px; }
    </style>
</head>
<body>
    <h1>{{ config('app.name') }}</h1>
    <p class="muted">Cierre de caja · Turno #{{ $session->id }}</p>

    <table>
        <tr><td class="muted">Responsable</td><td class="r strong">{{ $session->user->name }}</td></tr>
        <tr><td class="muted">Apertura</td><td class="r">{{ $session->opened_at->format('d/m/Y H:i') }}</td></tr>
        <tr><td class="muted">Cierre</td><td class="r">{{ $session->closed_at?->format('d/m/Y H:i') ?? 'Turno abierto' }}</td></tr>
        @if ($session->closer && $session->closer->id !== $session->user_id)
            <tr><td class="muted">Cerrado por</td><td class="r">{{ $session->closer->name }}</td></tr>
        @endif
    </table>

    <h2>Ventas del turno</h2>
    <table>
        <tr><td>Cantidad de ventas (sin anuladas)</td><td class="r">{{ $summary['count'] }}</td></tr>
        @foreach (PaymentMethod::cases() as $method)
            <tr><td>{{ $method->label() }}</td><td class="r">{{ Format::money($summary['by_method'][$method->value]) }}</td></tr>
        @endforeach
        <tr><td class="strong">Total vendido</td><td class="r strong">{{ Format::money($summary['total']) }}</td></tr>
        @if ($summary['voided_count'] > 0)
            <tr><td class="muted">Ventas anuladas (no incluidas)</td><td class="r muted">{{ $summary['voided_count'] }} · {{ Format::money($summary['voided_total']) }}</td></tr>
        @endif
    </table>

    <h2>Cuadre de efectivo</h2>
    <table>
        <tr><td>Efectivo inicial</td><td class="r">{{ Format::money($session->opening_amount) }}</td></tr>
        <tr><td>+ Ventas en efectivo</td><td class="r">{{ Format::money($summary['by_method']['efectivo']) }}</td></tr>
        <tr><td class="strong">Efectivo esperado</td><td class="r strong">{{ Format::money($session->expected_cash ?? $summary['expected_cash']) }}</td></tr>
        @if ($session->counted_cash !== null)
            <tr><td>Efectivo contado</td><td class="r">{{ Format::money($session->counted_cash) }}</td></tr>
            <tr>
                <td class="strong">Diferencia</td>
                <td class="r strong">
                    @if ((float) $session->difference == 0)
                        Cuadra
                    @elseif ((float) $session->difference > 0)
                        Sobran {{ Format::money($session->difference) }}
                    @else
                        Faltan {{ Format::money(abs((float) $session->difference)) }}
                    @endif
                </td>
            </tr>
        @endif
    </table>

    @if ($session->notes)
        <div class="box"><span class="strong">Notas:</span> {{ $session->notes }}</div>
    @endif

    <table class="sign">
        <tr>
            <td><div class="line">Responsable de caja</div></td>
            <td><div class="line">Administración</div></td>
        </tr>
    </table>

    <p class="footer">Generado el {{ now()->format('d/m/Y H:i') }} por {{ $generatedBy }}. Documento interno, no es factura.</p>
</body>
</html>
