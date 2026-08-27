<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SessionController extends Controller
{
    /**
     * Sesiones abiertas del usuario autenticado.
     *
     * Requiere el driver de sesión `database`: con Redis las sesiones no son
     * enumerables por usuario.
     */
    public function index(Request $request): JsonResponse
    {
        $currentId = $request->session()->getId();

        $sessions = DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($session) => [
                'id' => substr($session->id, 0, 8),
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'last_activity' => Carbon::createFromTimestamp($session->last_activity)->toIso8601String(),
                'is_current' => $session->id === $currentId,
            ]);

        return response()->json(['data' => $sessions]);
    }
}
