<?php

namespace Tests\Unit;

use App\Models\DeviceReading;
use App\Models\User;
use App\Support\Encounters\ClinicalNote;
use App\Support\Encounters\ClinicalNoteBuilder;
use App\Support\Encounters\Directors\EmergencyEncounterDirector;
use App\Support\Encounters\Directors\EncounterDirector;
use App\Support\Encounters\Directors\OutpatientControlDirector;
use App\Support\Encounters\Directors\TeleconsultationDirector;
use App\Support\Encounters\EncounterDirectorResolver;
use App\Support\Encounters\EncounterType;
use App\Support\Encounters\Exceptions\IncompleteClinicalNoteException;
use App\Support\Encounters\TriageLevel;
use App\Support\Iot\Readings\ReadingSeverity;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class ClinicalNoteBuilderTest extends TestCase
{
    #[Test]
    public function el_producto_solo_puede_crearse_desde_el_builder(): void
    {
        // El constructor privado es lo que hace del builder el único camino:
        // sin él, cualquiera podría fabricar una nota saltándose la validación.
        $this->assertTrue(
            (new ReflectionClass(ClinicalNote::class))->getConstructor()->isPrivate()
        );
    }

    #[Test]
    public function el_producto_es_inmutable(): void
    {
        $clase = new ReflectionClass(ClinicalNote::class);

        foreach ($clase->getProperties() as $propiedad) {
            $this->assertTrue(
                $propiedad->isReadOnly(),
                "La propiedad {$propiedad->getName()} debería ser de sólo lectura."
            );
        }

        // Y ningún setter que permita enmendar la nota después de creada.
        foreach ($clase->getMethods() as $metodo) {
            $this->assertStringStartsNotWith('set', $metodo->getName());
        }
    }

    #[Test]
    public function cada_paso_devuelve_el_mismo_builder(): void
    {
        // Interfaz fluida: es lo que permite al director encadenar sólo los
        // pasos que su tipo de atención necesita.
        $builder = new ClinicalNoteBuilder;

        $this->assertSame($builder, $builder->ofType(EncounterType::Emergency));
        $this->assertSame($builder, $builder->withChiefComplaint('Dolor torácico'));
        $this->assertSame($builder, $builder->addDiagnosis('I20.0', 'Angina inestable'));
        $this->assertSame($builder, $builder->withTriage(TriageLevel::II));
    }

    #[Test]
    public function una_nota_sin_contenido_minimo_no_llega_a_existir(): void
    {
        try {
            (new ClinicalNoteBuilder)
                ->ofType(EncounterType::OutpatientControl)
                ->withChiefComplaint('Control de hipertensión')
                ->build();

            $this->fail('El builder debió rechazar la nota incompleta.');
        } catch (IncompleteClinicalNoteException $e) {
            $this->assertContains('identificación del paciente', $e->missing);
            $this->assertContains('enfermedad actual', $e->missing);
            $this->assertContains('diagnóstico', $e->missing);
            $this->assertContains('plan de manejo', $e->missing);
        }
    }

    #[Test]
    public function la_urgencia_exige_triaje_y_signos_vitales(): void
    {
        $builder = $this->builderCompleto(EncounterType::Emergency);

        try {
            $builder->build();
            $this->fail('Una urgencia sin triaje no debería construirse.');
        } catch (IncompleteClinicalNoteException $e) {
            $this->assertContains('clasificación de triaje', $e->missing);
            $this->assertContains('signos vitales', $e->missing);
        }
    }

    #[Test]
    public function el_control_ambulatorio_exige_antecedentes(): void
    {
        try {
            $this->builderCompleto(EncounterType::OutpatientControl)->build();
            $this->fail('Un control sin antecedentes no debería construirse.');
        } catch (IncompleteClinicalNoteException $e) {
            $this->assertContains('antecedentes', $e->missing);
        }
    }

    #[Test]
    public function la_teleconsulta_exige_consentimiento(): void
    {
        try {
            $this->builderCompleto(EncounterType::Teleconsultation)->build();
            $this->fail('Una teleconsulta sin consentimiento no debería construirse.');
        } catch (IncompleteClinicalNoteException $e) {
            $this->assertContains('consentimiento informado de telesalud', $e->missing);
        }
    }

    #[Test]
    public function no_puede_documentarse_un_examen_fisico_en_una_teleconsulta(): void
    {
        $this->expectException(LogicException::class);

        (new ClinicalNoteBuilder)
            ->ofType(EncounterType::Teleconsultation)
            ->withPhysicalExam('Ruidos cardiacos rítmicos, sin soplos');
    }

    #[Test]
    public function la_nota_completa_se_construye_y_conserva_sus_partes(): void
    {
        $nota = $this->builderCompleto(EncounterType::Emergency)
            ->withTriage(TriageLevel::II)
            ->withDeviceReadings([$this->lectura()])
            ->addPrescription('Ácido acetilsalicílico', '100 mg', 'cada 24 horas', 30)
            ->build();

        $this->assertInstanceOf(ClinicalNote::class, $nota);
        $this->assertSame(EncounterType::Emergency, $nota->type);
        $this->assertSame(TriageLevel::II, $nota->triage);
        $this->assertCount(1, $nota->vitalSigns);
        $this->assertCount(1, $nota->prescriptions);
        $this->assertSame('I20.0', $nota->primaryDiagnosis()->code);
    }

    #[Test]
    public function el_diagnostico_principal_es_el_marcado_y_no_el_primero(): void
    {
        $nota = $this->builderCompleto(EncounterType::Teleconsultation)
            ->withMetadata(['consent' => true])
            ->addDiagnosis('E11.9', 'Diabetes mellitus tipo 2', primary: true)
            ->build();

        // El primero de la lista es I20.0, pero el marcado es el segundo.
        $this->assertSame('E11.9', $nota->primaryDiagnosis()->code);
    }

    #[Test]
    public function el_director_es_abstracto_y_su_secuencia_es_final(): void
    {
        $clase = new ReflectionClass(EncounterDirector::class);

        $this->assertTrue($clase->isAbstract());
        // Si una subclase pudiera reescribir `construct()`, podría omitir el
        // encabezado o los diagnósticos y saltarse el contenido mínimo.
        $this->assertTrue($clase->getMethod('construct')->isFinal());
        $this->assertTrue($clase->getMethod('assembleSpecificSections')->isAbstract());
    }

    #[Test]
    public function el_resolver_entrega_el_director_que_corresponde(): void
    {
        $resolver = new EncounterDirectorResolver;

        $this->assertInstanceOf(EmergencyEncounterDirector::class, $resolver->for('emergency'));
        $this->assertInstanceOf(OutpatientControlDirector::class, $resolver->for('outpatient_control'));
        $this->assertInstanceOf(TeleconsultationDirector::class, $resolver->for('teleconsultation'));
    }

    #[Test]
    public function el_resolver_rechaza_un_tipo_de_atencion_desconocido(): void
    {
        $this->expectException(ValidationException::class);

        (new EncounterDirectorResolver)->for('peluqueria');
    }

    /** Builder con todo lo común ya puesto, para aislar lo que falta en cada caso. */
    private function builderCompleto(EncounterType $type): ClinicalNoteBuilder
    {
        $paciente = new User(['name' => 'Paciente']);
        $paciente->id = 1;

        $profesional = new User(['name' => 'Profesional']);
        $profesional->id = 2;

        return (new ClinicalNoteBuilder)
            ->ofType($type)
            ->forPatient($paciente)
            ->attendedBy($profesional, 'RM-12345')
            ->at(Carbon::parse('2026-09-09 10:00:00'))
            ->withChiefComplaint('Dolor torácico opresivo')
            ->withPresentIllness('Cuadro de dos horas de evolución, irradiado a brazo izquierdo.')
            ->addDiagnosis('I20.0', 'Angina inestable')
            ->withTreatmentPlan('Monitorización continua y traslado a unidad coronaria.');
    }

    private function lectura(): DeviceReading
    {
        return new DeviceReading([
            'device_type' => 'sphygmomanometer',
            'loinc_code' => '85354-9',
            'display' => 'Presión arterial',
            'value' => 150,
            'unit' => 'mm[Hg]',
            'severity' => ReadingSeverity::Warning,
            'measured_at' => Carbon::parse('2026-09-09 09:50:00'),
        ]);
    }
}
