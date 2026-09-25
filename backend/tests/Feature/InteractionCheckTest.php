<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InteractionCheckTest extends TestCase
{
    use RefreshDatabase;

    private User $professional;

    protected function setUp(): void
    {
        parent::setUp();
        AuditLogger::resetInstance();

        $this->professional = User::factory()->create(['name' => 'Dra. Helena Ruiz']);
        $this->actingAs($this->professional);
    }

    #[Test]
    public function detecta_una_interaccion_grave_en_la_formula(): void
    {
        $response = $this->postJson('/api/interaction-checks', [
            'drugs' => ['Losartán', 'Espironolactona'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.source', 'vademecum_nacional')
            ->assertJsonPath('data.has_warnings', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.most_severe', 'grave')
            ->assertJsonPath('data.interactions.0.severity_label', 'Grave')
            ->assertJsonPath('data.interactions.0.requires_attention', true);
    }

    #[Test]
    public function una_formula_segura_no_genera_alertas(): void
    {
        $this->postJson('/api/interaction-checks', [
            'drugs' => ['Losartán', 'Acetaminofén'],
        ])
            ->assertOk()
            ->assertJsonPath('data.has_warnings', false)
            ->assertJsonPath('data.total', 0);
    }

    #[Test]
    public function la_interaccion_leve_se_reporta_pero_no_alerta(): void
    {
        // Acetaminofén + ibuprofeno es una asociación aceptada: se documenta,
        // pero no debe frenar al profesional.
        $response = $this->postJson('/api/interaction-checks', [
            'drugs' => ['Acetaminofén', 'Ibuprofeno'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.interactions.0.severity', 'leve')
            ->assertJsonPath('data.interactions.0.requires_attention', false)
            ->assertJsonPath('data.has_warnings', false);
    }

    #[Test]
    public function detecta_una_combinacion_contraindicada(): void
    {
        $this->postJson('/api/interaction-checks', [
            'drugs' => ['Sildenafil', 'Nitroglicerina'],
        ])
            ->assertOk()
            ->assertJsonPath('data.most_severe', 'contraindicada')
            ->assertJsonPath('data.interactions.0.severity_label', 'Contraindicada');
    }

    #[Test]
    public function ordena_las_interacciones_de_mas_grave_a_menos(): void
    {
        $response = $this->postJson('/api/interaction-checks', [
            'drugs' => ['Warfarina', 'Ibuprofeno', 'Acetaminofén'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.interactions.0.severity', 'grave')
            ->assertJsonPath('data.interactions.2.severity', 'leve');
    }

    #[Test]
    public function la_misma_peticion_funciona_contra_la_fuente_externa(): void
    {
        // EL PUNTO DEL PATRÓN: cambia la fuente, no cambia nada más.
        Http::fake([
            'rxnav.nlm.nih.gov/*' => Http::response([
                'fullInteractionTypeGroup' => [[
                    'fullInteractionType' => [[
                        'interactionPair' => [[
                            'severity' => 'high',
                            'description' => 'The risk or severity of renal failure can be increased.',
                            'interactionConcept' => [
                                ['minConceptItem' => ['rxcui' => '52175', 'name' => 'losartan']],
                                ['minConceptItem' => ['rxcui' => '5640', 'name' => 'ibuprofen']],
                            ],
                        ]],
                    ]],
                ]],
            ]),
        ]);

        $this->postJson('/api/interaction-checks', [
            'drugs' => ['Losartán', 'Ibuprofeno'],
            'source' => 'rxnav',
        ])
            ->assertOk()
            ->assertJsonPath('data.source', 'rxnav_nlm')
            ->assertJsonPath('data.most_severe', 'grave')
            // El nombre que ve el médico es el que él escribió, no el del API.
            ->assertJsonPath('data.interactions.0.drug_a', 'Losartán');
    }

    #[Test]
    public function la_caida_del_servicio_externo_no_devuelve_error_al_profesional(): void
    {
        Http::fake(['rxnav.nlm.nih.gov/*' => Http::response('', 503)]);

        $this->postJson('/api/interaction-checks', [
            'drugs' => ['Losartán', 'Ibuprofeno'],
            'source' => 'rxnav',
        ])
            ->assertOk()
            ->assertJsonPath('data.total', 0);
    }

    #[Test]
    public function exige_al_menos_dos_principios_activos(): void
    {
        $this->postJson('/api/interaction-checks', ['drugs' => ['Losartán']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('drugs');
    }

    #[Test]
    public function rechaza_una_fuente_no_soportada(): void
    {
        $this->postJson('/api/interaction-checks', [
            'drugs' => ['Losartán', 'Ibuprofeno'],
            'source' => 'el_vecino_farmaceuta',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('source');
    }

    #[Test]
    public function cada_verificacion_queda_auditada(): void
    {
        $this->postJson('/api/interaction-checks', [
            'drugs' => ['Losartán', 'Espironolactona'],
        ])->assertOk();

        $evento = AuditLog::where('action', 'hce.interaction.checked')->firstOrFail();

        $this->assertSame($this->professional->id, $evento->actor_id);
        $this->assertSame('vademecum_nacional', $evento->metadata['source']);
        $this->assertSame('grave', $evento->metadata['most_severe']);
        $this->assertSame(1, $evento->metadata['found']);
    }

    #[Test]
    public function la_nota_de_atencion_verifica_su_propia_formula(): void
    {
        // Integración real: al registrar una atención con dos medicamentos que
        // interactúan, la respuesta trae la alerta junto con la nota.
        $paciente = User::factory()->create();

        $response = $this->postJson('/api/clinical-encounters', [
            'encounter_type' => 'teleconsultation',
            'patient_id' => $paciente->id,
            'consent' => true,
            'channel' => 'Videollamada institucional',
            'professional_license' => 'RM-12345',
            'chief_complaint' => 'Control de hipertensión',
            'present_illness' => 'Paciente con cifras tensionales elevadas y dolor articular.',
            'diagnoses' => [['code' => 'I10', 'description' => 'Hipertensión esencial', 'primary' => true]],
            'treatment_plan' => 'Antihipertensivo y analgesia según necesidad.',
            'prescriptions' => [
                ['active_ingredient' => 'Losartán', 'dose' => '50 mg', 'frequency' => 'cada 24 horas', 'duration_days' => 30],
                ['active_ingredient' => 'Ibuprofeno', 'dose' => '400 mg', 'frequency' => 'cada 8 horas', 'duration_days' => 5],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.interactions.has_warnings', true)
            ->assertJsonPath('data.interactions.most_severe', 'moderada')
            ->assertJsonPath('data.interactions.source', 'vademecum_nacional');

        // La nota SÍ queda guardada: la alerta informa, no bloquea.
        $this->assertDatabaseCount('clinical_encounters', 1);

        $evento = AuditLog::where('action', 'hce.encounter.created')->firstOrFail();
        $this->assertSame('moderada', $evento->metadata['interaction_warning']);
    }

    #[Test]
    public function una_nota_con_un_solo_medicamento_no_se_verifica(): void
    {
        // Con un fármaco no hay nada que comparar: se distingue «no se verificó»
        // de «se verificó y salió limpio».
        $paciente = User::factory()->create();

        $this->postJson('/api/clinical-encounters', [
            'encounter_type' => 'teleconsultation',
            'patient_id' => $paciente->id,
            'consent' => true,
            'channel' => 'Videollamada institucional',
            'professional_license' => 'RM-12345',
            'chief_complaint' => 'Cefalea',
            'present_illness' => 'Cefalea tensional de dos días de evolución.',
            'diagnoses' => [['code' => 'G44.2', 'description' => 'Cefalea tensional', 'primary' => true]],
            'treatment_plan' => 'Analgesia y medidas de higiene del sueño.',
            'prescriptions' => [
                ['active_ingredient' => 'Acetaminofén', 'dose' => '500 mg', 'frequency' => 'cada 8 horas', 'duration_days' => 3],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.interactions', null);
    }

    #[Test]
    public function lista_las_fuentes_disponibles(): void
    {
        $this->getJson('/api/interaction-sources')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.source', 'vademecum')
            ->assertJsonPath('data.0.requires_network', false)
            ->assertJsonPath('data.1.source', 'rxnav')
            ->assertJsonPath('data.1.requires_network', true);
    }

    #[Test]
    public function la_verificacion_exige_sesion(): void
    {
        app('auth')->forgetGuards();

        $this->postJson('/api/interaction-checks', ['drugs' => ['Losartán', 'Ibuprofeno']])
            ->assertUnauthorized();
    }
}
