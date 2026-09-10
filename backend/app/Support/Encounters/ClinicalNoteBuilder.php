<?php

namespace App\Support\Encounters;

use App\Models\DeviceReading;
use App\Models\User;
use App\Support\Encounters\Exceptions\IncompleteClinicalNoteException;
use App\Support\Encounters\Parts\Diagnosis;
use App\Support\Encounters\Parts\Prescription;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * BUILDER del patrón Builder (GoF).
 *
 * Construye la nota de atención paso a paso. Cada método añade una parte y
 * devuelve `$this`, de modo que el director encadena sólo las secciones que su
 * tipo de atención necesita; `build()` cierra la construcción validando el
 * contenido mínimo antes de entregar el producto inmutable.
 *
 * Reparte responsabilidades así:
 *
 * - El BUILDER sabe *cómo* se añade cada parte y qué es una nota válida.
 * - El DIRECTOR sabe *qué* partes lleva cada tipo de atención y en qué orden.
 * - El PRODUCTO no sabe nada: sólo guarda el resultado, ya validado.
 */
final class ClinicalNoteBuilder
{
    private ?EncounterType $type = null;

    private ?int $patientId = null;

    private ?int $professionalId = null;

    private ?string $professionalLicense = null;

    private ?Carbon $attendedAt = null;

    private ?string $chiefComplaint = null;

    private ?string $presentIllness = null;

    private ?string $history = null;

    private ?string $physicalExam = null;

    /** @var list<array<string, mixed>> */
    private array $vitalSigns = [];

    /** @var list<Diagnosis> */
    private array $diagnoses = [];

    private ?string $treatmentPlan = null;

    /** @var list<Prescription> */
    private array $prescriptions = [];

    private ?TriageLevel $triage = null;

    private ?Carbon $followUpAt = null;

    /** @var array<string, mixed> */
    private array $metadata = [];

