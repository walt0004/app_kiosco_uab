@php use App\Support\Format; @endphp
<x-layouts.app :title="'Comprobante '.$sale->number()">
    <x-slot:actions>
        <a href="{{ route('sales.create') }}" class="btn-primary">Nueva venta</a>
        <button type="button" class="btn-secondary" onclick="window.print()">Imprimir</button>
        <a href="{{ route('sales.index') }}" class="btn-secondary">Ver ventas</a>
    </x-slot:actions>

    @if ($sale->isVoided())
        <div class="mb-5 max-w-2xl rounded-xl border border-red-300 bg-red-50 px-5 py-4 text-red-900" role="status">
            <p class="font-semibold">Venta anulada</p>
            <p class="mt-1 text-sm">
                {{ $sale->voided_at->format('d/m/Y H:i') }} por {{ $sale->voider?->name ?? 'un administrador' }}.
                Motivo: {{ $sale->void_reason }}
            </p>
            <p class="mt-1 text-xs">El stock de los productos fue devuelto y esta venta no se suma a los totales.</p>
        </div>
    @endif

    <article class="card max-w-2xl">
        <header class="border-b border-slate-200 pb-4 text-center">
            <p class="text-lg font-bold text-slate-900">{{ config('app.name') }}</p>
            <p class="text-sm text-slate-500">Comprobante interno de venta (no es factura)</p>
            <p class="mt-2 text-xl font-bold tabular-nums text-slate-900">{{ $sale->number() }}</p>
        </header>

        <dl class="grid gap-x-6 gap-y-2 border-b border-slate-200 py-4 text-sm sm:grid-cols-2">
            <div class="flex justify-between gap-3 sm:block">
                <dt class="text-slate-500">Fecha</dt>
                <dd class="font-medium text-slate-900">{{ $sale->created_at->format('d/m/Y H:i') }}</dd>
            </div>
            <div class="flex justify-between gap-3 sm:block">
                <dt class="text-slate-500">Atendió</dt>
                <dd class="font-medium text-slate-900">{{ $sale->user->name }}</dd>
            </div>
            <div class="flex justify-between gap-3 sm:block">
                <dt class="text-slate-500">Método de pago</dt>
                <dd class="font-medium text-slate-900">{{ $sale->payment_method->label() }}</dd>
            </div>
            <div class="flex justify-between gap-3 sm:block">
                <dt class="text-slate-500">Turno de caja</dt>
                <dd class="font-medium text-slate-900"><a href="{{ route('cash.show', $sale->cashSession) }}" class="text-brand-700 hover:underline print:no-underline">#{{ $sale->cash_session_id }}</a></dd>
            </div>
        </dl>

        <table class="mt-4 w-full text-sm">
            <thead class="text-left text-slate-500">
                <tr>
                    <th class="pb-2 font-medium">Producto</th>
                    <th class="pb-2 text-right font-medium">Cant.</th>
                    <th class="pb-2 text-right font-medium">P. unit.</th>
                    <th class="pb-2 text-right font-medium">Subtotal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($sale->items as $item)
                    <tr>
                        <td class="py-2 pr-2 text-slate-900">{{ $item->product_name }}</td>
                        <td class="py-2 text-right tabular-nums">{{ Format::qty($item->quantity) }} {{ $item->unit->short() }}</td>
                        <td class="py-2 text-right tabular-nums">{{ Format::money($item->unit_price) }}</td>
                        <td class="py-2 text-right font-medium tabular-nums">{{ Format::money($item->subtotal) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-slate-300">
                    <td colspan="3" class="pt-3 text-right text-base font-bold text-slate-900">TOTAL</td>
                    <td class="pt-3 text-right text-xl font-bold tabular-nums {{ $sale->isVoided() ? 'text-slate-400 line-through' : 'text-slate-900' }}">{{ Format::money($sale->total) }}</td>
                </tr>
            </tfoot>
        </table>
    </article>

    @if (auth()->user()->isAdmin() && ! $sale->isVoided())
        <section class="card mt-5 max-w-2xl print:hidden" x-data="{ open: {{ $errors->has('reason') ? 'true' : 'false' }} }">
            @if ($canVoid)
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Anular esta venta</h2>
                        <p class="text-sm text-slate-600">Devuelve el stock y deja constancia del motivo. No se puede deshacer.</p>
                    </div>
                    <button type="button" class="btn-secondary" x-show="!open" @click="open = true">Anular…</button>
                </div>

                <form method="POST" action="{{ route('sales.void', $sale) }}" class="mt-4 space-y-4" x-show="open" x-cloak novalidate
                      onsubmit="return confirm('¿Anular la venta {{ $sale->number() }}? Se devolverá el stock y no se podrá deshacer.')">
                    @csrf
                    <x-field name="reason" label="Motivo de la anulación" :required="true">
                        <input id="reason" name="reason" type="text" maxlength="255" value="{{ old('reason') }}"
                               class="input @error('reason') input-error @enderror" placeholder="Ej.: el cliente se arrepintió, error al registrar">
                    </x-field>
                    <div class="flex gap-3">
                        <button type="submit" class="btn-danger">Anular venta</button>
                        <button type="button" class="btn-secondary" @click="open = false">Cancelar</button>
                    </div>
                </form>
            @else
                <h2 class="text-base font-bold text-slate-900">Anulación no disponible</h2>
                <p class="mt-1 text-sm text-slate-600">
                    El turno de caja #{{ $sale->cash_session_id }} ya fue cerrado y su cierre no puede cambiar.
                    Si fue un error, registra el ajuste en el turno actual.
                </p>
            @endif
        </section>
    @endif
</x-layouts.app>
