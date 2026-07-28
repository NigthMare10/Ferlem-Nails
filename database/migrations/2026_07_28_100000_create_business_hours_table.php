<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_hours', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('weekday')->unique();
            $table->boolean('is_open')->default(true);
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->timestamps();
        });

        $now = now();
        $opensAt = env('APPOINTMENTS_OPEN_TIME', '08:00');
        $closesAt = env('APPOINTMENTS_CLOSE_TIME', '18:00');
        DB::table('business_hours')->insert(collect(range(1, 7))->map(fn (int $weekday) => [
            'weekday' => $weekday,
            'is_open' => true,
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());

        $permission = Permission::firstOrCreate(['name' => Permissions::SETTINGS_BUSINESS_HOURS_MANAGE, 'guard_name' => 'web']);
        Role::query()->whereIn('name', ['owner', 'administrator'])->each(fn (Role $role) => $role->givePermissionTo($permission));
    }

    public function down(): void
    {
        Permission::findByName(Permissions::SETTINGS_BUSINESS_HOURS_MANAGE, 'web')->delete();
        Schema::dropIfExists('business_hours');
    }
};
