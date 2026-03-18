<?php

namespace App\Http\Controllers;

class HealthController extends Controller
{
    public function status()
    {
        return response()->json([
            'service' => 'finwise-api',
            'status' => 'healthy',
        ]);
    }
}

