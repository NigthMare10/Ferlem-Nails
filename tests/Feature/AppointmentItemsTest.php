<?php

namespace Tests\Feature;

use App\Actions\Appointments\CreateAppointmentAction;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AppointmentItemsTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_new_appointment_service_is_stored_as_individual_quantity_one_items(): void
    {
        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow('2026-07-24 14:00:00 UTC');
        $employee = User::factory()->create(['is_active' => true]);
        $employee->assignRole('employee');
        $service = Service::query()->create(['name' => 'Manicura', 'duration_minutes' => 30, 'price' => '100.00', 'is_active' => true]);

        $appointment = app(CreateAppointmentAction::class)->execute($employee, [
            'client_name' => 'Clienta',
            'date' => '2026-07-25',
            'start_time' => '10:00',
            'items' => [[
                'service_id' => $service->id,
                'assigned_to' => $employee->id,
                'quantity' => 2,
                'duration_minutes' => 30,
            ]],
        ]);

        $items = $appointment->items()->orderBy('position')->get();
        $this->assertCount(2, $items);
        $this->assertSame([1, 1], $items->pluck('quantity')->all());
        $this->assertSame(['100.00', '100.00'], $items->pluck('line_total')->all());
    }
}
