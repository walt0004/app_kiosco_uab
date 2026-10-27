<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CashController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/panel');

Route::middleware('guest')->group(function () {
    Route::get('/ingresar', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/ingresar', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/salir', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/panel', [DashboardController::class, 'index'])->name('dashboard');

    // ---- Productos: administrador y vendedor pueden consultar el catálogo ----
    Route::get('/productos', [ProductController::class, 'index'])->name('products.index');
    Route::get('/productos/datos', [ProductController::class, 'data'])->name('products.data');

    // Solo el administrador crea, edita y ajusta stock.
    Route::middleware('role:administrador')->group(function () {
        Route::get('/productos/crear', [ProductController::class, 'create'])->name('products.create');
        Route::post('/productos', [ProductController::class, 'store'])->name('products.store');
        Route::get('/productos/{product}/editar', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('/productos/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::patch('/productos/{product}/estado', [ProductController::class, 'toggle'])->name('products.toggle');
        Route::get('/productos/{product}/stock', [StockAdjustmentController::class, 'create'])->name('products.stock.create');
        Route::post('/productos/{product}/stock', [StockAdjustmentController::class, 'store'])->name('products.stock.store');

        // ---- Categorías (solo administrador) ----
        Route::get('/categorias', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('/categorias/datos', [CategoryController::class, 'data'])->name('categories.data');
        Route::get('/categorias/crear', [CategoryController::class, 'create'])->name('categories.create');
        Route::post('/categorias', [CategoryController::class, 'store'])->name('categories.store');
        Route::get('/categorias/{category}/editar', [CategoryController::class, 'edit'])->name('categories.edit');
        Route::put('/categorias/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::patch('/categorias/{category}/estado', [CategoryController::class, 'toggle'])->name('categories.toggle');
        Route::delete('/categorias/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    });

    Route::get('/productos/{product}', [ProductController::class, 'show'])->name('products.show');

    // ---- Caja (turnos): cada usuario abre y cierra la suya ----
    Route::get('/caja', [CashController::class, 'index'])->name('cash.index');
    Route::post('/caja/abrir', [CashController::class, 'open'])->name('cash.open');
    Route::get('/caja/historial', [CashController::class, 'history'])->name('cash.history');
    Route::get('/caja/historial/datos', [CashController::class, 'data'])->name('cash.data');
    Route::get('/caja/{cashSession}', [CashController::class, 'show'])->name('cash.show');
    Route::post('/caja/{cashSession}/cerrar', [CashController::class, 'close'])->name('cash.close');
    Route::get('/caja/{cashSession}/pdf', [CashController::class, 'pdf'])->name('cash.pdf');

    // ---- Ventas ----
    Route::get('/ventas', [SaleController::class, 'index'])->name('sales.index');
    Route::get('/ventas/datos', [SaleController::class, 'data'])->name('sales.data');
    Route::get('/ventas/nueva', [SaleController::class, 'create'])->name('sales.create');
    Route::get('/ventas/buscar-productos', [SaleController::class, 'search'])->name('sales.search');
    Route::post('/ventas', [SaleController::class, 'store'])->name('sales.store');
    Route::get('/ventas/{sale}', [SaleController::class, 'show'])->name('sales.show');
    Route::post('/ventas/{sale}/anular', [SaleController::class, 'void'])->middleware('role:administrador')->name('sales.void');

    // ---- Mi cuenta ----
    Route::get('/mi-cuenta', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/mi-cuenta', [ProfileController::class, 'update'])->name('profile.update');

    // ---- Solo administrador: usuarios y reportes ----
    Route::middleware('role:administrador')->group(function () {
        Route::get('/usuarios', [UserController::class, 'index'])->name('users.index');
        Route::get('/usuarios/datos', [UserController::class, 'data'])->name('users.data');
        Route::get('/usuarios/crear', [UserController::class, 'create'])->name('users.create');
        Route::post('/usuarios', [UserController::class, 'store'])->name('users.store');
        Route::get('/usuarios/{user}/editar', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/usuarios/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('/usuarios/{user}/estado', [UserController::class, 'toggle'])->name('users.toggle');

        Route::get('/reportes', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reportes/{report}', [ReportController::class, 'show'])->name('reports.show');
        Route::get('/reportes/{report}/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
    });
});
