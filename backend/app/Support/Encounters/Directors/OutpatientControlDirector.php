<?php

namespace App\Support\Encounters\Directors;

use App\Models\User;
use App\Support\Encounters\ClinicalNoteBuilder;
use App\Support\Encounters\EncounterType;
use Illuminate\Support\Carbon;

/**
 * DIRECTOR CONCRETO: control ambulatorio de patología crónica.
 *
 * Su razón de ser es el seguimiento: sin antecedentes no hay con qué comparar,
 * y sin próxima cita no hay adherencia que auditar. Ambas cosas las exige el
 * builder para este tipo de nota.
 */
final class OutpatientControlDirector extends EncounterDirector
{
    public function type(): EncounterType
    {
        return EncounterType::OutpatientControl;
    }

    public function payloadRules(): array
    {
        return [
            'history' => ['required', 'string', 'min:10'],
            'physical_exam' => ['sometimes', 'nullable', 'string', 'min:10'],
            'follow_up_at' => ['required', 'date', 'after:today'],
            'program' => ['sometimes', 'string', 'max:120'],
        ];
    }

    protected function assembleSpecificSections(ClinicalNoteBuilder $builder, array $payload, User $patient): void
    {
        if (! empty($payload['physical_exam'])) {
            $builder->withPhysicalExam($payload['physical_exam']);
        }

        // En el control, las lecturas domiciliarias son el dato central: son las
        // que muestran si el tratamiento está funcionando entre consulta y
        // consulta.
        $builder->withDeviceReadings($this->recentReadings($patient, limit: 10));

        if (! empty($payload['follow_up_at'])) {
            $builder->withFollowUp(Carbon::parse($payload['follow_up_at']));
        }

        $builder->withMetadata([
            'program' => $payload['program'] ?? 'Crónicos',
        ]);
    }
}
