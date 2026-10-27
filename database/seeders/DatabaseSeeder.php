<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Crea los usuarios iniciales. CAMBIA estas contraseñas antes de usar el
     * sistema con datos reales.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@kiosco.test'],
            ['name' => 'Administrador', 'password' => 'Admin12345', 'role' => Role::Administrador, 'is_active' => true],
        );

        User::updateOrCreate(
            ['email' => 'vendedor@kiosco.test'],
            ['name' => 'Vendedor de ejemplo', 'password' => 'Vendedor12345', 'role' => Role::Vendedor, 'is_active' => true],
        );

        // Productos de ejemplo solo en entorno local, para probar el sistema.
        if (app()->isLocal()) {
            $this->call(DemoProductSeeder::class);
        }
    }
}
