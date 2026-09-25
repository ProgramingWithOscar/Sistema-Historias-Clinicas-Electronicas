<?php

namespace App\Support\Interactions\Parts;

use App\Support\Interactions\InteractionSeverity;

/**
 * Una interacción entre dos principios activos, ya traducida al vocabulario
 * del sistema.
 *
 * Es inmutable: representa un hallazgo que se consigna en la historia clínica,
 * no un borrador que alguien pueda retocar después.
 */
final class DrugInteraction
{
    public function __construct(
        public readonly string $drugA,
        public readonly string $drugB,
        public readonly InteractionSeverity $severity,
        public readonly string $description,
        /** Fuente que reportó el hallazgo: exigible en una auditoría clínica. */
        public readonly string $source,
    ) {}

    /**
     * Clave estable del par, sin importar el orden en que llegaron.
     *
     * Sirve para no reportar dos veces la misma interacción cuando varias
     * fuentes la encuentran.
     */
    public function pairKey(): string
    {
        $par = [mb_strtolower($this->drugA), mb_strtolower($this->drugB)];
        sort($par);

        return implode('|', $par);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'drug_a' => $this->drugA,
            'drug_b' => $this->drugB,
            'severity' => $this->severity->value,
            'severity_label' => $this->severity->label(),
            'requires_attention' => $this->severity->requiresAttention(),
            'description' => $this->description,
            'source' => $this->source,
        ];
    }
}
