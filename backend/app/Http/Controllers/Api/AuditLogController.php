<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Últimos eventos que el AuditLogger registró para el usuario autenticado.
     *
     * Incluye dos conjuntos:
     *  - los eventos con su `actor_id`, es decir, lo que el usuario hizo;
     *  - los intentos de acceso fallidos contra su correo, que se guardan sin
     *    `actor_id` porque en ese momento nadie está autenticado todavía.
     *
     * Ese segundo grupo es justamente el que interesa vigilar: alguien
     * probando contraseñas contra tu cuenta. Se filtra por correo para que un
     * usuario no pueda leer los intentos dirigidos a otras cuentas.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $logs = AuditLog::query()
            ->where(function (Builder $query) use ($user) {
                $query->where('actor_id', $user->id)
                    ->orWhere(function (Builder $anonimos) use ($user) {
                        $anonimos->whereNull('actor_id')
                            ->where('metadata->email', $user->email);
                    });
            })
            ->latest('id')
            ->limit(25)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'request_id' => substr($log->request_id, 0, 8),
                'sequence' => $log->sequence,
                'action' => $log->action,
                'outcome' => $log->outcome->value,
                'outcome_label' => $log->outcome->label(),
                'status_code' => $log->status_code,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $logs]);
    }
}