    public function ofType(EncounterType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function forPatient(User $patient): self
    {
        $this->patientId = $patient->id;

        return $this;
    }

    /** Identificación del profesional y su registro médico: dato obligatorio. */
    public function attendedBy(User $professional, string $license): self
    {
        $this->professionalId = $professional->id;
        $this->professionalLicense = $license;

        return $this;
    }

    public function at(Carbon $moment): self
    {
        $this->attendedAt = $moment;

        return $this;
    }

    /** Motivo de consulta, en palabras del paciente. */
    public function withChiefComplaint(string $complaint): self
    {
        $this->chiefComplaint = trim($complaint);

        return $this;
    }

    /** Enfermedad actual: la anamnesis. */
    public function withPresentIllness(string $illness): self
    {
        $this->presentIllness = trim($illness);

        return $this;
    }

    /** Antecedentes personales, familiares y farmacológicos. */
    public function withHistory(?string $history): self
    {
        $this->history = $history === null ? null : trim($history);

        return $this;
    }

    /**
     * Hallazgos del examen físico.
     *
     * @throws LogicException si la atención no fue presencial
     */
    public function withPhysicalExam(string $findings): self
    {
        // Regla de dominio, no de formato: en una teleconsulta el profesional no
        // tiene al paciente delante, así que documentar una exploración sería
        // consignar en la historia clínica algo que no ocurrió.
        if ($this->type !== null && ! $this->type->isInPerson()) {
            throw new LogicException(
                'No puede documentarse un examen físico en una atención no presencial.'
            );
        }

        $this->physicalExam = trim($findings);

        return $this;
    }

    /**
     * Incorpora las lecturas que ya normalizó el Factory Method.
     *
     * La nota no vuelve a interpretar los valores: reutiliza el código LOINC y
     * la severidad que calculó el producto `ClinicalReading` en su momento.
     *
     * @param  iterable<DeviceReading>  $readings
     */
    public function withDeviceReadings(iterable $readings): self
    {
        foreach ($readings as $reading) {
            $this->vitalSigns[] = [
                'loinc_code' => $reading->loinc_code,
                'display' => $reading->display,
                'value' => $reading->value,
                'unit' => $reading->unit,
                'severity' => $reading->severity->value,
                'measured_at' => $reading->measured_at?->toIso8601String(),
                'source' => $reading->device_type,
            ];
        }

        return $this;
    }

    public function addDiagnosis(string $cie10, string $description, bool $primary = false): self
    {
        $this->diagnoses[] = new Diagnosis($cie10, $description, $primary);

        return $this;
    }

    public function withTreatmentPlan(string $plan): self
    {
        $this->treatmentPlan = trim($plan);

        return $this;
    }

    public function addPrescription(
        string $activeIngredient,
        string $dose,
        string $frequency,
        int $durationDays,
        ?string $notes = null,
    ): self {
        $this->prescriptions[] = new Prescription($activeIngredient, $dose, $frequency, $durationDays, $notes);

        return $this;
    }

    public function withTriage(TriageLevel $level): self
    {
        $this->triage = $level;

        return $this;
    }

    public function withFollowUp(Carbon $date): self
    {
        $this->followUpAt = $date;

        return $this;
    }

    /**
     * Notas propias del tipo de atención (consentimiento de telesalud, medio de
     * la videollamada, servicio de urgencias que recibe…).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        $this->metadata = [...$this->metadata, ...$metadata];

        return $this;
    }

    /**
     * Cierra la construcción y entrega el producto.
     *
     * Aquí está el valor real del patrón: una nota a medio llenar no llega a
     * existir como objeto. Si falta cualquiera de los ocho contenidos mínimos de
     * la Resolución 1995 de 1999, no se devuelve un objeto incompleto ni un
     * `null`: se aborta la construcción.
     *
     * @throws IncompleteClinicalNoteException
     */
    public function build(): ClinicalNote
    {
        $missing = $this->missingSections();

        if ($missing !== []) {
            throw new IncompleteClinicalNoteException($missing);
        }

        return ClinicalNote::fromBuilder([
            'type' => $this->type,
            'patientId' => $this->patientId,
            'professionalId' => $this->professionalId,
            'professionalLicense' => $this->professionalLicense,
            'attendedAt' => $this->attendedAt,
            'chiefComplaint' => $this->chiefComplaint,
            'presentIllness' => $this->presentIllness,
            'diagnoses' => $this->diagnoses,
            'treatmentPlan' => $this->treatmentPlan,
            'history' => $this->history,
            'physicalExam' => $this->physicalExam,
            'vitalSigns' => $this->vitalSigns,
            'prescriptions' => $this->prescriptions,
            'triage' => $this->triage,
            'followUpAt' => $this->followUpAt,
            'metadata' => $this->metadata,
        ]);
    }

    /**
     * Contenido mínimo de la historia clínica (Res. 1995 de 1999, art. 3 y 5)
     * más las exigencias propias de cada tipo de atención.
     *
     * @return list<string>
     */
    private function missingSections(): array
    {
        $missing = [];

        $obligatorias = [
            'tipo de atención' => $this->type,
            'identificación del paciente' => $this->patientId,
            'identificación del profesional' => $this->professionalId,
            'registro profesional' => $this->professionalLicense,
            'fecha y hora de la atención' => $this->attendedAt,
            'motivo de consulta' => $this->chiefComplaint,
            'enfermedad actual' => $this->presentIllness,
            'plan de manejo' => $this->treatmentPlan,
        ];

        foreach ($obligatorias as $nombre => $valor) {
            if ($valor === null || $valor === '') {
                $missing[] = $nombre;
            }
        }

        if ($this->diagnoses === []) {
            $missing[] = 'diagnóstico';
        }

        // La urgencia sin triaje ni signos vitales no es una atención de
        // urgencias documentada: es una nota que no permite auditar la
        // oportunidad de la atención (Res. 5596 de 2015).
        if ($this->type === EncounterType::Emergency) {
            if ($this->triage === null) {
                $missing[] = 'clasificación de triaje';
            }

            if ($this->vitalSigns === []) {
                $missing[] = 'signos vitales';
            }
        }

        if ($this->type === EncounterType::OutpatientControl && $this->history === null) {
            $missing[] = 'antecedentes';
        }

        if ($this->type === EncounterType::Teleconsultation && ! ($this->metadata['consent'] ?? false)) {
            $missing[] = 'consentimiento informado de telesalud';
        }

        return $missing;
    }
}
