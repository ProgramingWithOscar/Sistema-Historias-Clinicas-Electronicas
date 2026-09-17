<?php

namespace App\Support\Templates;

use App\Support\Encounters\EncounterType;
use Illuminate\Validation\ValidationException;

/**
 * REGISTRO DE PROTOTIPOS (Prototype Registry).
 *
 * Guarda una instancia configurada de cada plantilla y entrega COPIAS cuando se
 * la piden. Nunca entrega el original: si lo hiciera, el primer médico que
 * ajustara una dosis modificaría la plantilla institucional para todos los
 * demás.
 *
 * Es también lo que hace innecesaria una subclase por plantilla. Sin el patrón
 * habría que escribir `ControlHipertensionFactory`, `ControlDiabetesFactory`,
 * `CrisisHipertensivaFactory`… una clase por cada motivo de consulta frecuente.
 * Con él, dar de alta una plantilla es registrar un objeto, no escribir código:
 * por eso el propio médico puede crear las suyas desde la interfaz.
 */
final class TemplateRegistry
{
    /** @var array<string, EncounterTemplate> */
    private array $prototypes = [];

    public function __construct()
    {
        $this->registerBuiltIns();
    }

    /** Añade un prototipo al catálogo (o reemplaza el que tuviera esa clave). */
    public function register(EncounterTemplate $prototype): self
    {
        $this->prototypes[$prototype->key()] = $prototype;

        return $this;
    }

    /**
     * Entrega una COPIA del prototipo pedido.
     *
     * Éste es el corazón del registro: el `copy()` no es una optimización ni
     * una cortesía, es lo que impide que el catálogo se corrompa.
     *
     * @throws ValidationException si la plantilla no existe
     */
    public function get(string $key): EncounterTemplate
    {
        $prototype = $this->prototypes[$key] ?? null;

        if ($prototype === null) {
            throw ValidationException::withMessages([
                'template' => "La plantilla «{$key}» no existe.",
            ]);
        }

        return $prototype->copy();
    }

    public function has(string $key): bool
    {
        return isset($this->prototypes[$key]);
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->prototypes);
    }

    /**
     * Catálogo completo. También devuelve copias: ni siquiera listar el
     * catálogo debe dar acceso a los originales.
     *
     * @return list<array<string, mixed>>
     */
    public function catalog(): array
    {
        return array_values(array_map(
            fn (EncounterTemplate $prototype) => $prototype->copy()->toArray(),
            $this->prototypes,
        ));
    }

    /**
     * Plantillas institucionales: los motivos de consulta más frecuentes en
     * consulta externa y urgencias.
     */
    private function registerBuiltIns(): void
    {
        $this->register(
            (new EncounterTemplate(
                key: 'hta_control',
                name: 'Control de hipertensión arterial',
                type: EncounterType::OutpatientControl,
                chiefComplaint: 'Control de hipertensión arterial',
                treatmentPlan: 'Continuar antihipertensivo, dieta hiposódica y actividad física '
                    .'150 minutos por semana. Toma domiciliaria de presión arterial dos veces al día.',
                followUpDays: 90,
                metadata: ['program' => 'Riesgo cardiovascular'],
            ))
                ->addDiagnosis('I10', 'Hipertensión esencial (primaria)', primary: true)
                ->addPrescription('Losartán', '50 mg', 'cada 24 horas', 90)
        );

        $this->register(
            (new EncounterTemplate(
                key: 'dm2_control',
                name: 'Control de diabetes mellitus tipo 2',
                type: EncounterType::OutpatientControl,
                chiefComplaint: 'Control de diabetes mellitus tipo 2',
                treatmentPlan: 'Continuar antidiabético oral, plan nutricional y automonitoreo '
                    .'de glucemia capilar. Control de hemoglobina glicosilada en tres meses.',
                followUpDays: 90,
                metadata: ['program' => 'Crónicos'],
            ))
                ->addDiagnosis('E11.9', 'Diabetes mellitus tipo 2 sin complicaciones', primary: true)
                ->addPrescription('Metformina', '850 mg', 'cada 12 horas', 90, 'Tomar con las comidas')
        );

        $this->register(
            (new EncounterTemplate(
                key: 'crisis_hipertensiva',
                name: 'Urgencia hipertensiva',
                type: EncounterType::Emergency,
                chiefComplaint: 'Cefalea y cifras tensionales elevadas',
                treatmentPlan: 'Monitorización continua, reducción gradual de la presión arterial '
                    .'y valoración de daño de órgano blanco.',
            ))
                ->addDiagnosis('I16.0', 'Urgencia hipertensiva', primary: true)
                ->addPrescription('Captopril', '25 mg', 'dosis única sublingual', 1)
        );

        $this->register(
            (new EncounterTemplate(
                key: 'tele_ira',
                name: 'Teleorientación por infección respiratoria aguda',
                type: EncounterType::Teleconsultation,
                chiefComplaint: 'Tos, congestión nasal y malestar general',
                treatmentPlan: 'Manejo sintomático, hidratación abundante y signos de alarma '
                    .'explicados al paciente. Consultar a urgencias si aparece dificultad respiratoria.',
                followUpDays: 7,
            ))
                ->addDiagnosis('J06.9', 'Infección aguda de las vías respiratorias superiores', primary: true)
                ->addPrescription('Acetaminofén', '500 mg', 'cada 8 horas', 5, 'Si hay fiebre o dolor')
        );
    }
}
