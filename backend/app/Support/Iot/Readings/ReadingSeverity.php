<?php

namespace App\Support\Iot\Readings;

/**
 * Criticidad clínica de una lectura de dispositivo.
 *
 * Es el vocabulario común que comparten todos los productos concretos: cada
 * lectura sabe interpretar sus propios valores, pero todas responden con la
 * misma escala para que el triaje no dependa del tipo de dispositivo.
 */
enum ReadingSeverity: string
{
    /** Dentro de rango: no requiere acción. */
    case Normal = 'normal';

    /** Fuera de rango pero sin riesgo inmediato: seguimiento. */
    case Warning = 'warning';

    /** Riesgo inmediato: exige atención clínica. */
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Warning => 'Alerta',
            self::Critical => 'Crítica',
        };
    }

    /** ¿Debe generar una notificación al equipo asistencial? */
    public function requiresAttention(): bool
    {
        return $this !== self::Normal;
    }
}
