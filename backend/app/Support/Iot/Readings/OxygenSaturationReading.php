<?php

namespace App\Support\Iot\Readings;

/**
 * PRODUCTO CONCRETO: saturación de oxígeno (SpO2).
 */
final class OxygenSaturationReading implements ClinicalReading
{
    public function __construct(
        private readonly float $percentage,
        private readonly ?int $pulse = null,
    ) {}

    public function loincCode(): string
    {
        // 59408-5: SpO2 por oximetría de pulso.
        return '59408-5';
    }

    public function display(): string
    {
        return 'Saturación de oxígeno (SpO2)';
    }

    public function value(): float
    {
        return $this->percentage;
    }

    public function unit(): string
    {
        return '%';
    }

    public function severity(): ReadingSeverity
    {
        if ($this->percentage < 90) {
            return ReadingSeverity::Critical;
        }

        return $this->percentage < 95
            ? ReadingSeverity::Warning
            : ReadingSeverity::Normal;
    }

    public function components(): array
    {
        return ['pulse' => $this->pulse];
    }
}
