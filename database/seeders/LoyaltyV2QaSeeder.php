<?php

namespace Database\Seeders;

use App\Models\{Customer, LoyaltyEntry};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LoyaltyV2QaSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('qa') || DB::connection()->getDriverName() !== 'sqlite' || ! str_ends_with(config('database.connections.sqlite.database'), '-qa.sqlite')) {
            throw new \RuntimeException('Solo base QA aislada.');
        }
        $this->call(LocalQaSeeder::class);
        $levels = [['name' => 'Brillo', 'points' => 0, 'benefits' => 'Acumula puntos por tus servicios'], ['name' => 'Glow', 'points' => 100, 'benefits' => 'Acceso al catálogo de recompensas'], ['name' => 'Amatista', 'points' => 1000, 'benefits' => 'Reconocimiento de fidelidad'], ['name' => 'Diamante', 'points' => 5000, 'benefits' => 'Atenciones especiales según disponibilidad']];
        DB::table('loyalty_settings')->where('id', 1)->update(['levels' => json_encode($levels)]);
        $client = Customer::where('email', 'clienta@lemus.test')->firstOrFail();
        LoyaltyEntry::firstOrCreate(['source_key' => 'qa-v2-max'], ['customer_id' => $client->id, 'points' => 2000, 'qualifying_points' => 6000, 'type' => 'award', 'note' => 'Saldo QA nivel máximo']);
        foreach (['carla' => [true, true], 'inactiva' => [false, true], 'sinverificar' => [true, false]] as $name => [$active, $verified]) {
            $c = Customer::firstOrCreate(['email' => $name.'@lemus.test'], ['name' => ucfirst($name).' de prueba', 'password' => 'LocalTesting123!']);
            $c->forceFill(['is_active' => $active, 'email_verified_at' => $verified ? now() : null])->save();
        }
    }
}
