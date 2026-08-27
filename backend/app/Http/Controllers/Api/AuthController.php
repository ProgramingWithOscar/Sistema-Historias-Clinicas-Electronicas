<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
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
                metadata: ['email' => $request->string('email')->value()],
                request: $request,
            );

            return response()->json([
                'message' => 'Demasiados intentos fallidos. Intente de nuevo más tarde.',
            ], 429);
        }

        $user = User::where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check($request->string('password')->value(), $user->password)) {
            RateLimiter::hit($throttleKey, decaySeconds: 60);

            // Nunca se registra la contraseña, sólo el intento (Ley 1581 de 2012).
            $audit->record(
                action: 'auth.login.failed',
                metadata: ['email' => $request->string('email')->value()],
                request: $request,
            );

            return response()->json(['message' => 'Credenciales inválidas.'], 401);
        }

        RateLimiter::clear($throttleKey);

        $token = $user->createToken(
            $request->string('device_name')->value() ?: 'api'
        );

        $audit->record(
            action: 'auth.login.succeeded',
            actorId: $user->id,
            subjectType: User::class,
            subjectId: $user->id,
            metadata: ['token_name' => $token->accessToken->name],
            request: $request,
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => $user->only(['id', 'name', 'email']),
            'request_id' => $audit->requestId(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $audit = AuditLogger::getInstance();
        $user = $request->user();

        // Con autenticación por token real hay un PersonalAccessToken que revocar;
        // con sesión de cookie (o `actingAs` en pruebas) Sanctum entrega un
        // TransientToken, que no es persistente y por tanto no se borra.
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        $audit->record(
            action: 'auth.logout',
            actorId: $user->id,
            subjectType: User::class,
            subjectId: $user->id,
            request: $request,
        );

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        AuditLogger::getInstance()->record(
            action: 'auth.session.read',
            actorId: $user->id,
            subjectType: User::class,
            subjectId: $user->id,
            request: $request,
        );

        return response()->json($user->only(['id', 'name', 'email']));
    }
}
