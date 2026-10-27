<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('total', 10, 2);
            $table->string('payment_method', 20);
            $table->string('status', 15)->default('completada');
            // Evita registrar dos veces la misma venta si se pulsa el botón dos veces.
            $table->string('request_token', 64)->nullable()->unique();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->timestamps();

            // Índices pensados para unas 500 ventas diarias y reportes por fecha.
            $table->index('created_at');
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['cash_session_id', 'status']);
            $table->index(['payment_method', 'created_at']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            // Copia de los datos al momento de vender: editar el producto después
            // no cambia las ventas pasadas ni los reportes.
            $table->string('product_name', 120);
            $table->string('unit', 20);
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('purchase_price', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2);

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
