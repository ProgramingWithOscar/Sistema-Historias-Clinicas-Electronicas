<?php

namespace App\Support\Encounters\Directors;

use App\Models\DeviceReading;
use App\Models\User;
use App\Support\Encounters\ClinicalNote;
use App\Support\Encounters\ClinicalNoteBuilder;
use App\Support\Encounters\EncounterType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * DIRECTOR del patrón Builder.
 *
 * El director no construye nada por su cuenta: conoce el ORDEN y el CONJUNTO de
 * pasos que corresponden a su tipo de atención y se los pide al builder. Por eso
 * el mismo builder produce una nota de urgencias, una de control ambulatorio o
 * una de teleconsulta sin que ninguna de sus reglas cambie.
 *
 * `construct()` es `final` a propósito: fija el esqueleto común —identificación,
 * anamnesis, diagnósticos, plan— y deja a cada subclase sólo lo que la
 * distingue, en `assembleSpecificSections()`.
 */
abstract class EncounterDirector
{
    /** Tipo de atención que dirige. */
    abstract public function type(): EncounterType;

    /**
     * Reglas de validación del payload, propias de cada tipo de atención.
     *
     * @return array<string, mixed>
     */
    abstract public function payloadRules(): array;

    /**
     * Pasos que sólo aplican a este tipo de atención.
     *
     * @param  array<string, mixed>  $payload
     */
    abstract protected function assembleSpecificSections(ClinicalNoteBuilder $builder, array $payload, User $patient): void;

    /**
     * Secuencia de construcción de una nota clínica.
     *
     * @param  array<string, mixed>  $payload
     */
    final public function construct(array $payload, User $patient, User $professional): ClinicalNote
    {
        $builder = new ClinicalNoteBuilder;

        // Las secciones ausentes se pasan vacías en lugar de reventar aquí: el
        // único que decide si una nota está incompleta es `build()`, y así puede
        // informar de TODO lo que falta de una vez, no del primer hueco.
        // 1. Encabezado: quién, a quién y cuándo (Res. 1995 de 1999, art. 5).
        $builder
            ->ofType($this->type())
            ->forPatient($patient)
            ->attendedBy($professional, (string) ($payload['professional_license'] ?? ''))
            ->at(isset($payload['attended_at']) ? Carbon::parse($payload['attended_at']) : Carbon::now());

        // 2. Anamnesis: el relato de la consulta.
        $builder
            ->withChiefComplaint((string) ($payload['chief_complaint'] ?? ''))
            ->withPresentIllness((string) ($payload['present_illness'] ?? ''))
            ->withHistory($payload['history'] ?? null);

        // 3. Secciones propias del tipo de atención.
        $this->assembleSpecificSections($builder, $payload, $patient);

        // 4. Diagnósticos codificados en CIE-10.
        foreach ($payload['diagnoses'] ?? [] as $indice => $diagnostico) {
            $builder->addDiagnosis(
                cie10: $diagnostico['code'],
                description: $diagnostico['description'],
                // Si nadie marca el principal, lo es el primero de la lista.
                primary: $diagnostico['primary'] ?? $indice === 0,
            );
        }

        // 5. Plan de manejo y prescripciones.
        $builder->withTreatmentPlan((string) ($payload['treatment_plan'] ?? ''));

        foreach ($payload['prescriptions'] ?? [] as $medicamento) {
            $builder->addPrescription(
                activeIngredient: $medicamento['active_ingredient'],
                dose: $medicamento['dose'],
                frequency: $medicamento['frequency'],
                durationDays: (int) $medicamento['duration_days'],
                notes: $medicamento['notes'] ?? null,
            );
        }

        // 6. Cierre: aquí se valida el contenido mínimo o se aborta.
        return $builder->build();
    }

    /**
     * Últimas lecturas del paciente, ya normalizadas por el Factory Method.
     *
     * @return Collection<int, DeviceReading>
     */
    protected function recentReadings(User $patient, int $limit = 5): Collection
    {
        return DeviceReading::query()
            ->where('patient_id', $patient->id)
            ->latest('measured_at')
            ->limit($limit)
            ->get();
    }
}
