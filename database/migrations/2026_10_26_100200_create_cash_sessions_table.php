<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un turno de caja por usuario: se abre con un monto inicial y se cierra
        // contando el efectivo.
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            // Igual a user_id mientras el turno está abierto y NULL al cerrarse.
            // Al ser único, la base de datos impide dos turnos abiertos del mismo usuario.
            $table->unsignedBigInteger('open_for_user_id')->nullable()->unique();
            $table->string('status', 10)->default('abierta');
            $table->decimal('opening_amount', 10, 2)->default(0);
            $table->timestamp('opened_at');
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('expected_cash', 10, 2)->nullable();
            $table->decimal('counted_cash', 10, 2)->nullable();
            $table->decimal('difference', 10, 2)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'opened_at']);
            $table->index(['user_id', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
};
