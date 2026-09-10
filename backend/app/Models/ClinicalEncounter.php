<?php

namespace App\Models;

use App\Support\Encounters\ClinicalNote;
use App\Support\Encounters\EncounterType;
use App\Support\Encounters\TriageLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nota de atención persistida.
 *
 * El modelo no sabe armar una nota: sólo guarda la que el builder ya validó.
 * Por eso el único camino de entrada es `fromNote()`.
 */
#[Fillable([
    'type',
    'patient_id',
    'professional_id',
    'professional_license',
    'attended_at',
    'chief_complaint',
    'present_illness',
    'history',
    'physical_exam',
    'vital_signs',
    'diagnoses',
    'treatment_plan',
    'prescriptions',
    'triage',
    'follow_up_at',
    'metadata',
])]
class ClinicalEncounter extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EncounterType::class,
            'triage' => TriageLevel::class,
            'attended_at' => 'datetime',
            'follow_up_at' => 'datetime',
            'vital_signs' => 'array',
            'diagnoses' => 'array',
            'prescriptions' => 'array',
            'metadata' => 'array',
        ];
    }

    /** Persiste el producto que entregó el builder, sin transformarlo. */
    public static function fromNote(ClinicalNote $note): self
    {
        return self::create($note->toArray());
    }

    /** @return BelongsTo<User, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    /** @return BelongsTo<User, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_id');
    }
}
