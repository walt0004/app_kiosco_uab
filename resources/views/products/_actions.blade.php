<span class="whitespace-nowrap text-sm">
    <a href="{{ route('products.show', $product) }}" class="font-medium text-brand-700 hover:underline">Ver</a>
    @if ($isAdmin)
        <span class="mx-1 text-slate-300">|</span>
        <a href="{{ route('products.edit', $product) }}" class="font-medium text-brand-700 hover:underline">Editar</a>
        <span class="mx-1 text-slate-300">|</span>
        <a href="{{ route('products.stock.create', $product) }}" class="font-medium text-brand-700 hover:underline">Ajustar stock</a>
    @endif
</span>
