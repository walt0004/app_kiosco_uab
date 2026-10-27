@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen" x-data="{ menu: false }">
    @php
        $user = auth()->user();
        $navLink = fn (bool $active) => 'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition '
            .($active ? 'bg-white/15 text-white' : 'text-brand-100 hover:bg-white/10 hover:text-white');
    @endphp

    {{-- Barra lateral --}}
    <aside class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-brand-900 transition-transform lg:translate-x-0 print:hidden"
           :class="{ 'translate-x-0': menu }" x-cloak>
        <div class="px-5 py-5">
            <p class="text-lg font-bold text-white">{{ config('app.name') }}</p>
            <p class="text-xs text-brand-200">Sistema de ventas del kiosco</p>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-2" aria-label="Menú principal">
            <a href="{{ route('dashboard') }}" class="{{ $navLink(request()->routeIs('dashboard')) }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10"/></svg>
                Panel
            </a>
            <a href="{{ route('sales.create') }}" class="{{ $navLink(request()->routeIs('sales.create')) }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 2.3a1 1 0 00.7 1.7H17M17 17a2 2 0 100 4 2 2 0 000-4zM9 17a2 2 0 100 4 2 2 0 000-4z"/></svg>
                Vender
            </a>
            <a href="{{ route('sales.index') }}" class="{{ $navLink(request()->routeIs('sales.index', 'sales.show')) }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.6a1 1 0 01.7.3l5.4 5.4a1 1 0 01.3.7V19a2 2 0 01-2 2z"/></svg>
                Ventas
            </a>
            <a href="{{ route('cash.index') }}" class="{{ $navLink(request()->routeIs('cash.*')) }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a4 4 0 00-8 0v2m-2 0h12a2 2 0 012 2v8a2 2 0 01-2 2H7a2 2 0 01-2-2v-8a2 2 0 012-2z"/></svg>
                Caja
            </a>
            <a href="{{ route('products.index') }}" class="{{ $navLink(request()->routeIs('products.*')) }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0v10l-8 4m0-14L4 7m8 4v10M4 7v10l8 4"/></svg>
                <span class="flex-1">Productos</span>
                @if (($lowStockCount ?? 0) > 0)
                    <span class="badge bg-amber-400 text-amber-950" title="Productos con stock bajo o agotado">{{ $lowStockCount }}</span>
                @endif
            </a>
            @if ($user->isAdmin())
                <a href="{{ route('categories.index') }}" class="{{ $navLink(request()->routeIs('categories.*')) }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5a1.99 1.99 0 011.4.6l7 7a2 2 0 010 2.8l-5 5a2 2 0 01-2.8 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>
                    Categorías
                </a>
                <a href="{{ route('reports.index') }}" class="{{ $navLink(request()->routeIs('reports.*')) }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6m4 6V7m4 10v-3M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    Reportes
                </a>
                <a href="{{ route('users.index') }}" class="{{ $navLink(request()->routeIs('users.*')) }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-5-3.9M9 11a4 4 0 100-8 4 4 0 000 8zm0 2c-3.3 0-6 1.8-6 4v3h12v-3c0-2.2-2.7-4-6-4zm8-2a3 3 0 100-6"/></svg>
                    Usuarios
                </a>
            @endif
        </nav>

        <div class="border-t border-white/10 p-4">
            <p class="truncate text-sm font-semibold text-white">{{ $user->name }}</p>
            <p class="text-xs text-brand-200">{{ $user->role->label() }}</p>
            <a href="{{ route('profile.edit') }}" class="mb-3 mt-1 inline-block text-xs font-medium text-brand-100 underline-offset-2 hover:text-white hover:underline">Cambiar mi contraseña</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full rounded-lg border border-white/20 px-3 py-2 text-sm font-medium text-white transition hover:bg-white/10">
                    Cerrar sesión
                </button>
            </form>
        </div>
    </aside>

    {{-- Fondo oscuro al abrir el menú en pantallas angostas --}}
    <div class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden" x-show="menu" x-cloak @click="menu = false"></div>

    <div class="lg:pl-64 print:pl-0">
        <header class="sticky top-0 z-20 flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-3 sm:px-8 print:static print:border-0">
            <button type="button" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" @click="menu = true" aria-label="Abrir menú">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <h1 class="flex-1 truncate text-xl font-bold text-slate-900">{{ $title }}</h1>
            @if ($openCashSession ?? null)
                <a href="{{ route('cash.index') }}" class="badge hidden bg-emerald-100 text-emerald-800 hover:bg-emerald-200 sm:inline-flex print:hidden"
                   title="Abierta desde {{ $openCashSession->opened_at->format('d/m/Y H:i') }}">Caja abierta</a>
            @else
                <a href="{{ route('cash.index') }}" class="badge hidden bg-amber-100 text-amber-800 hover:bg-amber-200 sm:inline-flex print:hidden">Caja cerrada</a>
            @endif
            @isset($actions)
                <div class="flex items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>

        <main class="px-4 py-6 sm:px-8">
            <x-flash />
            {{ $slot }}
        </main>
    </div>
</body>
</html>
