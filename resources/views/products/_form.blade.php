@php
    $isEdit = $product->exists;
    $unitsData = collect($units)->mapWithKeys(fn ($u) => [$u->value => ['decimals' => $u->allowsDecimals(), 'short' => $u->short()]]);
@endphp

<form method="POST" action="{{ $isEdit ? route('products.update', $product) : route('products.store') }}" class="space-y-6" novalidate
      x-data="{
          units: @js($unitsData),
          unit: @js(old('unit', $product->unit?->value ?? 'unidad')),
          purchase: @js(old('purchase_price', $product->purchase_price ?? 0)),
          sale: @js(old('sale_price', $product->sale_price)),
          get decimals() { return this.units[this.unit]?.decimals ?? false },
          get step() { return this.decimals ? '0.001' : '1' },
          get short() { return this.units[this.unit]?.short ?? '' },
          get belowCost() { return parseFloat(this.sale) > 0 && parseFloat(this.sale) < parseFloat(this.purchase) },
      }">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="card space-y-5">
        <h2 class="text-base font-bold text-slate-900">Datos del producto</h2>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="name" label="Nombre" :required="true" class="sm:col-span-2">
                <input id="name" name="name" type="text" maxlength="120" required value="{{ old('name', $product->name) }}"
                       class="input @error('name') input-error @enderror" placeholder="Ej.: Empanada de queso">
            </x-field>

            <x-field name="category_id" label="Categoría" :required="true">
                <select id="category_id" name="category_id" class="input @error('category_id') input-error @enderror">
                    <option value="">Selecciona una categoría</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('category_id', $product->category_id) === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                @if ($categories->isEmpty())
                    <p class="text-sm text-amber-700">Todavía no hay categorías. <a href="{{ route('categories.create') }}" class="font-medium underline">Crea la primera</a> y vuelve a este formulario.</p>
                @endif
            </x-field>

            <x-field name="unit" label="Se vende por" :required="true">
                <select id="unit" name="unit" x-model="unit" class="input @error('unit') input-error @enderror">
                    @foreach ($units as $unit)
                        <option value="{{ $unit->value }}">{{ $unit->label() }}</option>
                    @endforeach
                </select>
            </x-field>

            <x-field name="code" label="Código" hint="Opcional. Sirve para buscar más rápido (puede ser el código de barras).">
                <input id="code" name="code" type="text" maxlength="40" value="{{ old('code', $product->code) }}"
                       class="input @error('code') input-error @enderror">
            </x-field>
        </div>
    </div>

    <div class="card space-y-5">
        <h2 class="text-base font-bold text-slate-900">Precios</h2>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="purchase_price" label="Precio de compra (Bs)" :required="true" hint="Lo que te cuesta cada unidad. Solo lo ve el administrador.">
                <input id="purchase_price" name="purchase_price" type="number" step="0.01" min="0" inputmode="decimal" required
                       x-model="purchase" class="input @error('purchase_price') input-error @enderror">
            </x-field>

            <x-field name="sale_price" label="Precio de venta (Bs)" :required="true">
                <input id="sale_price" name="sale_price" type="number" step="0.01" min="0.01" inputmode="decimal" required
                       x-model="sale" class="input @error('sale_price') input-error @enderror">
            </x-field>
        </div>

        <p x-show="belowCost" x-cloak class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">
            El precio de venta es menor al de compra: cada venta daría pérdida. Puedes guardarlo igual si es intencional.
        </p>
    </div>

    <div class="card space-y-5">
        <h2 class="text-base font-bold text-slate-900">Inventario</h2>

        <div class="grid gap-5 sm:grid-cols-2">
            @unless ($isEdit)
                <x-field name="stock" label="Stock inicial" :required="true" hint="Cuántas unidades tienes hoy. Si no tienes, escribe 0.">
                    <div class="flex items-center gap-2">
                        <input id="stock" name="stock" type="number" min="0" inputmode="decimal" required
                               :step="step" value="{{ old('stock', 0) }}" class="input @error('stock') input-error @enderror">
                        <span class="w-10 text-sm text-slate-500" x-text="short"></span>
                    </div>
                </x-field>
            @else
                <div class="space-y-1.5">
                    <span class="block text-sm font-medium text-slate-700">Stock actual</span>
                    <p class="text-lg font-bold text-slate-900">{{ \App\Support\Format::qty($product->stock) }} {{ $product->unit->short() }}</p>
                    <p class="text-xs text-slate-500">El stock no se edita aquí para no perder el historial.
                        <a href="{{ route('products.stock.create', $product) }}" class="font-medium text-brand-700 hover:underline">Ajustar stock</a></p>
                </div>
            @endunless

            <x-field name="min_stock" label="Stock mínimo" :required="true" hint="Cuando el stock llegue a este número, el sistema avisará que hay que reponer.">
                <div class="flex items-center gap-2">
                    <input id="min_stock" name="min_stock" type="number" min="0" inputmode="decimal" required
                           :step="step" value="{{ old('min_stock', $product->min_stock ?? 0) }}" class="input @error('min_stock') input-error @enderror">
                    <span class="w-10 text-sm text-slate-500" x-text="short"></span>
                </div>
            </x-field>
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active ?? true))
                   class="h-4 w-4 rounded border-slate-300 text-brand-700 focus:ring-brand-500">
            Producto activo (aparece en ventas)
        </label>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="btn-primary">{{ $isEdit ? 'Guardar cambios' : 'Crear producto' }}</button>
        <a href="{{ $isEdit ? route('products.show', $product) : route('products.index') }}" class="btn-secondary">Cancelar</a>
    </div>
</form>
