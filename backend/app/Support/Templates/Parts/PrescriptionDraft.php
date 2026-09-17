<?php

namespace App\Support\Templates\Parts;

use App\Support\Encounters\Parts\Prescription;

/**
 * Medicamento propuesto por una plantilla.
 *
 * Es la pieza que mejor explica por qué la copia tiene que ser profunda: la
 * dosis de la plantilla es un punto de partida que casi siempre se ajusta al
 * paciente —por peso, por función renal, por edad—. Si el borrador compartiera
 * este objeto con la plantilla institucional, ajustar la dosis para un paciente
 * la cambiaría para todos los siguientes.
 */
final class PrescriptionDraft
{
    public function __construct(
        public string $activeIngredient,
        public string $dose,
        public string $frequency,
        public int $durationDays,
        public ?string $notes = null,
    ) {}

    /** Ajusta la dosis al paciente concreto que se está atendiendo. */
    public function adjustDose(string $dose): self
    {
        $this->dose = $dose;

        return $this;
    }

    /** Congela el borrador en la prescripción inmutable que va a la nota. */
    public function toPrescription(): Prescription
    {
        return new Prescription(
            $this->activeIngredient,
            $this->dose,
            $this->frequency,
            $this->durationDays,
            $this->notes,
        );
    }

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
