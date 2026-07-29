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
            $table->decimal('discount_amount', 12, 2)->default(0)->after('subtotal');
        });

        Schema::create('sale_additional_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->string('description', 120);
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });

        DB::table('appointment_items')->where('quantity', '>', 1)->orderBy('appointment_id')->orderBy('position')->get()->groupBy('appointment_id')->each(function ($items): void {
            DB::table('appointment_items')->where('appointment_id', $items->first()->appointment_id)->update([
                'position' => DB::raw('position + 1000'),
            ]);
            foreach ($items as $item) {
                $unitCents = (int) round(((float) $item->unit_price) * 100);
                $duration = (int) $item->duration_minutes;
                $start = \Carbon\CarbonImmutable::parse($item->scheduled_start);
                DB::table('appointment_items')->where('id', $item->id)->update([
                    'quantity' => 1,
                    'line_total' => number_format($unitCents / 100, 2, '.', ''),
                    'scheduled_end' => $start->addMinutes($duration),
                ]);
                for ($copy = 1; $copy < $item->quantity; $copy++) {
                    DB::table('appointment_items')->insert([
                        'appointment_id' => $item->appointment_id,
                        'service_id' => $item->service_id,
                        'service_name' => $item->service_name,
                        'service_description' => $item->service_description,
                        'duration_minutes' => $duration,
                        'unit_price' => $item->unit_price,
                        'quantity' => 1,
                        'line_total' => number_format($unitCents / 100, 2, '.', ''),
                        'assigned_to' => $item->assigned_to,
                    'position' => $item->position + 1000 + $copy,
                        'scheduled_start' => $start->addMinutes($duration * $copy),
                        'scheduled_end' => $start->addMinutes($duration * ($copy + 1)),
                        'default_duration_minutes' => $item->default_duration_minutes,
                        'created_at' => $item->created_at,
                        'updated_at' => $item->updated_at,
                    ]);
                }
            }
            $all = DB::table('appointment_items')->where('appointment_id', $items->first()->appointment_id)->orderBy('scheduled_start')->orderBy('id')->get();
            foreach ($all as $index => $item) {
                DB::table('appointment_items')->where('id', $item->id)->update(['position' => $index + 1]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_additional_charges');
        Schema::table('sales', fn (Blueprint $table) => $table->dropColumn('discount_amount'));
    }
};
