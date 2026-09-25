<?php

namespace App\Support\Interactions;

use App\Support\Interactions\Parts\DrugInteraction;
use Illuminate\Support\Carbon;

/**
 * Resultado de una verificación de interacciones, en el formato del sistema.
 *
 * Éste es el TARGET del patrón Adapter por el lado de la salida: da igual si
 * detrás respondió un API en inglés con códigos RxCUI o un vademécum en CSV,
 * el cliente siempre recibe esto.
 */
final class InteractionReport
{
    /**
     * @param  list<DrugInteraction>  $interactions
     * @param  list<string>  $checkedDrugs
     */
    public function __construct(
        public readonly array $interactions,
        public readonly array $checkedDrugs,
        public readonly string $source,
        public readonly Carbon $checkedAt,
    ) {}

    /** Informe vacío: la consulta se hizo y no había nada que reportar. */
    public static function empty(array $checkedDrugs, string $source): self
    {
        return new self([], $checkedDrugs, $source, Carbon::now());
    }

    /** ¿Hay algo que deba frenar al profesional antes de firmar? */
    public function hasWarnings(): bool
    {
        foreach ($this->interactions as $interaction) {
            if ($interaction->severity->requiresAttention()) {
                return true;
            }
        }

        return false;
    }

    /** La interacción más grave encontrada, que es la que encabeza la alerta. */
    public function mostSevere(): ?DrugInteraction
    {
        $peor = null;

        foreach ($this->interactions as $interaction) {
            if ($peor === null || $interaction->severity->weight() > $peor->severity->weight()) {
                $peor = $interaction;
            }
        }

        return $peor;
    }

    /**
     * Interacciones ordenadas de más grave a menos.
     *
     * @return list<DrugInteraction>
     */
    public function sorted(): array
    {
        $ordenadas = $this->interactions;

        usort(
            $ordenadas,
            fn (DrugInteraction $a, DrugInteraction $b) => $b->severity->weight() <=> $a->severity->weight()
        );

        return $ordenadas;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'checked_drugs' => $this->checkedDrugs,
            'checked_at' => $this->checkedAt->toIso8601String(),
            'has_warnings' => $this->hasWarnings(),
            'total' => count($this->interactions),
            'most_severe' => $this->mostSevere()?->severity->value,
            'interactions' => array_map(
                fn (DrugInteraction $i) => $i->toArray(),
                $this->sorted(),
            ),
        ];
    }
}
