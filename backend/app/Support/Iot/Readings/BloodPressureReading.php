<?php

namespace App\Support\Iot\Readings;

/**
 * PRODUCTO CONCRETO: presión arterial.
 *
 * Ejemplo de por qué el producto necesita `components()`: la observación tiene
 * dos cifras (sistólica y diastólica) y una tercera opcional (pulso), pero la
 * interfaz común expone un único `value()`. La sistólica es el valor principal
 * —el que se grafica y con el que se compara—, y el resto viaja como
 * componentes sin romper el contrato que ve el creador.
 */
final class BloodPressureReading implements ClinicalReading
{
    public function __construct(
        private readonly float $systolic,
        private readonly float $diastolic,
        private readonly ?int $pulse = null,
    ) {}

    public function loincCode(): string
    {
        // 85354-9: panel de presión arterial (sistólica + diastólica).
        return '85354-9';
    }

    public function display(): string
    {
        return 'Presión arterial';
    }

    public function value(): float
    {
        return $this->systolic;
    }

    public function unit(): string
    {
        return 'mm[Hg]';
    }

    public function severity(): ReadingSeverity
    {
        // Crisis hipertensiva o hipotensión con riesgo de shock.
        if ($this->systolic >= 180 || $this->diastolic >= 120 || $this->systolic < 90) {
            return ReadingSeverity::Critical;
        }

        if ($this->systolic >= 140 || $this->diastolic >= 90) {
            return ReadingSeverity::Warning;
        }

        return ReadingSeverity::Normal;
    }

    public function components(): array
    {
        return [
            'systolic' => $this->systolic,
            'diastolic' => $this->diastolic,
            'pulse' => $this->pulse,
        ];
    }
}
