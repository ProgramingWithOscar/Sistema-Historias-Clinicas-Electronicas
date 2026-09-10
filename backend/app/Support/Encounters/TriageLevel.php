<?php

namespace App\Support\Encounters;

/**
 * Clasificación de triaje de urgencias (Resolución 5596 de 2015).
 *
 * Sólo aparece en las notas de urgencias: es el director de ese tipo de
 * atención el que obliga a que la sección exista.
 */
enum TriageLevel: string
{
    case I = 'I';
    case II = 'II';
    case III = 'III';
    case IV = 'IV';
    case V = 'V';

    public function label(): string
    {
        return match ($this) {
            self::I => 'I - Atención inmediata',
            self::II => 'II - Atención en menos de 30 minutos',
            self::III => 'III - Atención en menos de 120 minutos',
            self::IV => 'IV - Atención en menos de 240 minutos',
            self::V => 'V - Atención en menos de 360 minutos',
        };
    }

    /** Minutos máximos de espera que admite la norma para este nivel. */
    public function maxWaitMinutes(): int
    {
        return match ($this) {
            self::I => 0,
            self::II => 30,
            self::III => 120,
            self::IV => 240,
            self::V => 360,
        };
    }
}
