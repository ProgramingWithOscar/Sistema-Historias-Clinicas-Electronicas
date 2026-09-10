<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DeviceReading;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Iot\Readings\ReadingSeverity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClinicalExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        AuditLogger::resetInstance();

        $this->user = User::factory()->create([
            'name' => 'Ana María Rodríguez Pérez',
            'document_type' => 'CC',
            'document_number' => '52847391',
            'birth_date' => '1985-04-12',
        ]);

        $this->actingAs($this->user);

        DeviceReading::create([
            'device_type' => 'sphygmomanometer',
            'loinc_code' => '85354-9',
            'display' => 'Presión arterial',
            'value' => 150,
            'unit' => 'mm[Hg]',
            'severity' => ReadingSeverity::Warning,
            'components' => ['diastolic' => 95, 'pulse' => 78],
            'patient_id' => $this->user->id,
            'measured_at' => now(),
        ]);
    }

    #[Test]
    public function exporta_la_historia_como_bundle_de_fhir(): void
    {
        $response = $this->postJson('/api/clinical-exports', ['standard' => 'fhir_r4']);

        $response->assertCreated()
            ->assertJsonPath('data.media_type', 'application/fhir+json')
            ->assertJsonPath('data.observations', 1)
            ->assertJsonPath('data.document.resourceType', 'Bundle')
            ->assertJsonPath('data.document.type', 'document')
            ->assertJsonPath('data.document.entry.0.resource.resourceType', 'Patient')
            ->assertJsonPath('data.document.entry.0.resource.identifier.0.value', '52847391')
            ->assertJsonPath('data.document.entry.1.resource.resourceType', 'Observation')
            ->assertJsonPath('data.document.entry.1.resource.code.coding.0.code', '85354-9')
            ->assertJsonPath('data.document.entry.1.resource.valueQuantity.unit', 'mm[Hg]')
            ->assertJsonPath('data.document.entry.1.resource.interpretation.0.coding.0.code', 'A');
    }

    #[Test]
    public function la_observacion_de_fhir_apunta_al_paciente_de_su_misma_familia(): void
    {
        // Consistencia de la familia: la referencia del sujeto es la que produce
        // el serializador de paciente de FHIR, no la de otro estándar.
        $document = $this->postJson('/api/clinical-exports', ['standard' => 'fhir_r4'])
            ->json('data.document');

        $this->assertSame(
            'Patient/'.$document['entry'][0]['resource']['id'],
            $document['entry'][1]['resource']['subject']['reference'],
        );
    }

    #[Test]
    public function exporta_la_historia_como_resumen_digital_de_atencion(): void
    {
        $response = $this->postJson('/api/clinical-exports', ['standard' => 'rda']);

        $response->assertCreated()
            ->assertJsonPath('data.legal_basis', 'Resolución 1888 de 2025')
            ->assertJsonPath('data.document.resumenDigitalAtencion.paciente.numeroDocumento', '52847391')
            ->assertJsonPath('data.document.resumenDigitalAtencion.paciente.tipoDocumento', 'CC')
            ->assertJsonPath('data.document.resumenDigitalAtencion.hallazgos.0.codigoLoinc', '85354-9')
            ->assertJsonPath('data.document.resumenDigitalAtencion.hallazgos.0.interpretacion', 'Alerta')
            ->assertJsonPath('data.document.resumenDigitalAtencion.hallazgos.0.documentoPaciente', 'CC-52847391')
            ->assertJsonPath('data.document.resumenDigitalAtencion.totalHallazgos', 1);

        // El RDA no usa recursos FHIR en ningún nivel del documento.
        $response->assertJsonMissingPath('data.document.resourceType');
    }

    #[Test]
    public function la_exportacion_anonimizada_no_publica_ningun_dato_identificable(): void
    {
        $response = $this->postJson('/api/clinical-exports', ['standard' => 'anonymized']);

        $response->assertCreated()
            ->assertJsonPath('data.document.dataset.contieneDatosIdentificables', false)
            ->assertJsonPath('data.document.dataset.sujeto.disociado', true)
            ->assertJsonPath('data.document.dataset.sujeto.grupoEtario', '40-44');

        // La garantía se comprueba sobre el documento entero, no sólo sobre el
        // bloque del sujeto: ése es justamente el punto del Abstract Factory.
        $json = json_encode($response->json('data.document'), JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString('52847391', $json);
        $this->assertStringNotContainsString('Rodríguez', $json);
        $this->assertStringNotContainsString($this->user->email, $json);
        $this->assertStringNotContainsString('1985-04-12', $json);
    }

    #[Test]
    public function el_seudonimo_es_estable_entre_exportaciones(): void
    {
        // Estable para permitir estudios longitudinales, pero derivado con sal:
        // no se puede revertir al paciente desde el dataset.
        $primera = $this->postJson('/api/clinical-exports', ['standard' => 'anonymized'])
            ->json('data.document.dataset.sujeto.seudonimo');

        $segunda = $this->postJson('/api/clinical-exports', ['standard' => 'anonymized'])
            ->json('data.document.dataset.sujeto.seudonimo');

        $this->assertSame($primera, $segunda);
        $this->assertStringStartsWith('SUJ-', $primera);
    }

    #[Test]
    public function el_mismo_paciente_produce_documentos_distintos_segun_la_familia(): void
    {
        $fhir = $this->postJson('/api/clinical-exports', ['standard' => 'fhir_r4'])->json('data.document');
        $rda = $this->postJson('/api/clinical-exports', ['standard' => 'rda'])->json('data.document');

        $this->assertArrayHasKey('resourceType', $fhir);
        $this->assertArrayHasKey('resumenDigitalAtencion', $rda);
        $this->assertArrayNotHasKey('resourceType', $rda);
    }

    #[Test]
    public function rechaza_un_estandar_no_soportado(): void
    {
        $this->postJson('/api/clinical-exports', ['standard' => 'excel'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('standard');
    }

    #[Test]
    public function cada_exportacion_queda_auditada(): void
    {
        $this->postJson('/api/clinical-exports', ['standard' => 'fhir_r4'])->assertCreated();

        $evento = AuditLog::where('action', 'hce.export.generated')->firstOrFail();

        $this->assertSame($this->user->id, $evento->actor_id);
        $this->assertSame($this->user->id, $evento->subject_id);
        $this->assertSame('fhir_r4', $evento->metadata['standard']);
        $this->assertTrue($evento->metadata['identifies_patient']);
        $this->assertSame(1, $evento->metadata['observations']);
    }

    #[Test]
    public function lista_las_familias_disponibles(): void
    {
        $this->getJson('/api/exchange-standards')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.standard', 'fhir_r4')
            ->assertJsonPath('data.2.identifies_patient', false);
    }

    #[Test]
    public function la_exportacion_requiere_sesion(): void
    {
        app('auth')->forgetGuards();

        $this->postJson('/api/clinical-exports', ['standard' => 'fhir_r4'])->assertUnauthorized();
    }
}
