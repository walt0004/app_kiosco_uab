<x-layouts.app title="Nueva venta">
    <div class="grid gap-5 lg:grid-cols-5"
         x-data="pos(@js(['searchUrl' => route('sales.search'), 'storeUrl' => route('sales.store'), 'csrf' => csrf_token()]))">

        {{-- Búsqueda de productos --}}
        <section class="card lg:col-span-3">
            <label for="search" class="mb-1.5 block text-sm font-medium text-slate-700">Buscar producto por nombre o código</label>
            <input id="search" type="search" x-ref="search" x-model="query" autocomplete="off"
                   @input="onInput()"
                   @keydown.enter.prevent="addHighlighted()"
                   @keydown.down.prevent="moveHighlight(1)"
                   @keydown.up.prevent="moveHighlight(-1)"
                   class="input text-base" placeholder="Escribe y presiona Enter para agregar el primero">

            <p class="mt-1.5 text-xs text-slate-500">Usa las flechas para moverte por la lista y Enter para agregar.</p>

            <div class="mt-4" role="listbox" aria-label="Resultados de la búsqueda">
                <p x-show="searchFailed" x-cloak class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">
                    No se pudo buscar. Revisa la conexión e inténtalo de nuevo.
                </p>
                <p x-show="!loading && !searchFailed && results.length === 0" x-cloak class="py-8 text-center text-sm text-slate-500">
                    No hay productos activos que coincidan con la búsqueda.
                </p>

                <ul class="divide-y divide-slate-100">
                    <template x-for="(p, i) in results" :key="p.id">
                        <li>
                            <button type="button" role="option" :aria-selected="i === highlighted"
                                    @click="add(p)" @mouseenter="highlighted = i"
                                    :disabled="p.stock <= 0"
                                    class="flex w-full items-center justify-between gap-4 px-3 py-2.5 text-left transition disabled:cursor-not-allowed disabled:opacity-50"
                                    :class="i === highlighted ? 'bg-brand-50' : 'hover:bg-slate-50'">
                                <span class="min-w-0">
                                    <span class="block truncate font-medium text-slate-900" x-text="p.name"></span>
                                    <span class="block text-xs text-slate-500">
                                        <span x-show="p.code" x-text="p.code + ' · '"></span>
                                        <span x-show="p.stock > 0" x-text="'Stock: ' + qty(p.stock) + ' ' + p.unit_short"></span>
                                        <span x-show="p.stock <= 0" class="font-semibold text-red-600">Agotado</span>
                                    </span>
                                </span>
                                <span class="shrink-0 font-semibold tabular-nums text-slate-900" x-text="money(p.price)"></span>
                            </button>
                        </li>
                    </template>
                </ul>
            </div>
        </section>

        {{-- Carrito --}}
        <section class="card lg:sticky lg:top-20 lg:col-span-2 lg:self-start">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-900">Venta actual</h2>
                <button type="button" x-show="cart.length" x-cloak @click="clear()" class="text-sm font-medium text-slate-500 hover:text-red-700">Vaciar</button>
            </div>

            <p x-show="!cart.length" class="py-8 text-center text-sm text-slate-500">
                Todavía no agregaste productos. Búscalos a la izquierda.
            </p>

            <ul class="divide-y divide-slate-100">
                <template x-for="(line, index) in cart" :key="line.id">
                    <li class="py-3">
                        <div class="flex items-start justify-between gap-3">
                            <p class="min-w-0 flex-1 text-sm font-medium text-slate-900" x-text="line.name"></p>
                            <button type="button" @click="remove(index)" class="text-slate-400 hover:text-red-600" :aria-label="'Quitar ' + line.name">✕</button>
                        </div>
                        <div class="mt-2 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-1">
                                <button type="button" @click="step(line, line.decimals ? -0.5 : -1)" class="btn-secondary px-2.5 py-1" aria-label="Disminuir">−</button>
                                <input type="number" x-model.number="line.qty" @change="normalize(line)"
                                       :step="line.decimals ? '0.001' : '1'" :max="line.stock" min="0" inputmode="decimal"
                                       class="input w-20 px-2 py-1 text-center tabular-nums" :aria-label="'Cantidad de ' + line.name">
                                <button type="button" @click="step(line, line.decimals ? 0.5 : 1)" class="btn-secondary px-2.5 py-1" aria-label="Aumentar">+</button>
                                <span class="ml-1 text-xs text-slate-500" x-text="line.unit_short"></span>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-slate-500" x-text="money(line.price) + ' c/u'"></p>
                                <p class="font-semibold tabular-nums text-slate-900" x-text="lineTotal(line)"></p>
                            </div>
                        </div>
                        <p x-show="line.qty >= line.stock" x-cloak class="mt-1 text-xs text-amber-700">Es todo el stock disponible.</p>
                    </li>
                </template>
            </ul>

            <div class="mt-4 flex items-baseline justify-between border-t border-slate-200 pt-4">
                <span class="text-sm font-medium text-slate-600">Total a cobrar</span>
                <span class="text-3xl font-bold tabular-nums text-slate-900" x-text="total"></span>
            </div>

            <fieldset class="mt-4">
                <legend class="mb-2 text-sm font-medium text-slate-700">Método de pago</legend>
                <div class="grid grid-cols-3 gap-2">
                    @foreach ($methods as $method)
                        <label class="cursor-pointer rounded-lg border px-3 py-2 text-center text-sm font-medium transition"
                               :class="method === '{{ $method->value }}' ? 'border-brand-600 bg-brand-50 text-brand-800 ring-1 ring-brand-600' : 'border-slate-300 text-slate-700 hover:bg-slate-50'">
                            <input type="radio" name="payment_method" value="{{ $method->value }}" x-model="method" class="sr-only">
                            {{ $method->label() }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <p x-show="error" x-cloak x-text="error" role="alert" class="mt-4 rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-900"></p>

            <button type="button" @click="submit()" :disabled="!cart.length || submitting" class="btn-primary mt-4 w-full py-3 text-base">
                <span x-show="!submitting">Registrar venta</span>
                <span x-show="submitting" x-cloak>Registrando…</span>
            </button>
        </section>
    </div>
</x-layouts.app>
