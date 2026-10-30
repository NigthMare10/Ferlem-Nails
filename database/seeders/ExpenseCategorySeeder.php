<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Nómina', 'slug' => 'nomina'],
            ['name' => 'Alimentación', 'slug' => 'alimentacion'],
            ['name' => 'Transporte', 'slug' => 'transporte'],
            ['name' => 'Materiales e implementos', 'slug' => 'materiales-e-implementos'],
            ['name' => 'Servicios públicos', 'slug' => 'servicios-publicos'],
            ['name' => 'Mantenimiento', 'slug' => 'mantenimiento'],
            ['name' => 'Alquiler', 'slug' => 'alquiler'],
            ['name' => 'Otros', 'slug' => 'otros'],
        ] as $category) {
            ExpenseCategory::query()->firstOrCreate(
                ['slug' => $category['slug']],
                ['name' => $category['name'], 'is_active' => true],
            );
        }
    }
}
