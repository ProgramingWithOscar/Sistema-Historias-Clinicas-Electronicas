<?php

namespace Tests\Unit;

use App\Support\Encounters\EncounterType;
use App\Support\Templates\ClinicalPrototype;
use App\Support\Templates\EncounterTemplate;
use App\Support\Templates\TemplateRegistry;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EncounterTemplatePrototypeTest extends TestCase
{
    #[Test]
    public function la_plantilla_es_un_prototipo(): void
    {
        $this->assertInstanceOf(ClinicalPrototype::class, $this->plantilla());
    }

    #[Test]
    public function la_copia_es_un_objeto_distinto_con_el_mismo_contenido(): void
    {
        $original = $this->plantilla();
        $copia = $original->copy();

        $this->assertNotSame($original, $copia);
        $this->assertSame($original->key(), $copia->key());
        $this->assertEquals($original->toArray(), $copia->toArray());
    }

    #[Test]
    public function ajustar_la_dosis_de_la_copia_no_toca_la_plantilla_original(): void
    {
        // ÉSTE es el caso que justifica escribir `__clone()` a mano. Con la
        // copia superficial de PHP, los dos objetos compartirían la misma
        // `PrescriptionDraft` y este ajuste cambiaría la plantilla para todos
        // los pacientes que se atiendan después.
        $original = $this->plantilla();
        $borrador = $original->copy();

        $borrador->prescriptions()[0]->adjustDose('100 mg');

        $this->assertSame('100 mg', $borrador->prescriptions()[0]->dose);
        $this->assertSame('50 mg', $original->prescriptions()[0]->dose);
    }

    #[Test]
    public function cambiar_el_diagnostico_de_la_copia_no_toca_el_original(): void
    {
        $original = $this->plantilla();
        $borrador = $original->copy();

        $borrador->diagnoses()[0]->code = 'I11.9';
        $borrador->diagnoses()[0]->primary = false;

        $this->assertSame('I11.9', $borrador->diagnoses()[0]->code);
        $this->assertSame('I10', $original->diagnoses()[0]->code);
        $this->assertTrue($original->diagnoses()[0]->primary);
    }

    #[Test]
    public function las_partes_de_la_copia_son_objetos_independientes(): void
    {
        $original = $this->plantilla();
        $copia = $original->copy();

        // Copia profunda: mismo contenido, distinta identidad.
        $this->assertNotSame($original->prescriptions()[0], $copia->prescriptions()[0]);
        $this->assertNotSame($original->diagnoses()[0], $copia->diagnoses()[0]);
        $this->assertEquals($original->prescriptions()[0], $copia->prescriptions()[0]);
    }

    #[Test]
    public function anadir_un_diagnostico_a_la_copia_no_alarga_el_original(): void
    {
        $original = $this->plantilla();
        $copia = $original->copy();

        $copia->addDiagnosis('E78.5', 'Hiperlipidemia no especificada');

        $this->assertCount(2, $copia->diagnoses());
        $this->assertCount(1, $original->diagnoses());
    }

    #[Test]
    public function el_registro_nunca_entrega_el_prototipo_original(): void
    {
        $registro = new TemplateRegistry;

        $primera = $registro->get('hta_control');
        $segunda = $registro->get('hta_control');

        // Dos médicos cargando la misma plantilla a la vez no pueden pisarse.
        $this->assertNotSame($primera, $segunda);

        $primera->prescriptions()[0]->adjustDose('25 mg');

        $this->assertSame('50 mg', $segunda->prescriptions()[0]->dose);
        $this->assertSame('50 mg', $registro->get('hta_control')->prescriptions()[0]->dose);
    }

    #[Test]
    public function el_registro_trae_las_plantillas_institucionales(): void
    {
        $registro = new TemplateRegistry;

        $this->assertContains('hta_control', $registro->keys());
        $this->assertContains('dm2_control', $registro->keys());
        $this->assertContains('crisis_hipertensiva', $registro->keys());
        $this->assertContains('tele_ira', $registro->keys());
    }

    #[Test]
    public function el_registro_rechaza_una_plantilla_inexistente(): void
    {
        $this->expectException(ValidationException::class);

        (new TemplateRegistry)->get('plantilla_que_no_existe');
    }

    #[Test]
    public function se_puede_registrar_una_plantilla_nueva_sin_escribir_una_clase(): void
    {
        // Sin el patrón haría falta una subclase por motivo de consulta. Aquí
        // dar de alta una plantilla es registrar un objeto.
        $registro = new TemplateRegistry;

        $registro->register(new EncounterTemplate(
            key: 'control_asma',
            name: 'Control de asma',
            type: EncounterType::OutpatientControl,
        ));

        $this->assertTrue($registro->has('control_asma'));
        $this->assertSame('Control de asma', $registro->get('control_asma')->name());
    }

    #[Test]
    public function el_borrador_sale_listo_para_el_endpoint_de_atenciones(): void
    {
        $payload = $this->plantilla()->copy()->toPayload();

        $this->assertSame('outpatient_control', $payload['encounter_type']);
        $this->assertSame('I10', $payload['diagnoses'][0]['code']);
        $this->assertSame('Losartán', $payload['prescriptions'][0]['active_ingredient']);

        // La próxima cita se guarda como intervalo y se resuelve a fecha al
        // copiar: una plantilla no puede llevar dentro una fecha absoluta.
        $this->assertSame(now()->addDays(90)->toDateString(), $payload['follow_up_at']);
    }

    private function plantilla(): EncounterTemplate
    {
        return (new TemplateRegistry)->get('hta_control');
    }
}
