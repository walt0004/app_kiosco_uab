@php use App\Support\ReportCell; @endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        @page { margin: 28px 30px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; }
        h1 { font-size: 17px; margin: 0; }
        h2 { font-size: 13px; margin: 2px 0 0; font-weight: normal; color: #334155; }
        .muted { color: #64748b; }
        .head { border-bottom: 2px solid #1f3a9c; padding-bottom: 8px; margin-bottom: 10px; }
        table.cards { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 12px; }
        table.cards td { border: 1px solid #cbd5e1; padding: 6px 8px; }
        .card-label { font-size: 8px; color: #64748b; }
        .card-value { font-size: 13px; font-weight: bold; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #1f3a9c; color: #ffffff; padding: 5px 6px; text-align: left; font-size: 9px; }
        table.data td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; }
        table.data tr.total td { font-weight: bold; border-top: 2px solid #1e293b; border-bottom: 0; background: #f1f5f9; }
        .r { text-align: right; }
        .empty { padding: 30px 0; text-align: center; color: #64748b; }
        .footer { margin-top: 16px; font-size: 8px; color: #64748b; }
    </style>
</head>
<body>
    <div class="head">
        <h1>{{ config('app.name') }}</h1>
        <h2>{{ $report['title'] }}</h2>
        @if ($report['subtitle'])
            <div class="muted">{{ $report['subtitle'] }}</div>
        @endif
    </div>

    @if ($report['cards'])
        <table class="cards">
            <tr>
                @foreach ($report['cards'] as $card)
                    <td>
                        <div class="card-label">{{ $card['label'] }}</div>
                        <div class="card-value">{{ $card['value'] }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    @if (count($report['rows']) === 0)
        <p class="empty">No hay datos para los filtros elegidos.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    @foreach ($report['columns'] as $column)
                        <th class="{{ ReportCell::isNumeric($column['type']) ? 'r' : '' }}">{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($report['rows'] as $row)
                    <tr>
                        @foreach ($report['columns'] as $column)
                            <td class="{{ ReportCell::isNumeric($column['type']) ? 'r' : '' }}">{{ ReportCell::format($column['type'], $row[$column['key']] ?? null) }}</td>
                        @endforeach
                    </tr>
                @endforeach
                @if ($report['totals'])
                    <tr class="total">
                        @foreach ($report['columns'] as $column)
                            <td class="{{ ReportCell::isNumeric($column['type']) ? 'r' : '' }}">{{ array_key_exists($column['key'], $report['totals']) ? ReportCell::format($column['type'], $report['totals'][$column['key']]) : '' }}</td>
                        @endforeach
                    </tr>
                @endif
            </tbody>
        </table>
    @endif

    <p class="footer">Generado el {{ now()->format('d/m/Y H:i') }} por {{ $generatedBy }}. Las ventas anuladas no están incluidas. Documento interno.</p>
</body>
</html>
