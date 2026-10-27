@php
    use App\Enums\PaymentMethod;
    use App\Support\Format;
@endphp
<x-layouts.app :title="'Turno de caja #'.$session->id">
    <x-slot:actions>
        <a href="{{ route('cash.history') }}" class="btn-secondary">Volver</a>
        @if (! $session->isOpen())
            <a href="{{ route('cash.pdf', $session) }}" class="btn-secondary">Descargar PDF</a>
        @else
            <a href="{{ route('cash.index') }}" class="btn-primary">Ir a caja</a>
        @endif
    </x-slot:actions>

    <section class="card">
        <div class="flex flex-wrap items-center gap-3">
            @if ($session->isOpen())
                <span class="badge bg-emerald-100 text-emerald-800">Abierta</span>
            @else
                <span class="badge bg-slate-200 text-slate-700">Cerrada</span>
            @endif
            <span class="text-sm text-slate-600">Responsable: <span class="font-semibold text-slate-900">{{ $session->user->name }}</span></span>
        </div>

        <dl class="mt-5 grid gap-x-8 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-slate-500">Apertura</dt>
                <dd class="font-medium text-slate-900">{{ $session->opened_at->format('d/m/Y H:i') }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Cierre</dt>
                <dd class="font-medium text-slate-900">
                    {{ $session->closed_at?->format('d/m/Y H:i') ?? 'Sigue abierta' }}
                    @if ($session->closer && $session->closer->id !== $session->user_id)
                        <span class="block text-xs font-normal text-slate-500">por {{ $session->closer->name }}</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-slate-500">Efectivo inicial</dt>
                <dd class="font-medium tabular-nums text-slate-900">{{ Format::money($session->opening_amount) }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Ventas (sin anuladas)</dt>
                <dd class="font-medium tabular-nums text-slate-900">{{ $summary['count'] }} · {{ Format::money($summary['total']) }}</dd>
            </div>
        </dl>
    </section>

    <div class="mt-5 grid gap-5 lg:grid-cols-2">
        <section class="card">
            <h2 class="text-base font-bold text-slate-900">Cobros por método de pago</h2>
            <table class="mt-3 w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @foreach (PaymentMethod::cases() as $method)
                        <tr>
                            <td class="py-2 text-slate-600">{{ $method->label() }}</td>
                            <td class="py-2 text-right font-medium tabular-nums">{{ Format::money($summary['by_method'][$method->value]) }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td class="py-2 font-semibold text-slate-900">Total</td>
                        <td class="py-2 text-right font-bold tabular-nums">{{ Format::money($summary['total']) }}</td>
                    </tr>
                </tbody>
            </table>
            @if ($summary['voided_count'] > 0)
                <p class="mt-3 text-xs text-slate-500">Ventas anuladas (no incluidas): {{ $summary['voided_count'] }} por {{ Format::money($summary['voided_total']) }}.</p>
            @endif
        </section>

        <section class="card">
            <h2 class="text-base font-bold text-slate-900">Cuadre de efectivo</h2>
            <table class="mt-3 w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    <tr>
                        <td class="py-2 text-slate-600">Efectivo inicial</td>
                        <td class="py-2 text-right tabular-nums">{{ Format::money($session->opening_amount) }}</td>
                    </tr>
                    <tr>
                        <td class="py-2 text-slate-600">+ Ventas en efectivo</td>
                        <td class="py-2 text-right tabular-nums">{{ Format::money($summary['by_method']['efectivo']) }}</td>
                    </tr>
                    <tr>
                        <td class="py-2 font-semibold text-slate-900">Efectivo esperado</td>
                        <td class="py-2 text-right font-bold tabular-nums">{{ Format::money($session->isOpen() ? $summary['expected_cash'] : $session->expected_cash) }}</td>
                    </tr>
                    @unless ($session->isOpen())
                        <tr>
                            <td class="py-2 text-slate-600">Efectivo contado</td>
                            <td class="py-2 text-right tabular-nums">{{ Format::money($session->counted_cash) }}</td>
                        </tr>
                        <tr>
                            <td class="py-2 font-semibold text-slate-900">Diferencia</td>
                            <td class="py-2 text-right">@include('cash._difference', ['difference' => (float) $session->difference])</td>
                        </tr>
                    @endunless
                </tbody>
            </table>
            @if ($session->notes)
                <p class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700"><span class="font-medium">Notas:</span> {{ $session->notes }}</p>
            @endif
        </section>
    </div>

    <section class="card mt-5">
        <h2 class="mb-3 text-base font-bold text-slate-900">Ventas de este turno</h2>
        <x-datatable
            :url="route('sales.data').'?session='.$session->id"
            :order="[[0, 'desc']]"
            placeholder="Número o vendedor"
            :columns="[
                ['data' => 'number', 'title' => 'N°', 'orderable' => true],
                ['data' => 'created_at', 'title' => 'Fecha', 'orderable' => true],
                ['data' => 'seller', 'title' => 'Vendedor', 'orderable' => true],
                ['data' => 'method', 'title' => 'Pago', 'orderable' => true],
                ['data' => 'total', 'title' => 'Total', 'orderable' => true, 'className' => 'dt-right'],
                ['data' => 'status', 'title' => 'Estado', 'orderable' => true],
                ['data' => 'actions', 'title' => '', 'className' => 'dt-right'],
            ]" />
    </section>
</x-layouts.app>
