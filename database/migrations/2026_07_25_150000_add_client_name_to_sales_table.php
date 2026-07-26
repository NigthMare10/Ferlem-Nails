<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('client_name', 120)->nullable()->after('appointment_id');
        });

        DB::table('sales')->whereNotNull('appointment_id')->orderBy('id')->chunkById(500, function ($sales): void {
            $names = DB::table('appointments')
                ->whereIn('id', $sales->pluck('appointment_id')->filter()->all())
                ->pluck('client_name', 'id');

            foreach ($sales as $sale) {
                if ($name = $names->get($sale->appointment_id)) {
                    DB::table('sales')->where('id', $sale->id)->update(['client_name' => $name]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('client_name');
        });
    }
};
