<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Nullable solo por compatibilidad con productos creados antes de
            // existir las categorías; el formulario la exige siempre.
            $table->foreignId('category_id')->nullable()->after('code')->constrained()->restrictOnDelete();
        });

        // Los productos que ya existían pasan a una categoría "General".
        if (DB::table('products')->exists()) {
            $now = now();
            $id = DB::table('categories')->where('name', 'General')->value('id')
                ?? DB::table('categories')->insertGetId(['name' => 'General', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);

            DB::table('products')->whereNull('category_id')->update(['category_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
