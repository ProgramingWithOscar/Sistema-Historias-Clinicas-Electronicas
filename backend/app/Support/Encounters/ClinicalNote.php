<?php

namespace App\Support\Encounters;

use App\Support\Encounters\Parts\Diagnosis;
use App\Support\Encounters\Parts\Prescription;
use Illuminate\Support\Carbon;

/**
 * PRODUCTO del patrón Builder: la nota de atención clínica ya armada.
 *
 * Tiene quince partes, de las cuales sólo ocho son obligatorias y el resto
 * dependen del tipo de atención. Construirla con un constructor sería
 * inmanejable —quince argumentos, la mitad `null`, y ningún orden evidente— y
 * con setters públicos quedaría mutable, que es justo lo que la historia
 * clínica no puede ser.
 *
 * Por eso el constructor es `private`: la ÚNICA forma de obtener una nota es a
 * través de `ClinicalNoteBuilder::build()`, que antes verifica el contenido
 * mínimo de la Resolución 1995 de 1999. Una vez creada, es de sólo lectura.
 */
final class ClinicalNote
{
    /**
     * @param  list<Diagnosis>  $diagnoses
     * @param  list<Prescription>  $prescriptions
     * @param  list<array<string, mixed>>  $vitalSigns
     * @param  array<string, mixed>  $metadata
     */
    private function __construct(
        public readonly EncounterType $type,
        public readonly int $patientId,
        public readonly int $professionalId,
        public readonly string $professionalLicense,
        public readonly Carbon $attendedAt,
        public readonly string $chiefComplaint,
        public readonly string $presentIllness,
        public readonly array $diagnoses,
        public readonly string $treatmentPlan,
        public readonly ?string $history = null,
        public readonly ?string $physicalExam = null,
        public readonly array $vitalSigns = [],
        public readonly array $prescriptions = [],
        public readonly ?TriageLevel $triage = null,
        public readonly ?Carbon $followUpAt = null,
        public readonly array $metadata = [],
    ) {}

    /**
     * Fábrica interna reservada al builder.
     *
     * No es pública en la práctica: el builder es el único que la conoce y
     * `build()` es el único camino que la alcanza tras validar la nota.
     *
     * @internal
     *
     * @param  array<string, mixed>  $parts
     */
    public static function fromBuilder(array $parts): self
    {
        return new self(...$parts);
    }

    /** Diagnóstico principal: el que encabeza la nota y va al RIPS. */
    public function primaryDiagnosis(): ?Diagnosis
    {
        foreach ($this->diagnoses as $diagnosis) {
            if ($diagnosis->primary) {
                return $diagnosis;
            }
        }

        return $this->diagnoses[0] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'patient_id' => $this->patientId,
            'professional_id' => $this->professionalId,
            'professional_license' => $this->professionalLicense,
            'attended_at' => $this->attendedAt,
            'chief_complaint' => $this->chiefComplaint,
            'present_illness' => $this->presentIllness,
            'history' => $this->history,
            'physical_exam' => $this->physicalExam,
            'vital_signs' => $this->vitalSigns,
            'diagnoses' => array_map(fn (Diagnosis $d) => $d->toArray(), $this->diagnoses),
            'treatment_plan' => $this->treatmentPlan,
            'prescriptions' => array_map(fn (Prescription $p) => $p->toArray(), $this->prescriptions),
            'triage' => $this->triage?->value,
            'follow_up_at' => $this->followUpAt,
            'metadata' => $this->metadata,
        ];
    }
}
