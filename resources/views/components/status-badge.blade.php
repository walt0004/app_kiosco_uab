@props(['active'])
@if ($active)
<span class="badge bg-sky-100 text-sky-800">Activo</span>
@else
<span class="badge bg-slate-200 text-slate-700">Inactivo</span>
@endif
