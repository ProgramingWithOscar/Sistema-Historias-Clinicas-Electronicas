<?php

namespace App\Support\Encounters;

/**
 * Tipo de atención que documenta la nota clínica.
 *
 * Cada tipo tiene su propio DIRECTOR: la misma nota se arma con secciones
 * distintas y en un orden distinto según se trate de una urgencia, un control
 * ambulatorio o una teleconsulta.
 */
enum EncounterType: string
{
    /** Atención de urgencias: exige triaje y signos vitales. */
    case Emergency = 'emergency';

    /** Consulta externa de control: exige antecedentes y plan de seguimiento. */
    case OutpatientControl = 'outpatient_control';

    /** Teleconsulta (Res. 2654 de 2019): sin examen físico, con consentimiento. */
    case Teleconsultation = 'teleconsultation';

    public function label(): string
    {
        return match ($this) {
            self::Emergency => 'Urgencias',
            self::OutpatientControl => 'Control ambulatorio',
            self::Teleconsultation => 'Teleconsulta',
        };
    }

    /**
     * ¿El profesional tiene al paciente delante?
     *
     * De aquí sale la regla que impide documentar un examen físico en una
     * teleconsulta: consignar una exploración que no ocurrió es falsear la
     * historia clínica.
     */
    public function isInPerson(): bool
    {
        return $this !== self::Teleconsultation;
    }
}
