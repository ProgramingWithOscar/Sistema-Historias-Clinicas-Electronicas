<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Audit\AuditOutcome;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Autenticación en modo SPA de Sanctum: se abre una sesión y el navegador
     * guarda la cookie `HttpOnly`, que ningún script de la página puede leer.
     * No se emite ningún token para almacenar en el cliente.
     *
     * El controlador NO recibe el logger por inyección ni lo instancia: lo pide
     * al único punto de acceso del Singleton. Cualquier otra capa que haga lo
     * mismo durante esta petición obtiene exactamente la misma instancia y, por
     * tanto, el mismo request_id y la misma secuencia de eventos.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $audit = AuditLogger::getInstance();

        $throttleKey = Str::lower($request->string('email')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, maxAttempts: 5)) {
            $audit->record(
                action: 'auth.login.throttled',
                outcome: AuditOutcome::Denied,
                statusCode: 429,
                metadata: ['email' => $request->string('email')->value()],
                request: $request,
            );

            return response()->json([
                'message' => 'Demasiados intentos fallidos. Intente de nuevo más tarde.',
            ], 429);
        }

        $credentials = $request->only(['email', 'password']);

        if (! Auth::attempt($credentials, remember: $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, decaySeconds: 60);

            // Nunca se registra la contraseña, sólo el intento (Ley 1581 de 2012).
            $audit->record(
                action: 'auth.login.failed',
                outcome: AuditOutcome::Failure,
                statusCode: 401,
                metadata: ['email' => $request->string('email')->value()],
                request: $request,
            );

            return response()->json(['message' => 'Credenciales inválidas.'], 401);
        }

        RateLimiter::clear($throttleKey);

        // Evita la fijación de sesión: el identificador previo al login se descarta.
        $request->session()->regenerate();

        $user = $request->user();

        $audit->record(
            action: 'auth.login.succeeded',
            statusCode: 200,
            actorId: $user->id,
            subjectType: User::class,
            subjectId: $user->id,
            request: $request,
        );

        return response()->json([
            'user' => $user->only(['id', 'name', 'email']),
            'request_id' => $audit->requestId(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $audit = AuditLogger::getInstance();
        $user = $request->user();

        $audit->record(
            action: 'auth.logout',
            statusCode: 200,
            actorId: $user->id,
            subjectType: User::class,
            subjectId: $user->id,
            request: $request,
        );

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        AuditLogger::getInstance()->record(
            action: 'auth.session.read',
            statusCode: 200,
            actorId: $user->id,
            subjectType: User::class,
            subjectId: $user->id,
            request: $request,
        );

        return response()->json($user->only(['id', 'name', 'email']));
    }
}
