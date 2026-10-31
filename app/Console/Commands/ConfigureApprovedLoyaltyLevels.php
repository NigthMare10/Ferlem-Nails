<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ConfigureApprovedLoyaltyLevels extends Command
{
    protected $signature = 'loyalty:configure-approved-levels';
    protected $description = 'Replace only untouched initial levels with the approved Brillo/Glow/Amatista/Diamante program';

    public function handle(): int
    {
        $levels = [
            ['name' => 'Brillo', 'points' => 0, 'benefits' => 'Acumula puntos por tus servicios'],
            ['name' => 'Glow', 'points' => 100, 'benefits' => 'Acceso al catálogo de recompensas'],
            ['name' => 'Amatista', 'points' => 1000, 'benefits' => 'Reconocimiento de fidelidad'],
            ['name' => 'Diamante', 'points' => 5000, 'benefits' => 'Atenciones especiales según disponibilidad'],
        ];
        return DB::transaction(function () use ($levels) {
            $settings = DB::table('loyalty_settings')->where('id', 1)->lockForUpdate()->first();
            $before = json_decode($settings->levels, true);
            if ($before === $levels) {
                $this->info('Los niveles aprobados ya están configurados.');

                return self::SUCCESS;
            }
            if (array_column($before, 'name') !== ['Brillo', 'Glow', 'Lemus VIP', 'Icon'] || array_column($before, 'points') !== [0, 100, 200, 350] || $settings->version !== 1) {
                $this->error('Ya existe una configuración personalizada. No se modificó: revísala desde Niveles.');

                return self::FAILURE;
            }
            DB::table('loyalty_settings')->where('id', 1)->update(['levels' => json_encode($levels), 'version' => 2, 'updated_at' => now()]);
            DB::table('security_audits')->insert(['action' => 'levels.initialized', 'subject_type' => 'settings', 'subject_id' => 1, 'before' => json_encode($before), 'after' => json_encode($levels), 'created_at' => now(), 'updated_at' => now()]);
            $this->info('Niveles aprobados configurados sin alterar saldos ni puntos calificables.');

            return self::SUCCESS;
        });
    }
}
