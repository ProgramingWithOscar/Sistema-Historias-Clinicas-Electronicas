<?php

namespace App\Support\Templates;

use App\Models\ClinicalEncounter;
use App\Support\Encounters\EncounterType;
use App\Support\Templates\Parts\DiagnosisDraft;
use App\Support\Templates\Parts\PrescriptionDraft;

/**
 * PROTOTIPO CONCRETO: plantilla de atención clínica.
 *
 * Es una nota a medio escribir, ya configurada con el diagnóstico habitual, el
 * plan de manejo y la medicación típica de un motivo de consulta frecuente. El
 * médico no la rellena desde cero: pide una copia y la ajusta al paciente que
 * tiene delante.
 *
 * Ahí está el patrón: el objeto que se necesita YA EXISTE configurado, así que
 * es más barato y más fiable copiarlo que reconstruirlo paso a paso. El Builder
 * sigue siendo el que cierra la nota y valida el contenido mínimo; el Prototype
 * sólo le ahorra el trabajo repetido.
 */
final class EncounterTemplate implements ClinicalPrototype
{
    /**
     * Secciones que NUNCA se heredan al crear una plantilla desde una nota real.
     *
     * Son el relato de un paciente concreto. Arrastrarlas a la atención de otro
     * es el error de «copy-forward»: una fuente documentada de daño al paciente
     * y de historias clínicas que describen a quien no es.
     */
    public const SECCIONES_NO_HEREDABLES = [
        'present_illness',   // la anamnesis de ESA consulta
        'history',           // los antecedentes de ESE paciente
        'physical_exam',     // los hallazgos de ESA exploración
        'vital_signs',       // las cifras de ESE momento
        'patient_id',
        'professional_license',
        'attended_at',
        'triage',            // la gravedad de ESE episodio
    ];

    /** @var list<DiagnosisDraft> */
    private array $diagnoses = [];

