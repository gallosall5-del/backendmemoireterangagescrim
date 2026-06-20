<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

class HealthController extends ApiController
{
    public function index(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'timestamp' => now()->toISOString()]);
    }
}
