<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateOwner extends Command
{
    protected $signature = 'studio:create-owner
        {--name= : Nombre completo del propietario}
        {--email= : Correo electrónico del propietario}
        {--password= : Contraseña del propietario}
        {--force : Restablece la contraseña si el usuario ya existe}';

    protected $description = 'Crea o actualiza el primer propietario de Studio Lemus';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Nombre');
        $email = $this->option('email') ?: $this->ask('Correo');
        if (! $name || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Indica un nombre y un correo válido.');

            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $isNew = ! $user->exists;
        $shouldSetPassword = $isNew || $this->option('force');
        $password = $this->option('password');

        if ($shouldSetPassword && ! $password) {
            $password = $this->secret('Contraseña (mínimo 8 caracteres)');
        }

        if ($shouldSetPassword && strlen((string) $password) < 8) {
            $this->error('La contraseña debe tener al menos 8 caracteres.');

            return self::FAILURE;
        }

        $user->fill(['name' => $name]);
        $user->is_active = true;
        if ($shouldSetPassword) {
            $user->password = Hash::make($password);
        } elseif ($this->option('password')) {
            $this->warn('El usuario ya existe; la contraseña se conservó. Usa --force para restablecerla.');
        }

        $user->save();
        $user->syncRoles(['owner']);
        $this->info("Propietario {$user->email} listo y activo.");

        return self::SUCCESS;
    }
}
