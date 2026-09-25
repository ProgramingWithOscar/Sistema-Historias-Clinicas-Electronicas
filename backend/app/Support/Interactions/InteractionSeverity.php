<?php

namespace App\Support\Interactions;

/**
 * Gravedad de una interacción medicamentosa.
 *
 * Es el vocabulario PROPIO del sistema. Cada fuente externa usa el suyo
 * —«high/moderate/low» en unas, «Grave/Moderada/Leve» en otras, números del 1
 * al 4 en las peores— y traducirlo a esta escala es precisamente una de las
 * tres cosas que hace el adaptador.
 */
enum InteractionSeverity: string
{
    /** Sin relevancia clínica: se documenta pero no cambia la conducta. */
    case Leve = 'leve';

    /** Exige vigilancia o ajuste de dosis. */
    case Moderada = 'moderada';

    /** Riesgo serio: valorar alternativa terapéutica. */
    case Grave = 'grave';

    /** No deben administrarse juntos bajo ninguna circunstancia. */
    case Contraindicada = 'contraindicada';

    public function label(): string
    {
        return match ($this) {
            self::Leve => 'Leve',
            self::Moderada => 'Moderada',
            self::Grave => 'Grave',
            self::Contraindicada => 'Contraindicada',
        };
    }

    /** ¿Debe frenar al profesional antes de firmar la fórmula? */
    public function requiresAttention(): bool
    {
        return $this !== self::Leve;
    }

    /** Orden para presentar primero lo más peligroso. */
    public function weight(): int
    {
        return match ($this) {
            self::Contraindicada => 4,
            self::Grave => 3,
            self::Moderada => 2,
            self::Leve => 1,
        };
    }
}
