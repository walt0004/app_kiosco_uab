<?php

namespace App\Providers;

use App\Models\CashSession;
use App\Models\Product;
use App\Services\ReportService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Los identificadores de las rutas son siempre números.
        foreach (['product', 'category', 'sale', 'cashSession', 'user'] as $parameter) {
            Route::pattern($parameter, '[0-9]+');
        }

        // Solo los reportes que existen.
        Route::pattern('report', implode('|', ReportService::SLUGS));

        // Datos del menú lateral: productos con stock bajo/agotado y caja del usuario.
        View::composer('components.layouts.app', function ($view) {
            $view->with('lowStockCount', Product::lowStock()->count());

            $user = auth()->user();
            $view->with('openCashSession', $user ? CashSession::open()->where('user_id', $user->id)->first() : null);
        });
    }
}
