<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard.
     */
    public function index(): Response
    {
        return Inertia::render('Dashboard', [
            'stats' => [
                'total_transactions' => 0,
                'active_bots' => 0,
                'total_volume' => 0,
                'profit_loss' => 0,
            ],
        ]);
    }
}
