<?php

namespace App\Support\Encounters\Directors;

use App\Models\User;
use App\Support\Encounters\ClinicalNoteBuilder;
use App\Support\Encounters\EncounterType;
use App\Support\Encounters\TriageLevel;

/**
 * DIRECTOR CONCRETO: atención de urgencias.
 *
 * Arma la nota más exigente de las tres: sin triaje y sin signos vitales no
 * puede auditarse la oportunidad de la atención, así que el builder la rechaza.
 */
final class EmergencyEncounterDirector extends EncounterDirector
{
    public function type(): EncounterType
    {
        return EncounterType::Emergency;
    }

    public function payloadRules(): array
    {
        return [
            'triage' => ['required', 'string', 'in:I,II,III,IV,V'],
            'physical_exam' => ['required', 'string', 'min:10'],
            'service' => ['sometimes', 'string', 'max:120'],
        ];
    }

    protected function assembleSpecificSections(ClinicalNoteBuilder $builder, array $payload, User $patient): void
    {
        // `tryFrom` en vez de `from`: un triaje ausente o inválido deja el paso
        // sin dar, y es `build()` quien lo reporta como sección faltante.
        $triage = TriageLevel::tryFrom((string) ($payload['triage'] ?? ''));

        if ($triage !== null) {
            $builder->withTriage($triage);
        }

        if (! empty($payload['physical_exam'])) {
            $builder->withPhysicalExam($payload['physical_exam']);
        }

        $builder->withMetadata(array_filter([
            'service' => $payload['service'] ?? 'Urgencias',
            'max_wait_minutes' => $triage?->maxWaitMinutes(),
        ], fn ($valor) => $valor !== null));

        // Los signos vitales de urgencias salen de los dispositivos conectados,
        // no se transcriben a mano: es la garantía de que la cifra que queda en
        // la historia es la que midió el equipo.
        $builder->withDeviceReadings($this->recentReadings($patient, limit: 5));
    }
}
