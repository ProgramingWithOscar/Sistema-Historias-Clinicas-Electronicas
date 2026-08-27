<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit\AuditLogger;
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

        $response->assertOk()->assertJsonStructure(['token', 'user', 'request_id']);

        $log = AuditLog::where('action', 'auth.login.succeeded')->sole();

        $this->assertSame($user->id, $log->actor_id);
        $this->assertSame(1, $log->sequence);
        $this->assertSame($response->json('request_id'), $log->request_id);
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

        $log = AuditLog::where('action', 'auth.login.failed')->sole();

        $this->assertNull($log->actor_id);
        $this->assertSame(['email' => 'ana@clinica.test'], $log->metadata);
        $this->assertStringNotContainsString('incorrecta', json_encode($log->getAttributes()));
    }

    #[Test]
    public function el_logout_queda_auditado(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/logout')
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.logout',
            'actor_id' => $user->id,
        ]);
    }
}
