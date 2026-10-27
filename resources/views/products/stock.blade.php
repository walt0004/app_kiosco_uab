@php use App\Support\Format; @endphp
<x-layouts.app title="Ajustar stock">
    <div class="max-w-2xl space-y-5"
         x-data="{
             current: {{ (float) $product->stock }},
             operation: @js(old('operation', 'entrada')),
             quantity: @js(old('quantity', '')),
             get qty() { const n = parseFloat(this.quantity); return isNaN(n) ? null : n },
             get result() {
                 if (this.qty === null) return null;
                 if (this.operation === 'entrada') return this.current + this.qty;
                 if (this.operation === 'salida') return this.current - this.qty;
                 return this.qty;
             },
             fmt(n) { return new Intl.NumberFormat('es-BO', { maximumFractionDigits: 3 }).format(n) },
         }">

        <section class="card">
            <p class="text-sm text-slate-500">Producto</p>
            <p class="text-lg font-bold text-slate-900">{{ $product->name }}</p>
            <p class="mt-2 text-sm text-slate-600">Stock actual:
                <span class="font-semibold tabular-nums text-slate-900">{{ Format::qty($product->stock) }} {{ $product->unit->short() }}</span>
            </p>
        </section>

        <form method="POST" action="{{ route('products.stock.store', $product) }}" class="card space-y-5" novalidate>
            @csrf

            <fieldset>
                <legend class="mb-2 text-sm font-medium text-slate-700">¿Qué quieres hacer?</legend>
                <div class="grid gap-3 sm:grid-cols-3">
                    @foreach ([
                        'entrada' => ['Agregar stock', 'Llegó mercadería: suma al stock actual.'],
                        'salida' => ['Retirar stock', 'Merma, vencido o pérdida: resta del stock.'],
                        'correccion' => ['Corregir inventario', 'Escribes lo que contaste y el sistema ajusta.'],
                    ] as $value => [$title, $help])
                        <label class="cursor-pointer rounded-lg border p-3 text-sm transition"
                               :class="operation === '{{ $value }}' ? 'border-brand-600 bg-brand-50 ring-1 ring-brand-600' : 'border-slate-300 hover:bg-slate-50'">
                            <input type="radio" name="operation" value="{{ $value }}" x-model="operation" class="sr-only">
                            <span class="block font-semibold text-slate-900">{{ $title }}</span>
                            <span class="mt-0.5 block text-xs text-slate-500">{{ $help }}</span>
                        </label>
                    @endforeach
                </div>
                @error('operation')
                    <p class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
            </fieldset>

            <x-field name="quantity" label="Cantidad">
                <div class="flex items-center gap-2">
                    <input id="quantity" name="quantity" type="number" min="0" inputmode="decimal" required x-model="quantity"
                           step="{{ $product->unit->allowsDecimals() ? '0.001' : '1' }}"
                           class="input @error('quantity') input-error @enderror">
                    <span class="w-12 text-sm text-slate-500">{{ $product->unit->short() }}</span>
                </div>
            </x-field>

            <p x-show="result !== null" x-cloak class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700">
                El stock quedará en
                <span class="font-semibold tabular-nums" :class="result < 0 ? 'text-red-600' : 'text-slate-900'"
                      x-text="fmt(result) + ' {{ $product->unit->short() }}'"></span>
                <span x-show="result < 0" class="text-red-600"> (no puede quedar en negativo)</span>
            </p>

            <x-field name="reason" label="Motivo"
                     hint="Obligatorio al retirar o corregir. Ej.: producto vencido, conteo físico del domingo.">
                <input id="reason" name="reason" type="text" maxlength="255" value="{{ old('reason') }}"
                       class="input @error('reason') input-error @enderror">
            </x-field>

            <div class="flex items-center gap-3">
                <button type="submit" class="btn-primary">Guardar ajuste</button>
                <a href="{{ route('products.show', $product) }}" class="btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</x-layouts.app>
