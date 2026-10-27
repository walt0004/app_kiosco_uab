@php
    use App\Enums\PaymentMethod;
    use App\Support\Format;
@endphp
<x-layouts.app title="Caja">
    <x-slot:actions>
        <a href="{{ route('cash.history') }}" class="btn-secondary">Historial de turnos</a>
    </x-slot:actions>

    @if (! $session)
        <div class="grid gap-5 lg:grid-cols-5">
            <section class="card lg:col-span-3">
                <h2 class="text-base font-bold text-slate-900">Abrir caja</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Tu caja está cerrada. Cuenta el efectivo con el que empiezas el turno (el cambio inicial) y regístralo para poder vender.
                </p>

                <form method="POST" action="{{ route('cash.open') }}" class="mt-5 max-w-sm space-y-5" novalidate>
                    @csrf
                    <x-field name="opening_amount" label="Efectivo inicial (Bs)" :required="true" hint="Si empiezas sin efectivo, escribe 0.">
                        <input id="opening_amount" name="opening_amount" type="number" step="0.01" min="0" inputmode="decimal" required
                               value="{{ old('opening_amount', '0') }}" class="input @error('opening_amount') input-error @enderror" autofocus>
                    </x-field>
                    <button type="submit" class="btn-primary">Abrir caja</button>
                </form>
            </section>

            <section class="card lg:col-span-2">
                <h2 class="text-base font-bold text-slate-900">¿Cómo funciona?</h2>
                <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-slate-600">
                    <li>Abres tu caja con el efectivo inicial.</li>
                    <li>Registras tus ventas durante el turno.</li>
                    <li>Al terminar, cuentas el efectivo y cierras la caja: el sistema compara lo contado con lo esperado.</li>
                </ol>
            </section>
        </div>
    @else
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-emerald-50 px-5 py-4 ring-1 ring-emerald-200">
            <div>
                <p class="font-semibold text-emerald-900">Caja abierta</p>
                <p class="text-sm text-emerald-800">Desde el {{ $session->opened_at->format('d/m/Y H:i') }} · Efectivo inicial {{ Format::money($session->opening_amount) }}</p>
            </div>
            <a href="{{ route('sales.create') }}" class="btn-primary">Ir a vender</a>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="card">
                <p class="text-sm text-slate-500">Ventas del turno</p>
                <p class="mt-1 text-3xl font-bold tabular-nums text-slate-900">{{ $summary['count'] }}</p>
                @if ($summary['voided_count'] > 0)
                    <p class="mt-1 text-xs text-slate-500">{{ $summary['voided_count'] }} anulada(s) no se cuentan</p>
                @endif
            </div>
            <div class="card">
                <p class="text-sm text-slate-500">Total vendido</p>
                <p class="mt-1 text-3xl font-bold tabular-nums text-slate-900">{{ Format::money($summary['total']) }}</p>
            </div>
            @foreach (PaymentMethod::cases() as $method)
                @if ($method !== PaymentMethod::Transferencia)
                    <div class="card">
                        <p class="text-sm text-slate-500">En {{ mb_strtolower($method->label()) }}</p>
                        <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ Format::money($summary['by_method'][$method->value]) }}</p>
                    </div>
                @endif
            @endforeach
        </div>
        @if ($summary['by_method']['transferencia'] > 0)
            <p class="mt-3 text-sm text-slate-600">También hay {{ Format::money($summary['by_method']['transferencia']) }} cobrados por transferencia.</p>
        @endif

        <section class="card mt-5 max-w-2xl">
            <h2 class="text-base font-bold text-slate-900">Cerrar caja</h2>
            <p class="mt-1 text-sm text-slate-600">
                Efectivo que debería haber ahora (inicial + ventas en efectivo):
                <span class="font-bold tabular-nums text-slate-900">{{ Format::money($summary['expected_cash']) }}</span>.
                Cuenta el efectivo real e ingrésalo abajo.
            </p>

            <form method="POST" action="{{ route('cash.close', $session) }}" class="mt-5 space-y-5" novalidate
                  onsubmit="return confirm('¿Cerrar la caja? Después de cerrarla no podrás registrar más ventas en este turno.')">
                @csrf
                <x-field name="counted_cash" label="Efectivo contado (Bs)" :required="true">
                    <input id="counted_cash" name="counted_cash" type="number" step="0.01" min="0" inputmode="decimal" required
                           value="{{ old('counted_cash') }}" class="input max-w-xs @error('counted_cash') input-error @enderror">
                </x-field>
                <x-field name="notes" label="Notas" hint="Obligatorias si el efectivo contado no coincide con el esperado.">
                    <textarea id="notes" name="notes" rows="2" maxlength="500" class="input @error('notes') input-error @enderror">{{ old('notes') }}</textarea>
                </x-field>
                <button type="submit" class="btn-danger">Cerrar caja</button>
            </form>
        </section>
    @endif
</x-layouts.app>
