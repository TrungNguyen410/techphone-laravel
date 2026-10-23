<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): View
    {
        $year = (int) $request->query('year', now()->year);
        $year = $year >= 2000 && $year <= 2100 ? $year : now()->year;

        return view('admin.dashboard', ['year' => $year] + $dashboard->statistics($year));
    }
}
