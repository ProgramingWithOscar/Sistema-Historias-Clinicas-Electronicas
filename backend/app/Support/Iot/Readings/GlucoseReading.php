<?php

namespace App\Support\Iot\Readings;

/**
 * PRODUCTO CONCRETO: glucemia capilar.
 *
 * Toda la lógica clínica de la glucemia vive aquí, no en el controlador ni en
 * un `match` central: los umbrales cambian según si la muestra fue en ayunas,
 * y ese detalle sólo lo conoce este producto.
 */
final class GlucoseReading implements ClinicalReading
{
    public function __construct(
        private readonly float $mgPerDl,
        private readonly bool $fasting = false,
    ) {}

    public function loincCode(): string
    {
        // 1558-6: glucosa en ayunas; 2339-0: glucosa capilar sin ayuno.
        return $this->fasting ? '1558-6' : '2339-0';
    }

    public function display(): string
    {
        return $this->fasting
            ? 'Glucemia en ayunas'
            : 'Glucemia capilar';
    }

    public function value(): float
    {
        return $this->mgPerDl;
    }

    public function unit(): string
    {
        return 'mg/dL';
    }

    public function severity(): ReadingSeverity
    {
        // Hipoglucemia severa: riesgo inmediato con independencia del ayuno.
        if ($this->mgPerDl < 54) {
            return ReadingSeverity::Critical;
        }

        if ($this->mgPerDl < 70 || $this->mgPerDl > 250) {
            return $this->mgPerDl > 400
                ? ReadingSeverity::Critical
                : ReadingSeverity::Warning;
        }

        $limiteSuperior = $this->fasting ? 126.0 : 180.0;

        return $this->mgPerDl > $limiteSuperior
            ? ReadingSeverity::Warning
            : ReadingSeverity::Normal;
    }

    public function components(): array
    {
        return ['fasting' => $this->fasting];
    }
}
