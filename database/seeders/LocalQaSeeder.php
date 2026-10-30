<?php

namespace Database\Seeders;

use App\Actions\Appointments\CreateAppointmentAction;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\LoyaltyEntry;
use App\Models\LoyaltyReward;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocalQaSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('qa') || DB::connection()->getDriverName() !== 'sqlite' || ! str_ends_with((string) config('database.connections.sqlite.database'), 'qa.sqlite')) {
            throw new \RuntimeException('QA seed is restricted to the isolated QA SQLite database.');
        }
        $this->call(DatabaseSeeder::class);
        foreach (['owner' => 'admin@lemus.test', 'employee' => 'empleada@lemus.test'] as $role => $email) {
            $user = User::firstOrCreate(['email' => $email], ['name' => $role === 'owner' ? 'Administración QA' : 'Empleada QA', 'password' => 'LocalTesting123!', 'is_active' => true]);
            $user->assignRole($role);
        }
        $customer = Customer::firstOrCreate(['email' => 'clienta@lemus.test'], ['name' => 'Sofía Ramírez', 'password' => 'LocalTesting123!']);
        $customer->forceFill(['email_verified_at' => now()])->save();
        LoyaltyEntry::firstOrCreate(['source_key' => 'qa-opening'], ['customer_id' => $customer->id, 'type' => 'adjustment', 'points' => 120, 'qualifying_points' => 120, 'note' => 'Saldo ficticio de pruebas']);
        foreach (['Esmalte en gel', 'Baño de acrílico', 'Pedicura', 'Retoque de pestañas', 'Diseño especial', 'Calcio', 'Extensiones', 'Promoción de temporada'] as $i => $name) {
            Service::firstOrCreate(['name' => $name], ['duration_minutes' => 60, 'price' => '350.00', 'is_active' => true, 'loyalty_points' => 20, 'is_promotion' => $i === 7]);
        }
        LoyaltyReward::firstOrCreate(['name' => 'Diseño de uñas gratis'], ['description' => 'Un diseño especial en tu próxima visita.', 'points_cost' => 100, 'benefit_type' => 'gift', 'per_customer_limit' => 2, 'is_active' => true]);
        LoyaltyReward::firstOrCreate(['name' => 'Retoque con descuento'], ['points_cost' => 150, 'benefit_type' => 'discount', 'discount_percent' => 20, 'per_customer_limit' => 2, 'is_active' => true]);
        if (! Appointment::where('client_name', 'Cita QA editable')->exists()) {
            app(CreateAppointmentAction::class)->execute(User::where('email', 'admin@lemus.test')->firstOrFail(), [
                'client_name' => 'Cita QA editable', 'date' => '2030-01-07', 'start_time' => '10:00', 'items' => [
                    ['service_id' => Service::where('name', 'Esmalte en gel')->value('id'), 'assigned_to' => User::where('email', 'empleada@lemus.test')->value('id'), 'quantity' => 1, 'duration_minutes' => 60],
                ],
            ]);
        }
    }
}
