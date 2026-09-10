<?php

namespace App\Support\Interop;

use App\Models\DeviceReading;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Interop\Contracts\ClinicalExchangeFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * CLIENTE del patrón Abstract Factory.
 *
 * Ésta es la clase que justifica todo lo anterior: arma la historia clínica
 * exportable sin nombrar ni una sola vez FHIR, RDA o anonimización. Recibe una
 * fábrica, le pide sus tres productos y los usa a través de sus interfaces.
 *
 * Consecuencia práctica: el día que el MinSalud publique un formato nuevo, este
 * archivo no se toca. Y como los tres productos salen de la misma fábrica, es
 * imposible que el exportador combine un paciente de una familia con las
 * observaciones de otra.
 */
final class ClinicalRecordExporter
{
    public function __construct(
        private readonly ClinicalExchangeFactory $factory,
    ) {}

    /**
     * Genera el documento de intercambio con las lecturas del paciente.
     *
     * @return array{standard: string, media_type: string, filename: string, document: array<string, mixed>, observations: int}
     */
    public function export(User $patient, ?Request $request = null, int $limit = 100): array
    {
        // Los tres productos se piden a la MISMA fábrica: ahí está la garantía
        // de consistencia que aporta el patrón.
        $patientSerializer = $this->factory->createPatientSerializer();
        $observationSerializer = $this->factory->createObservationSerializer();
        $envelope = $this->factory->createEnvelope();

        $generatedAt = Carbon::now();
        $reference = $patientSerializer->reference($patient);

        $observations = DeviceReading::query()
            ->where('patient_id', $patient->id)
            ->latest('measured_at')
            ->limit($limit)
            ->get()
            ->map(fn (DeviceReading $reading) => $observationSerializer->serialize($reading, $reference))
            ->all();

        $document = $envelope->assemble(
            patient: $patientSerializer->serialize($patient),
            observations: $observations,
            generatedAt: $generatedAt,
        );

        $this->audit($patient, count($observations), $request);

        return [
            'standard' => $this->factory->standard()->value,
            'label' => $this->factory->standard()->label(),
            'legal_basis' => $this->factory->standard()->legalBasis(),
            'media_type' => $envelope->mediaType(),
            'filename' => $envelope->filename($generatedAt),
            'observations' => count($observations),
            'document' => $document,
        ];
    }

    /**
     * Toda salida de información clínica es un evento auditable: la Ley 2015 de
     * 2020 exige poder decir quién extrajo la historia de qué paciente y en qué
     * formato. Se reutiliza el Singleton de auditoría.
     */
    private function audit(User $patient, int $observations, ?Request $request): void
    {
        AuditLogger::getInstance()->record(
            action: 'hce.export.generated',
            actorId: $request?->user()?->id,
            subjectType: User::class,
            subjectId: $patient->id,
            metadata: [
                'standard' => $this->factory->standard()->value,
                'legal_basis' => $this->factory->standard()->legalBasis(),
                'identifies_patient' => $this->factory->standard()->identifiesPatient(),
                'observations' => $observations,
            ],
            request: $request,
        );
    }
}
