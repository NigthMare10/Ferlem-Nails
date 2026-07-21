<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase4AAppointmentMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_appointment_migrations_are_reversible_and_restore_required_schema(): void
    {
        $appointments = require database_path('migrations/2026_07_20_100000_create_appointments_table.php');
        $items = require database_path('migrations/2026_07_20_100100_create_appointment_items_table.php');
        $events = require database_path('migrations/2026_07_20_100200_create_appointment_events_table.php');
        $segments = require database_path('migrations/2026_07_20_101000_add_segments_to_appointment_items_table.php');
        $terminalReasons = require database_path('migrations/2026_07_21_100000_add_no_show_reason_to_appointments_table.php');

        $terminalReasons->down();
        $segments->down();
        $events->down();
        $items->down();
        $appointments->down();

        $this->assertFalse(Schema::hasTable('appointment_events'));
        $this->assertFalse(Schema::hasTable('appointment_items'));
        $this->assertFalse(Schema::hasTable('appointments'));

        $appointments->up();
        $items->up();
        $events->up();
        $segments->up();
        $terminalReasons->up();

        $this->assertTrue(Schema::hasColumns('appointments', [
            'client_name',
            'client_phone',
            'assigned_to',
            'scheduled_start',
            'scheduled_end',
            'expected_total',
            'expected_duration_minutes',
            'status',
            'notes',
            'created_by',
            'completed_at',
            'canceled_at',
            'canceled_by',
            'cancellation_reason',
            'no_show_at',
            'no_show_by',
            'no_show_reason',
        ]));
        $this->assertTrue(Schema::hasColumns('appointment_items', [
            'appointment_id',
            'service_id',
            'service_name',
            'service_description',
            'duration_minutes',
            'unit_price',
            'quantity',
            'line_total',
            'assigned_to',
            'position',
            'scheduled_start',
            'scheduled_end',
            'default_duration_minutes',
        ]));
        $this->assertTrue(Schema::hasColumns('appointment_events', [
            'appointment_id',
            'type',
            'performed_by',
            'occurred_at',
            'previous_values',
            'new_values',
            'notes',
        ]));

        $indexNames = collect(Schema::getIndexes('appointments'))->pluck('name');
        $this->assertTrue($indexNames->contains('appointments_assignee_status_start_index'));
        $this->assertTrue($indexNames->contains('appointments_status_start_index'));
    }
}
