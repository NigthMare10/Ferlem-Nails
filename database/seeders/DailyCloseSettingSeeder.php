<?php

namespace Database\Seeders;

use App\Models\DailyCloseSetting;
use Illuminate\Database\Seeder;

class DailyCloseSettingSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        DailyCloseSetting::query()->firstOrCreate([], [
            'enabled' => false,
            'send_time' => '21:00',
            'timezone' => 'America/Tegucigalpa',
            'recipient_emails' => [],
        ]);
    }
}
