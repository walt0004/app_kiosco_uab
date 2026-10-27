<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Ingresar' }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-brand-900 px-4 py-10">
    <div class="w-full max-w-md">
        <div class="mb-6 text-center">
            <h1 class="text-2xl font-bold text-white">{{ config('app.name') }}</h1>
            <p class="mt-1 text-sm text-brand-200">Sistema de ventas del kiosco universitario</p>
        </div>
        <div class="rounded-2xl bg-white p-7 shadow-xl">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
