<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PingController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'message' => 'pong desde Laravel',
            'app' => config('app.name'),
            'time' => now()->toIso8601String(),
        ]);
    }
}
