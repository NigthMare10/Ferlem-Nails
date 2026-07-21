<?php

namespace App\Http\Controllers;

use App\Actions\Reports\BuildSalesSummaryAction;
use App\Http\Requests\SalesEarningsRequest;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class EarningsController extends Controller
{
    public function __invoke(SalesEarningsRequest $request, BuildSalesSummaryAction $report): Response
    {
        return Inertia::render('Earnings/Index', [
            ...$report->execute($request->validated()),
            'employeeOptions' => User::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
                ->values(),
        ]);
    }
}
