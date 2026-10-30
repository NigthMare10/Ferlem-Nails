<?php

namespace App\Http\Middleware;

use App\Models\Expense;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureExpenseIsVisible
{
    public function handle(Request $request, Closure $next): Response
    {
        $expense = $request->route('expense');
        $expenseId = $expense instanceof Expense ? $expense->getKey() : $expense;

        abort_unless(
            Expense::query()->visibleTo($request->user())->whereKey($expenseId)->exists(),
            403,
        );

        return $next($request);
    }
}
