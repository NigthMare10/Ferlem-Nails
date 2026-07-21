<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class CashController extends Controller
{
    public function index(): RedirectResponse
    {
        return to_route('sales.create');
    }
}
