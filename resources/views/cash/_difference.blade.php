@if ($difference == 0)
<span class="font-medium text-emerald-700">Cuadra</span>
@elseif ($difference > 0)
<span class="font-medium text-sky-700">Sobran {{ \App\Support\Format::money($difference) }}</span>
@else
<span class="font-medium text-red-700">Faltan {{ \App\Support\Format::money(abs($difference)) }}</span>
@endif
