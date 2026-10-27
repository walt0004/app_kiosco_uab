@props(['product'])

@switch($product->stockStatus())
    @case('agotado')
        <span class="badge bg-red-100 text-red-800">Agotado</span>
        @break
    @case('bajo')
        <span class="badge bg-amber-100 text-amber-800">Stock bajo</span>
        @break
    @default
        <span class="badge bg-emerald-100 text-emerald-800">Disponible</span>
@endswitch
