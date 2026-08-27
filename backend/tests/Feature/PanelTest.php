<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Audit\AuditOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AuditLogger::resetInstance();

        config(['sanctum.stateful' => ['localhost']]);
        $this->withHeader('Origin', 'http://localhost');
    }

    #[Test]
    public function las_sesiones_y_la_auditoria_exigen_autenticacion(): void
    {
        $this->getJson('/api/sessions')->assertUnauthorized();
        $this->getJson('/api/audit-logs')->assertUnauthorized();
    }

    #[Test]
    public function lista_las_sesiones_del_usuario_y_marca_la_actual(): void
    {
        $user = User::factory()->create();

        DB::table('sessions')->insert([
            ['id' => 'sesion-vieja', 'user_id' => $user->id, 'ip_address' => '10.0.0.9',
                'user_agent' => 'Mozilla/5.0 Firefox/130.0', 'payload' => '', 'last_activity' => now()->subHour()->timestamp],
            ['id' => 'de-otro-usuario', 'user_id' => $user->id + 99, 'ip_address' => '10.0.0.8',
                'user_agent' => 'curl', 'payload' => '', 'last_activity' => now()->timestamp],
        ]);

        $response = $this->actingAs($user)->getJson('/api/sessions')->assertOk();

        // Sólo se ven las sesiones propias, nunca las de otro usuario.
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.ip_address', '10.0.0.9');
        $response->assertJsonPath('data.0.is_current', false);
    }

    #[Test]
    public function la_auditoria_solo_muestra_los_eventos_propios(): void
    {
        $user = User::factory()->create();
        $otro = User::factory()->create();

        $logger = AuditLogger::getInstance();
        $logger->record(action: 'auth.login.succeeded', actorId: $user->id);
        $logger->record(action: 'auth.login.succeeded', actorId: $otro->id);

        $response = $this->actingAs($user)->getJson('/api/audit-logs')->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.action', 'auth.login.succeeded');
        $response->assertJsonPath('data.0.outcome', 'success');
    }

    #[Test]
    public function muestra_los_intentos_fallidos_contra_el_propio_correo(): void
    {
        $user = User::factory()->create(['email' => 'ana@clinica.test']);

        $logger = AuditLogger::getInstance();

        // Sin actor_id: nadie estaba autenticado cuando ocurrió el intento.
        $logger->record(
            action: 'auth.login.failed',
            outcome: AuditOutcome::Failure,
            statusCode: 401,
            metadata: ['email' => 'ana@clinica.test'],
        );

        // Intento contra otra cuenta: no debe filtrarse a esta.
        $logger->record(
            action: 'auth.login.failed',
            outcome: AuditOutcome::Failure,
            statusCode: 401,
            metadata: ['email' => 'otro@clinica.test'],
        );

        $response = $this->actingAs($user)->getJson('/api/audit-logs')->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.action', 'auth.login.failed');
        $response->assertJsonPath('data.0.outcome', 'failure');
        $response->assertJsonPath('data.0.outcome_label', 'Fallida');
        $response->assertJsonPath('data.0.status_code', 401);
    }
}
