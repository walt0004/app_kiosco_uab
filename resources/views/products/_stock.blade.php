<span class="tabular-nums">{{ \App\Support\Format::qty($product->stock) }} {{ $product->unit->short() }}</span>
<span class="ml-1"><x-stock-badge :product="$product" /></span>
