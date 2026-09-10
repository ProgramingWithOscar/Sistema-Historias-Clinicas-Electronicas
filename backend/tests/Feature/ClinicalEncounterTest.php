<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DeviceReading;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Encounters\Directors\EmergencyEncounterDirector;
use App\Support\Encounters\Exceptions\IncompleteClinicalNoteException;
use App\Support\Iot\Readings\ReadingSeverity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClinicalEncounterTest extends TestCase
{
    use RefreshDatabase;

    private User $professional;

    private User $patient;

    protected function setUp(): void
    {
        parent::setUp();
        AuditLogger::resetInstance();

        $this->professional = User::factory()->create(['name' => 'Dra. Helena Ruiz']);
        $this->patient = User::factory()->create(['name' => 'Ana María Rodríguez']);

        $this->actingAs($this->professional);

        DeviceReading::create([
            'device_type' => 'sphygmomanometer',
            'loinc_code' => '85354-9',
            'display' => 'Presión arterial',
            'value' => 190,
            'unit' => 'mm[Hg]',
            'severity' => ReadingSeverity::Critical,
            'components' => ['diastolic' => 125],
            'patient_id' => $this->patient->id,
            'measured_at' => now()->subMinutes(10),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(string $type, array $overrides = []): array
    {
        return [
            'encounter_type' => $type,
            'patient_id' => $this->patient->id,
            'professional_license' => 'RM-12345',
            'chief_complaint' => 'Cefalea intensa y visión borrosa',
            'present_illness' => 'Cuadro de tres horas de evolución, sin trauma previo.',
            'diagnoses' => [
                ['code' => 'I10', 'description' => 'Hipertensión esencial', 'primary' => true],
            ],
            'treatment_plan' => 'Antihipertensivo endovenoso y monitorización continua.',
            ...$overrides,
        ];
    }

    #[Test]
    public function registra_una_nota_de_urgencias_con_triaje_y_signos_vitales(): void
    {
        $response = $this->postJson('/api/clinical-encounters', $this->payload('emergency', [
            'triage' => 'II',
            'physical_exam' => 'Paciente álgida, TA 190/125, sin focalización neurológica.',
            'service' => 'Urgencias adultos',
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.type', 'emergency')
            ->assertJsonPath('data.triage', 'II')
            ->assertJsonPath('data.triage_label', 'II - Atención en menos de 30 minutos')
            ->assertJsonPath('data.metadata.max_wait_minutes', 30)
            ->assertJsonPath('data.diagnoses.0.code', 'I10')
            ->assertJsonPath('data.diagnoses.0.primary', true);

        // Los signos vitales los aporta el director desde las lecturas que ya
        // normalizó el Factory Method: no se transcriben a mano.
        $response->assertJsonPath('data.vital_signs.0.loinc_code', '85354-9')
            ->assertJsonPath('data.vital_signs.0.severity', 'critical');

        $this->assertDatabaseHas('clinical_encounters', [
            'type' => 'emergency',
            'patient_id' => $this->patient->id,
            'professional_id' => $this->professional->id,
            'triage' => 'II',
        ]);
    }

    #[Test]
    public function registra_un_control_ambulatorio_con_antecedentes_y_proxima_cita(): void
    {
        $response = $this->postJson('/api/clinical-encounters', $this->payload('outpatient_control', [
            'history' => 'Hipertensa desde 2019, en manejo con losartán 50 mg día.',
            'follow_up_at' => now()->addMonths(3)->toDateString(),
            'program' => 'Riesgo cardiovascular',
            'prescriptions' => [[
                'active_ingredient' => 'Losartán',
                'dose' => '50 mg',
                'frequency' => 'cada 24 horas',
                'duration_days' => 90,
            ]],
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.type', 'outpatient_control')
            ->assertJsonPath('data.metadata.program', 'Riesgo cardiovascular')
            ->assertJsonPath('data.prescriptions.0.active_ingredient', 'Losartán')
            ->assertJsonPath('data.triage', null);

        $this->assertNotNull($response->json('data.follow_up_at'));
    }

    #[Test]
    public function el_control_sin_antecedentes_se_rechaza(): void
    {
        $this->postJson('/api/clinical-encounters', $this->payload('outpatient_control', [
            'follow_up_at' => now()->addMonths(3)->toDateString(),
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('history');
    }

    #[Test]
    public function registra_una_teleconsulta_sin_examen_fisico(): void
    {
        $response = $this->postJson('/api/clinical-encounters', $this->payload('teleconsultation', [
            'consent' => true,
            'channel' => 'Videollamada institucional',
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.type', 'teleconsultation')
            ->assertJsonPath('data.physical_exam', null)
            ->assertJsonPath('data.metadata.consent', true)
            ->assertJsonPath('data.metadata.legal_basis', 'Resolución 2654 de 2019');
    }

    #[Test]
    public function la_teleconsulta_rechaza_el_examen_fisico(): void
    {
        // La regla no se ignora en silencio: quien lo envía se entera.
        $this->postJson('/api/clinical-encounters', $this->payload('teleconsultation', [
            'consent' => true,
            'channel' => 'Videollamada institucional',
            'physical_exam' => 'Ruidos cardiacos rítmicos, sin soplos.',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('physical_exam');
    }

    #[Test]
    public function la_teleconsulta_sin_consentimiento_se_rechaza(): void
    {
        $this->postJson('/api/clinical-encounters', $this->payload('teleconsultation', [
            'channel' => 'Videollamada institucional',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('consent');
    }

    #[Test]
    public function una_nota_sin_diagnostico_se_rechaza(): void
    {
        $payload = $this->payload('teleconsultation', [
            'consent' => true,
            'channel' => 'Videollamada institucional',
        ]);
        unset($payload['diagnoses']);

        $this->postJson('/api/clinical-encounters', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('diagnoses');
    }

    #[Test]
    public function el_diagnostico_debe_usar_un_codigo_cie10_valido(): void
    {
        $this->postJson('/api/clinical-encounters', $this->payload('teleconsultation', [
            'consent' => true,
            'channel' => 'Videollamada institucional',
            'diagnoses' => [['code' => 'dolor de cabeza', 'description' => 'Cefalea']],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('diagnoses.0.code');
    }

    #[Test]
    public function el_tipo_de_atencion_debe_estar_soportado(): void
    {
        $this->postJson('/api/clinical-encounters', $this->payload('cirugia_estetica'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('encounter_type');
    }

    #[Test]
    public function una_urgencia_sin_signos_vitales_no_llega_a_persistirse(): void
    {
        // Paciente sin ningún dispositivo conectado: el director no encuentra
        // lecturas y `build()` aborta antes de tocar la base de datos.
        $sinLecturas = User::factory()->create();

        $this->postJson('/api/clinical-encounters', $this->payload('emergency', [
            'patient_id' => $sinLecturas->id,
            'triage' => 'III',
            'physical_exam' => 'Paciente estable, sin signos de alarma evidentes.',
        ]))
            ->assertStatus(422)
            ->assertJsonPath('errors.note.0', 'signos vitales');

        $this->assertDatabaseCount('clinical_encounters', 0);
    }

    #[Test]
    public function el_director_no_revienta_ante_un_payload_incompleto(): void
    {
        // El director no valida: si una sección no viene, simplemente no da ese
        // paso. Quien decide —y quien informa de TODO lo que falta a la vez— es
        // `build()`. Sin esto, un payload sin triaje moría con un ValueError y
        // el usuario nunca se enteraba de qué otras secciones faltaban.
        try {
            (new EmergencyEncounterDirector)->construct([], $this->patient, $this->professional);
            $this->fail('Debió lanzarse la excepción de nota incompleta.');
        } catch (IncompleteClinicalNoteException $e) {
            $this->assertContains('clasificación de triaje', $e->missing);
            $this->assertContains('motivo de consulta', $e->missing);
            $this->assertContains('registro profesional', $e->missing);
            $this->assertContains('plan de manejo', $e->missing);
            $this->assertContains('diagnóstico', $e->missing);
        }
    }

    #[Test]
    public function cada_nota_queda_auditada(): void
    {
        $this->postJson('/api/clinical-encounters', $this->payload('emergency', [
            'triage' => 'I',
            'physical_exam' => 'Paciente en mal estado general, requiere atención inmediata.',
        ]))->assertCreated();

        $evento = AuditLog::where('action', 'hce.encounter.created')->firstOrFail();

        $this->assertSame($this->professional->id, $evento->actor_id);
        $this->assertSame('emergency', $evento->metadata['encounter_type']);
        $this->assertSame($this->patient->id, $evento->metadata['patient_id']);
        $this->assertSame('I10', $evento->metadata['primary_diagnosis']);
    }

    #[Test]
    public function sin_patient_id_la_nota_se_atribuye_al_usuario_autenticado(): void
    {
        $payload = $this->payload('teleconsultation', [
            'consent' => true,
            'channel' => 'Videollamada institucional',
        ]);
        unset($payload['patient_id']);

        $this->postJson('/api/clinical-encounters', $payload)
            ->assertCreated()
            ->assertJsonPath('data.patient_id', $this->professional->id);
    }

    #[Test]
    public function lista_los_tipos_de_atencion_con_lo_que_exige_cada_uno(): void
    {
        $this->getJson('/api/encounter-types')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.encounter_type', 'emergency')
            ->assertJsonPath('data.0.required_sections.0', 'Triaje')
            ->assertJsonPath('data.2.in_person', false);
    }

    #[Test]
    public function lista_las_notas_registradas(): void
    {
        $this->postJson('/api/clinical-encounters', $this->payload('teleconsultation', [
            'consent' => true,
            'channel' => 'Videollamada institucional',
        ]))->assertCreated();

        $this->getJson('/api/clinical-encounters')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type_label', 'Teleconsulta');
    }
}
