<?php

namespace App\Support\Encounters\Parts;

/**
 * Medicamento prescrito dentro del plan de manejo.
 *
 * El principio activo se guarda aparte del nombre comercial porque es con él
 * con el que el motor de interacciones medicamentosas hace su verificación.
 */
final class Prescription
{
    public function __construct(
        public readonly string $activeIngredient,
        public readonly string $dose,
        public readonly string $frequency,
        public readonly int $durationDays,
        public readonly ?string $notes = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'active_ingredient' => $this->activeIngredient,
            'dose' => $this->dose,
            'frequency' => $this->frequency,
            'duration_days' => $this->durationDays,
            'notes' => $this->notes,
        ], fn ($valor) => $valor !== null);
    }
}
