<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /**
     * Display the settings index page.
     */
    public function index(): Response
    {
        return Inertia::render('Settings/Index');
    }
}

