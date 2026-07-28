<?php

namespace Database\Seeders;

use App\Models\BusinessHour;
use Illuminate\Database\Seeder;

class BusinessHourSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1, 7) as $weekday) {
            BusinessHour::query()->firstOrCreate(
                ['weekday' => $weekday],
                ['is_open' => true, 'opens_at' => '08:00:00', 'closes_at' => '18:00:00'],
            );
        }
    }
}
