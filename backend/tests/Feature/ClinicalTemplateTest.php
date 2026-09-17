<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalTemplate;
use App\Models\DeviceReading;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Iot\Readings\ReadingSeverity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClinicalTemplateTest extends TestCase
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
    }

    #[Test]
    public function lista_el_catalogo_de_plantillas_institucionales(): void
    {
        $response = $this->getJson('/api/encounter-templates');

        $response->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.key', 'hta_control')
            ->assertJsonPath('data.0.encounter_type', 'outpatient_control')
            ->assertJsonPath('data.0.built_in', true)
            ->assertJsonPath('data.0.diagnoses.0.code', 'I10');
    }

    #[Test]
    public function el_borrador_llega_listo_para_registrar_la_atencion(): void
    {
        $response = $this->getJson('/api/encounter-templates/hta_control/draft');

        $response->assertOk()
            ->assertJsonPath('data.template.name', 'Control de hipertensión arterial')
            ->assertJsonPath('data.payload.encounter_type', 'outpatient_control')
            ->assertJsonPath('data.payload.diagnoses.0.code', 'I10')
            ->assertJsonPath('data.payload.prescriptions.0.dose', '50 mg');
    }

    #[Test]
    public function el_borrador_nunca_trae_datos_de_ningun_paciente(): void
    {
        $payload = $this->getJson('/api/encounter-templates/hta_control/draft')->json('data.payload');

        // Una plantilla es una estructura, no la historia de nadie.
        $this->assertArrayNotHasKey('patient_id', $payload);
        $this->assertArrayNotHasKey('present_illness', $payload);
        $this->assertArrayNotHasKey('history', $payload);
        $this->assertArrayNotHasKey('physical_exam', $payload);
    }

    #[Test]
    public function pedir_el_borrador_dos_veces_da_copias_independientes(): void
    {
        $primera = $this->getJson('/api/encounter-templates/hta_control/draft')->json('data.payload');
        $segunda = $this->getJson('/api/encounter-templates/hta_control/draft')->json('data.payload');

        $this->assertSame($primera['prescriptions'][0]['dose'], $segunda['prescriptions'][0]['dose']);
        $this->assertSame('50 mg', $segunda['prescriptions'][0]['dose']);
    }

    #[Test]
    public function una_plantilla_inexistente_se_rechaza(): void
    {
        $this->getJson('/api/encounter-templates/no_existe/draft')
            ->assertStatus(422)
            ->assertJsonValidationErrors('template');
    }

    #[Test]
    public function la_plantilla_rellena_los_huecos_de_la_nota(): void
    {
        // El profesional sólo aporta lo que es de este paciente; diagnóstico,
        // plan y medicación salen de la copia del prototipo.
        $response = $this->postJson('/api/clinical-encounters', [
            'template' => 'hta_control',
            'patient_id' => $this->patient->id,
            'professional_license' => 'RM-12345',
            'present_illness' => 'Paciente asintomática, adherente al tratamiento.',
            'history' => 'Hipertensa desde 2019, sin otras comorbilidades.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'outpatient_control')
            ->assertJsonPath('data.chief_complaint', 'Control de hipertensión arterial')
            ->assertJsonPath('data.diagnoses.0.code', 'I10')
            ->assertJsonPath('data.prescriptions.0.active_ingredient', 'Losartán')
            ->assertJsonPath('data.prescriptions.0.dose', '50 mg');

        $this->assertNotNull($response->json('data.follow_up_at'));
    }

    #[Test]
    public function lo_que_envia_el_profesional_gana_sobre_la_plantilla(): void
    {
        // La plantilla es un punto de partida, nunca una imposición: aquí se
        // ajusta la dosis al paciente y el diagnóstico al hallazgo real.
        $response = $this->postJson('/api/clinical-encounters', [
            'template' => 'hta_control',
            'patient_id' => $this->patient->id,
            'professional_license' => 'RM-12345',
            'present_illness' => 'Cifras tensionales persistentemente elevadas pese al tratamiento.',
            'history' => 'Hipertensa desde 2019, mala adherencia referida.',
            'chief_complaint' => 'Hipertensión no controlada',
            'diagnoses' => [['code' => 'I10', 'description' => 'Hipertensión esencial no controlada', 'primary' => true]],
            'prescriptions' => [[
                'active_ingredient' => 'Losartán',
                'dose' => '100 mg',
                'frequency' => 'cada 24 horas',
                'duration_days' => 90,
            ]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.chief_complaint', 'Hipertensión no controlada')
            ->assertJsonPath('data.prescriptions.0.dose', '100 mg');
    }

    #[Test]
    public function ajustar_la_dosis_en_una_atencion_no_contamina_la_siguiente(): void
    {
        // La prueba de fuego del patrón vista desde la API: la plantilla del
        // catálogo tiene que seguir intacta después de usarse.
        $this->postJson('/api/clinical-encounters', [
            'template' => 'hta_control',
            'patient_id' => $this->patient->id,
            'professional_license' => 'RM-12345',
            'present_illness' => 'Cifras elevadas, se ajusta dosis.',
            'history' => 'Hipertensa desde 2019.',
            'prescriptions' => [[
                'active_ingredient' => 'Losartán',
                'dose' => '100 mg',
                'frequency' => 'cada 24 horas',
                'duration_days' => 90,
            ]],
        ])->assertCreated();

        $this->getJson('/api/encounter-templates/hta_control/draft')
            ->assertOk()
            ->assertJsonPath('data.payload.prescriptions.0.dose', '50 mg');
    }

    #[Test]
    public function la_plantilla_de_urgencias_respeta_lo_que_exige_el_builder(): void
    {
        // El Prototype no debilita al Builder: la urgencia sigue necesitando
        // triaje y signos vitales aunque venga de una plantilla.
        $this->postJson('/api/clinical-encounters', [
            'template' => 'crisis_hipertensiva',
            'patient_id' => $this->patient->id,
            'professional_license' => 'RM-12345',
            'present_illness' => 'Cefalea occipital de dos horas de evolución.',
            'physical_exam' => 'Paciente álgida, sin focalización neurológica.',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('triage');
    }

    #[Test]
    public function guarda_una_plantilla_nueva_a_partir_de_una_nota_existente(): void
    {
        $encounter = $this->registrarControl();

        $response = $this->postJson('/api/encounter-templates', [
            'encounter_id' => $encounter->id,
            'key' => 'control_hta_helena',
            'name' => 'Control HTA — protocolo Dra. Ruiz',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.key', 'control_hta_helena')
            ->assertJsonPath('data.built_in', false)
            ->assertJsonPath('data.origin_encounter_id', $encounter->id)
            ->assertJsonPath('data.diagnoses.0.code', 'I10')
            ->assertJsonPath('data.prescriptions.0.active_ingredient', 'Losartán');

        $this->assertDatabaseHas('clinical_templates', [
            'key' => 'control_hta_helena',
            'author_id' => $this->professional->id,
        ]);
    }

    #[Test]
    public function la_plantilla_guardada_no_arrastra_la_historia_del_paciente(): void
    {
        // «Copy-forward»: el error de llevarse el relato de un paciente a la
        // atención de otro. La sanitización al clonar es lo que lo impide.
        $encounter = $this->registrarControl();

        $this->postJson('/api/encounter-templates', [
            'encounter_id' => $encounter->id,
            'key' => 'control_hta_helena',
            'name' => 'Control HTA — protocolo Dra. Ruiz',
        ])->assertCreated();

        $guardada = ClinicalTemplate::where('key', 'control_hta_helena')->firstOrFail();
        $json = json_encode($guardada->toArray(), JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString('asintomática', $json);
        $this->assertStringNotContainsString('Hipertensa desde 2019', $json);
        $this->assertStringNotContainsString('Ruidos cardiacos', $json);
        $this->assertNull($guardada->metadata['patient_id'] ?? null);
    }

    #[Test]
    public function la_plantilla_guardada_aparece_en_el_catalogo_y_se_puede_usar(): void
    {
        $encounter = $this->registrarControl();

        $this->postJson('/api/encounter-templates', [
            'encounter_id' => $encounter->id,
            'key' => 'control_hta_helena',
            'name' => 'Control HTA — protocolo Dra. Ruiz',
        ])->assertCreated();

        $this->getJson('/api/encounter-templates')->assertOk()->assertJsonCount(5, 'data');

        $this->postJson('/api/clinical-encounters', [
            'template' => 'control_hta_helena',
            'patient_id' => $this->patient->id,
            'professional_license' => 'RM-12345',
            'present_illness' => 'Segunda paciente, control rutinario.',
            'history' => 'Sin antecedentes de importancia.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.diagnoses.0.code', 'I10');
    }

    #[Test]
    public function no_se_admiten_dos_plantillas_con_la_misma_clave(): void
    {
        $encounter = $this->registrarControl();

        $datos = [
            'encounter_id' => $encounter->id,
            'key' => 'control_hta_helena',
            'name' => 'Control HTA — protocolo Dra. Ruiz',
        ];

        $this->postJson('/api/encounter-templates', $datos)->assertCreated();
        $this->postJson('/api/encounter-templates', $datos)
            ->assertStatus(422)
            ->assertJsonValidationErrors('key');
    }

    #[Test]
    public function guardar_y_aplicar_una_plantilla_quedan_auditados(): void
    {
        $encounter = $this->registrarControl();

        $this->postJson('/api/encounter-templates', [
            'encounter_id' => $encounter->id,
            'key' => 'control_hta_helena',
            'name' => 'Control HTA — protocolo Dra. Ruiz',
        ])->assertCreated();

        $this->getJson('/api/encounter-templates/hta_control/draft')->assertOk();

        $guardado = AuditLog::where('action', 'hce.template.saved')->firstOrFail();
        $this->assertSame($this->professional->id, $guardado->actor_id);
        $this->assertSame('control_hta_helena', $guardado->metadata['template']);
        $this->assertContains('present_illness', $guardado->metadata['omitted_sections']);

        $aplicado = AuditLog::where('action', 'hce.template.applied')->firstOrFail();
        $this->assertSame('hta_control', $aplicado->metadata['template']);
    }

    /** Registra un control ambulatorio real del que después sale la plantilla. */
    private function registrarControl(): ClinicalEncounter
    {
        DeviceReading::create([
            'device_type' => 'sphygmomanometer',
            'loinc_code' => '85354-9',
            'display' => 'Presión arterial',
            'value' => 138,
            'unit' => 'mm[Hg]',
            'severity' => ReadingSeverity::Normal,
            'components' => ['diastolic' => 86],
            'patient_id' => $this->patient->id,
            'measured_at' => now()->subDay(),
        ]);

        $this->postJson('/api/clinical-encounters', [
            'encounter_type' => 'outpatient_control',
            'patient_id' => $this->patient->id,
            'professional_license' => 'RM-12345',
            'chief_complaint' => 'Control de hipertensión arterial',
            'present_illness' => 'Paciente asintomática, adherente al tratamiento.',
            'history' => 'Hipertensa desde 2019, sin otras comorbilidades.',
            'physical_exam' => 'Ruidos cardiacos rítmicos, sin soplos.',
            'follow_up_at' => now()->addMonths(3)->toDateString(),
            'diagnoses' => [['code' => 'I10', 'description' => 'Hipertensión esencial', 'primary' => true]],
            'treatment_plan' => 'Continuar losartán, dieta hiposódica y actividad física.',
            'prescriptions' => [[
                'active_ingredient' => 'Losartán',
                'dose' => '50 mg',
                'frequency' => 'cada 24 horas',
                'duration_days' => 90,
            ]],
        ])->assertCreated();

        return ClinicalEncounter::latest('id')->firstOrFail();
    }
}
