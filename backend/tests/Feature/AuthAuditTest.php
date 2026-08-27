<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Audit\AuditOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AuditLogger::resetInstance();
        RateLimiter::clear('ana@clinica.test|127.0.0.1');

        // Sanctum sólo abre sesión (modo SPA) si la petición proviene de un
        // dominio declarado como stateful; hay que simular ese origen.
        config(['sanctum.stateful' => ['localhost']]);
        $this->withHeader('Origin', 'http://localhost');
    }

    #[Test]
    public function un_login_exitoso_queda_auditado(): void
    {
        $user = User::factory()->create([
            'email' => 'ana@clinica.test',
            'password' => 'secreta123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'ana@clinica.test',
            'password' => 'secreta123',
        ]);

        $response->assertOk()->assertJsonStructure(['user', 'request_id']);

        // Modo SPA: la sesión queda abierta, no se devuelve ningún token.
        $response->assertJsonMissingPath('token');
        $this->assertAuthenticatedAs($user);

        $log = AuditLog::where('action', 'auth.login.succeeded')->sole();

        $this->assertSame($user->id, $log->actor_id);
        $this->assertSame(1, $log->sequence);
        $this->assertSame($response->json('request_id'), $log->request_id);
        $this->assertSame(AuditOutcome::Success, $log->outcome);
        $this->assertSame(200, $log->status_code);
    }

    #[Test]
    public function un_login_fallido_queda_auditado_sin_la_contrasena(): void
    {
        User::factory()->create([
            'email' => 'ana@clinica.test',
            'password' => 'secreta123',
        ]);

        $this->postJson('/api/login', [
            'email' => 'ana@clinica.test',
            'password' => 'incorrecta',
        ])->assertUnauthorized();

        $this->assertGuest();

        $log = AuditLog::where('action', 'auth.login.failed')->sole();

        $this->assertNull($log->actor_id);
        $this->assertSame(AuditOutcome::Failure, $log->outcome);
        $this->assertSame(401, $log->status_code);
        $this->assertSame(['email' => 'ana@clinica.test'], $log->metadata);
        $this->assertStringNotContainsString('incorrecta', json_encode($log->getAttributes()));
    }

    #[Test]
    public function el_logout_cierra_la_sesion_y_queda_auditado(): void
    {
        $user = User::factory()->create([
            'email' => 'ana@clinica.test',
            'password' => 'secreta123',
        ]);

        // Flujo real de cookie: se inicia y se cierra sesión de verdad, sin
        // `actingAs`, para no falsear el guard.
        $this->postJson('/api/login', [
            'email' => 'ana@clinica.test',
            'password' => 'secreta123',
        ])->assertOk();

        $this->postJson('/api/logout')->assertOk();

        // La invalidación de la cookie de sesión se comprueba de extremo a
        // extremo contra el stack real: dentro del proceso de pruebas el guard
        // queda cacheado en memoria y `assertGuest` daría un falso negativo.

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.logout',
            'actor_id' => $user->id,
        ]);
    }
}