    /** @var list<PrescriptionDraft> */
    private array $prescriptions = [];

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        private string $key,
        private string $name,
        private EncounterType $type,
        private string $chiefComplaint = '',
        private string $treatmentPlan = '',
        /** Intervalo relativo: una plantilla no puede guardar una fecha absoluta. */
        private ?int $followUpDays = null,
        private array $metadata = [],
        private ?int $authorId = null,
        private ?int $originEncounterId = null,
    ) {}

    /**
     * LA OPERACIÓN DEL PATRÓN.
     *
     * `clone` dispara `__clone()`, que es donde vive la copia profunda.
     */
    public function copy(): static
    {
        return clone $this;
    }

    /**
     * Gancho de copia de PHP: se ejecuta sobre el objeto YA duplicado.
     *
     * Sin este método, PHP haría una copia superficial y los arreglos de la
     * copia apuntarían a los MISMOS objetos `DiagnosisDraft` y
     * `PrescriptionDraft` que el original. Ajustar la dosis del borrador
     * cambiaría entonces la plantilla institucional, y con ella la dosis de
     * todos los pacientes que se atiendan después.
     */
    public function __clone(): void
    {
        $this->diagnoses = array_map(
            fn (DiagnosisDraft $diagnostico) => clone $diagnostico,
            $this->diagnoses,
        );

        $this->prescriptions = array_map(
            fn (PrescriptionDraft $medicamento) => clone $medicamento,
            $this->prescriptions,
        );
    }

    /**
     * Crea un prototipo nuevo a partir de una nota ya registrada: el «guardar
     * como plantilla» del médico.
     *
     * Copia la ESTRUCTURA reutilizable —motivo, diagnósticos, plan, medicación—
     * y descarta todo lo que pertenece al paciente atendido
     * (`SECCIONES_NO_HEREDABLES`).
     */
    public static function fromEncounter(
        ClinicalEncounter $encounter,
        string $key,
        string $name,
        ?int $authorId = null,
    ): self {
        $template = new self(
            key: $key,
            name: $name,
            type: $encounter->type,
            chiefComplaint: $encounter->chief_complaint,
            treatmentPlan: $encounter->treatment_plan,
            followUpDays: self::intervaloEnDias($encounter),
            metadata: ['program' => $encounter->metadata['program'] ?? null],
            authorId: $authorId,
            originEncounterId: $encounter->id,
        );

        foreach ($encounter->diagnoses ?? [] as $diagnostico) {
            $template->addDiagnosis(
                $diagnostico['code'],
                $diagnostico['description'],
                $diagnostico['primary'] ?? false,
            );
        }

        foreach ($encounter->prescriptions ?? [] as $medicamento) {
            $template->addPrescription(
                $medicamento['active_ingredient'],
                $medicamento['dose'],
                $medicamento['frequency'],
                (int) $medicamento['duration_days'],
                $medicamento['notes'] ?? null,
            );
        }

        return $template;
    }

    /** La próxima cita se guarda como intervalo, no como fecha. */
    private static function intervaloEnDias(ClinicalEncounter $encounter): ?int
    {
        if ($encounter->follow_up_at === null || $encounter->attended_at === null) {
            return null;
        }

        return max(1, (int) $encounter->attended_at->diffInDays($encounter->follow_up_at));
    }

    public function addDiagnosis(string $code, string $description, bool $primary = false): self
    {
        $this->diagnoses[] = new DiagnosisDraft($code, $description, $primary);

        return $this;
    }

    public function addPrescription(
        string $activeIngredient,
        string $dose,
        string $frequency,
        int $durationDays,
        ?string $notes = null,
    ): self {
        $this->prescriptions[] = new PrescriptionDraft(
            $activeIngredient,
            $dose,
            $frequency,
            $durationDays,
            $notes,
        );

        return $this;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): EncounterType
    {
        return $this->type;
    }

    public function authorId(): ?int
    {
        return $this->authorId;
    }

    public function originEncounterId(): ?int
    {
        return $this->originEncounterId;
    }

    /** @return list<DiagnosisDraft> */
    public function diagnoses(): array
    {
        return $this->diagnoses;
    }

    /** @return list<PrescriptionDraft> */
    public function prescriptions(): array
    {
        return $this->prescriptions;
    }

    /**
     * Borrador con el que el médico empieza la atención.
     *
     * Devuelve exactamente las claves que espera `POST /api/clinical-encounters`,
     * de modo que la plantilla alimenta el flujo del Builder sin que éste tenga
     * que enterarse de que existen plantillas.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return array_filter([
            'encounter_type' => $this->type->value,
            'chief_complaint' => $this->chiefComplaint,
            'treatment_plan' => $this->treatmentPlan,
            'diagnoses' => array_map(
                fn (DiagnosisDraft $diagnostico) => $diagnostico->toArray(),
                $this->diagnoses,
            ),
            'prescriptions' => array_map(
                fn (PrescriptionDraft $medicamento) => $medicamento->toArray(),
                $this->prescriptions,
            ),
            // Se resuelve a fecha aquí, no en la plantilla: el intervalo cuenta
            // desde la atención que se está documentando.
            'follow_up_at' => $this->followUpDays === null
                ? null
                : now()->addDays($this->followUpDays)->toDateString(),
            'program' => $this->metadata['program'] ?? null,
        ], fn ($valor) => $valor !== null && $valor !== [] && $valor !== '');
    }

    /**
     * Ficha del prototipo para el catálogo de la interfaz.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'encounter_type' => $this->type->value,
            'type_label' => $this->type->label(),
            'chief_complaint' => $this->chiefComplaint,
            'treatment_plan' => $this->treatmentPlan,
            'follow_up_days' => $this->followUpDays,
            'diagnoses' => array_map(fn (DiagnosisDraft $d) => $d->toArray(), $this->diagnoses),
            'prescriptions' => array_map(fn (PrescriptionDraft $p) => $p->toArray(), $this->prescriptions),
            'author_id' => $this->authorId,
            'origin_encounter_id' => $this->originEncounterId,
            'built_in' => $this->authorId === null,
        ];
    }
}
