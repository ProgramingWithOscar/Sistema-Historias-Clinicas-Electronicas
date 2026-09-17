<?php

namespace App\Models;

use App\Support\Encounters\EncounterType;
use App\Support\Templates\EncounterTemplate;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plantilla de atención persistida.
 *
 * El modelo es sólo el almacén: el prototipo vivo es
 * `App\Support\Templates\EncounterTemplate`. `toPrototype()` rehidrata uno y
 * `fromPrototype()` lo guarda, de modo que la lógica de copia nunca se mezcla
 * con Eloquent.
 */
#[Fillable([
    'key',
    'name',
    'type',
    'chief_complaint',
    'treatment_plan',
    'follow_up_days',
    'diagnoses',
    'prescriptions',
    'metadata',
    'author_id',
    'origin_encounter_id',
])]
class ClinicalTemplate extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EncounterType::class,
            'diagnoses' => 'array',
            'prescriptions' => 'array',
            'metadata' => 'array',
            'follow_up_days' => 'integer',
        ];
    }

    /** Guarda un prototipo en la base de datos. */
    public static function fromPrototype(EncounterTemplate $prototype): self
    {
        $payload = $prototype->toArray();

        return self::create([
            'key' => $prototype->key(),
            'name' => $prototype->name(),
            'type' => $prototype->type(),
            'chief_complaint' => $payload['chief_complaint'],
            'treatment_plan' => $payload['treatment_plan'],
            'follow_up_days' => $payload['follow_up_days'],
            'diagnoses' => $payload['diagnoses'],
            'prescriptions' => $payload['prescriptions'],
            'metadata' => [],
            'author_id' => $prototype->authorId(),
            'origin_encounter_id' => $prototype->originEncounterId(),
        ]);
    }

    /** Reconstruye el prototipo vivo a partir de la fila guardada. */
    public function toPrototype(): EncounterTemplate
    {
        $prototype = new EncounterTemplate(
            key: $this->key,
            name: $this->name,
            type: $this->type,
            chiefComplaint: $this->chief_complaint,
            treatmentPlan: $this->treatment_plan,
            followUpDays: $this->follow_up_days,
            metadata: $this->metadata ?? [],
            authorId: $this->author_id,
            originEncounterId: $this->origin_encounter_id,
        );

        foreach ($this->diagnoses ?? [] as $diagnostico) {
            $prototype->addDiagnosis(
                $diagnostico['code'],
                $diagnostico['description'],
                $diagnostico['primary'] ?? false,
            );
        }

        foreach ($this->prescriptions ?? [] as $medicamento) {
            $prototype->addPrescription(
                $medicamento['active_ingredient'],
                $medicamento['dose'],
                $medicamento['frequency'],
                (int) $medicamento['duration_days'],
                $medicamento['notes'] ?? null,
            );
        }

        return $prototype;
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
