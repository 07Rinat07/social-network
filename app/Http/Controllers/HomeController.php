<?php

namespace App\Http\Controllers;

/**
 * Legacy dashboard controller for classic authenticated home page.
 */
class HomeController extends Controller
{
    /**
     * Register auth middleware for home dashboard route.
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Render authenticated home view.
     */
    public function index()
    {
        return view('home');
    }
}
