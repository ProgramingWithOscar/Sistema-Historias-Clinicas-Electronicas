<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DeviceReading;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeviceReadingIngestionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        AuditLogger::resetInstance();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    #[Test]
    public function ingesta_una_lectura_de_glucometro(): void
    {
        $response = $this->postJson('/api/device-readings', [
            'device_type' => 'glucometer',
            'payload' => ['mg_dl' => 145, 'fasting' => true],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.loinc_code', '1558-6')
            ->assertJsonPath('data.unit', 'mg/dL')
            ->assertJsonPath('data.severity', 'warning')
            ->assertJsonPath('data.requires_attention', true)
            ->assertJsonPath('data.components.fasting', true);

        $this->assertDatabaseHas('device_readings', [
            'device_type' => 'glucometer',
            'value' => 145,
            'patient_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function ingesta_una_lectura_de_tensiometro_con_dos_componentes(): void
    {
        $response = $this->postJson('/api/device-readings', [
            'device_type' => 'sphygmomanometer',
            'payload' => ['systolic' => 190, 'diastolic' => 125, 'pulse' => 92],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.loinc_code', '85354-9')
            ->assertJsonPath('data.value', 190)
            ->assertJsonPath('data.severity', 'critical')
            ->assertJsonPath('data.components.diastolic', 125)
            ->assertJsonPath('data.components.pulse', 92);
    }

    #[Test]
    public function ingesta_una_lectura_de_oximetro(): void
    {
        $this->postJson('/api/device-readings', [
            'device_type' => 'pulse_oximeter',
            'payload' => ['spo2' => 88, 'pulse' => 110],
        ])->assertCreated()
            ->assertJsonPath('data.loinc_code', '59408-5')
            ->assertJsonPath('data.severity', 'critical');
    }

    #[Test]
    public function cada_dispositivo_produce_una_lectura_distinta_por_la_misma_ruta(): void
    {
        // La prueba central del patrón: un único endpoint, un único
        // controlador, tres productos distintos según el `device_type`.
        $this->postJson('/api/device-readings', [
            'device_type' => 'glucometer',
            'payload' => ['mg_dl' => 95],
        ])->assertCreated();

        $this->postJson('/api/device-readings', [
            'device_type' => 'sphygmomanometer',
            'payload' => ['systolic' => 120, 'diastolic' => 80],
        ])->assertCreated();

        $this->postJson('/api/device-readings', [
            'device_type' => 'pulse_oximeter',
            'payload' => ['spo2' => 97],
        ])->assertCreated();

        $this->assertSame(
            ['2339-0', '85354-9', '59408-5'],
            DeviceReading::orderBy('id')->pluck('loinc_code')->all()
        );
    }

    #[Test]
    public function rechaza_un_dispositivo_no_soportado(): void
    {
        $this->postJson('/api/device-readings', [
            'device_type' => 'tostadora',
            'payload' => ['grados' => 200],
        ])->assertStatus(422)->assertJsonValidationErrors('device_type');

        $this->assertDatabaseCount('device_readings', 0);
    }

    #[Test]
    public function cada_fabrica_valida_el_payload_de_su_propio_dispositivo(): void
    {
        // La diastólica no puede superar a la sistólica: una regla que sólo
        // tiene sentido para el tensiómetro y por eso vive en su fábrica.
        $this->postJson('/api/device-readings', [
            'device_type' => 'sphygmomanometer',
            'payload' => ['systolic' => 80, 'diastolic' => 120],
        ])->assertStatus(422)->assertJsonValidationErrors('diastolic');

        $this->postJson('/api/device-readings', [
            'device_type' => 'glucometer',
            'payload' => ['mg_dl' => 5000],
        ])->assertStatus(422)->assertJsonValidationErrors('mg_dl');
    }

    #[Test]
    public function toda_ingesta_queda_auditada_por_el_singleton(): void
    {
        $this->postJson('/api/device-readings', [
            'device_type' => 'glucometer',
            'payload' => ['mg_dl' => 95],
        ])->assertCreated();

        $log = AuditLog::where('action', 'iot.reading.ingested')->sole();

        $this->assertSame(DeviceReading::class, $log->subject_type);
        $this->assertSame(DeviceReading::sole()->id, $log->subject_id);
        $this->assertSame('glucometer', $log->metadata['device_type']);
        $this->assertSame('normal', $log->metadata['severity']);
    }

    #[Test]
    public function lista_las_lecturas_y_los_dispositivos_soportados(): void
    {
        $this->postJson('/api/device-readings', [
            'device_type' => 'glucometer',
            'payload' => ['mg_dl' => 95],
        ])->assertCreated();

        $this->getJson('/api/device-readings')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/devices')
            ->assertOk()
            ->assertJsonPath('data', ['glucometer', 'sphygmomanometer', 'pulse_oximeter']);
    }

    #[Test]
    public function la_ingesta_exige_autenticacion(): void
    {
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/device-readings', [
            'device_type' => 'glucometer',
            'payload' => ['mg_dl' => 95],
        ])->assertUnauthorized();
    }
}
