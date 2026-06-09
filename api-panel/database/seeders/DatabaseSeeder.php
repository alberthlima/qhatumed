<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed standard SIAT document types
        \Illuminate\Support\Facades\DB::table('type_documents')->insertOrIgnore([
            ['id' => 1, 'name' => 'CI - Cédula de Identidad', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'CEX - Cédula de Extranjero', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'PAS - Pasaporte', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'OD - Otro Documento', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'name' => 'NIT - Número de Identificación Tributaria', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Llamamos al seeder de permisos y roles
        $this->call([
            PermissionsDemoSeeder::class,
        ]);
    }
}
