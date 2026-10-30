<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBusinessHoursRequest;
use App\Models\BusinessHour;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BusinessHourController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can(Permissions::SETTINGS_BUSINESS_HOURS_MANAGE), 403);

        return Inertia::render('Configuration/BusinessHours', [
            'hours' => BusinessHour::query()->orderBy('weekday')->get()->map(fn (BusinessHour $hour) => [
                'weekday' => $hour->weekday,
                'is_open' => $hour->is_open,
                'opens_at' => $hour->opens_at ? substr($hour->opens_at, 0, 5) : null,
                'closes_at' => $hour->closes_at ? substr($hour->closes_at, 0, 5) : null,
            ])->values(),
        ]);
    }

    public function update(UpdateBusinessHoursRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            foreach ($request->validated('hours') as $hour) {
                BusinessHour::query()->where('weekday', $hour['weekday'])->update([
                    'is_open' => $hour['is_open'],
                    'opens_at' => $hour['is_open'] ? $hour['opens_at'] : null,
                    'closes_at' => $hour['is_open'] ? $hour['closes_at'] : null,
                ]);
            }
        });

        return back()->with('success', 'Horario de atención actualizado correctamente.');
    }
}
