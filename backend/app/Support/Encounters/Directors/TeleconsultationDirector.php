<?php

namespace App\Support\Encounters\Directors;

use App\Models\User;
use App\Support\Encounters\ClinicalNoteBuilder;
use App\Support\Encounters\EncounterType;

/**
 * DIRECTOR CONCRETO: teleconsulta (Resolución 2654 de 2019).
 *
 * Es el que mejor muestra para qué sirve tener un director: no se limita a
 * añadir secciones, también **omite** una. Nunca llama a `withPhysicalExam()`,
 * porque en una atención no presencial no hay exploración que documentar, y
 * añade en cambio el consentimiento informado que la norma de telesalud exige.
 */
final class TeleconsultationDirector extends EncounterDirector
{
    public function type(): EncounterType
    {
        return EncounterType::Teleconsultation;
    }

    public function payloadRules(): array
    {
        return [
            'consent' => ['required', 'accepted'],
            'channel' => ['required', 'string', 'max:60'],
            'follow_up_at' => ['sometimes', 'nullable', 'date', 'after:today'],
            // Se rechaza explícitamente en lugar de ignorarlo en silencio: quien
            // lo envía debe enterarse de que no puede documentarlo.
            'physical_exam' => ['prohibited'],
        ];
    }

    protected function assembleSpecificSections(ClinicalNoteBuilder $builder, array $payload, User $patient): void
    {
        // Sin examen físico. Las cifras vienen de los dispositivos del paciente,
        // que es lo único que el profesional puede constatar a distancia.
        $builder
            ->withDeviceReadings($this->recentReadings($patient, limit: 10))
            ->withMetadata([
                'consent' => (bool) ($payload['consent'] ?? false),
                'channel' => $payload['channel'] ?? null,
                'legal_basis' => 'Resolución 2654 de 2019',
                'modality' => 'telemedicina interactiva',
            ]);
    }
}
