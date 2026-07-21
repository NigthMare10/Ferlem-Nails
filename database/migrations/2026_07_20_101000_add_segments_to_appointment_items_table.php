<?php

use App\Models\Appointment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = Schema::getColumnListing('appointment_items');
        Schema::table('appointment_items', function (Blueprint $table) use ($columns) {
            // Columns may already exist after a database engine applies part of a failed ALTER TABLE.
            if (! in_array('assigned_to', $columns, true)) {
                $table->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            }
            if (! in_array('position', $columns, true)) {
                $table->unsignedInteger('position')->nullable();
            }
            if (! in_array('scheduled_start', $columns, true)) {
                $table->timestamp('scheduled_start')->nullable();
            }
            if (! in_array('scheduled_end', $columns, true)) {
                $table->timestamp('scheduled_end')->nullable();
            }
            if (! in_array('default_duration_minutes', $columns, true)) {
                $table->unsignedInteger('default_duration_minutes')->nullable();
            }
        });
        $indexes = collect(Schema::getIndexes('appointment_items'))->pluck('name');
        if (! $indexes->contains('appointment_items_service_id_index')) {
            Schema::table('appointment_items', fn (Blueprint $table) => $table->index('service_id', 'appointment_items_service_id_index'));
        }
        if (! $indexes->contains('appointment_items_appointment_id_index')) {
            Schema::table('appointment_items', fn (Blueprint $table) => $table->index('appointment_id', 'appointment_items_appointment_id_index'));
        }
        $indexes = collect(Schema::getIndexes('appointment_items'))->pluck('name');
        Schema::table('appointment_items', function (Blueprint $table) use ($indexes) {
            if ($indexes->contains('appointment_items_appointment_service_unique')) {
                $table->dropUnique('appointment_items_appointment_service_unique');
            }
            if (! $indexes->contains('appointment_items_assigned_to_index')) {
                $table->index('assigned_to', 'appointment_items_assigned_to_index');
            }
            if (! $indexes->contains('appointment_items_scheduled_start_index')) {
                $table->index('scheduled_start', 'appointment_items_scheduled_start_index');
            }
            if (! $indexes->contains('appointment_items_scheduled_end_index')) {
                $table->index('scheduled_end', 'appointment_items_scheduled_end_index');
            }
            if (! $indexes->contains('appointment_items_appointment_position_unique')) {
                $table->unique(['appointment_id', 'position'], 'appointment_items_appointment_position_unique');
            }
        });

        Appointment::query()->with('items')->orderBy('id')->each(function (Appointment $appointment): void {
            $segmentStart = $appointment->scheduled_start;

            foreach ($appointment->items as $index => $item) {
                $segmentEnd = $segmentStart->addMinutes($item->duration_minutes * $item->quantity);
                $item->assigned_to = $appointment->assigned_to;
                $item->position = $index + 1;
                $item->scheduled_start = $segmentStart;
                $item->scheduled_end = $segmentEnd;
                $item->default_duration_minutes = $item->duration_minutes;
                $item->save();
                $segmentStart = $segmentEnd;
            }
        });

        Schema::table('appointment_items', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable(false)->change();
            $table->unsignedInteger('position')->nullable(false)->change();
            $table->timestamp('scheduled_start')->nullable(false)->change();
            $table->timestamp('scheduled_end')->nullable(false)->change();
            $table->unsignedInteger('default_duration_minutes')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('appointment_items')) {
            return;
        }

        $indexes = collect(Schema::getIndexes('appointment_items'))->pluck('name');
        Schema::table('appointment_items', function (Blueprint $table) use ($indexes) {
            if ($indexes->contains('appointment_items_appointment_position_unique')) {
                $table->dropUnique('appointment_items_appointment_position_unique');
            }
            if ($indexes->contains('appointment_items_assigned_to_index')) {
                $table->dropIndex('appointment_items_assigned_to_index');
            }
            if ($indexes->contains('appointment_items_scheduled_start_index')) {
                $table->dropIndex('appointment_items_scheduled_start_index');
            }
            if ($indexes->contains('appointment_items_scheduled_end_index')) {
                $table->dropIndex('appointment_items_scheduled_end_index');
            }
            if ($indexes->contains('appointment_items_service_id_index')) {
                $table->dropIndex('appointment_items_service_id_index');
            }
            if ($indexes->contains('appointment_items_appointment_id_index')) {
                $table->dropIndex('appointment_items_appointment_id_index');
            }
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn(['position', 'scheduled_start', 'scheduled_end', 'default_duration_minutes']);
            $table->unique(['appointment_id', 'service_id'], 'appointment_items_appointment_service_unique');
        });
    }
};
