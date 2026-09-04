<?php

namespace App\Support\Iot;

use App\Support\Iot\Readings\ClinicalReading;
use App\Support\Iot\Readings\OxygenSaturationReading;

/** CREADOR CONCRETO: oxímetro de pulso. */
final class PulseOximeterFactory extends DeviceReadingFactory
{
    public function deviceType(): string
    {
        return 'pulse_oximeter';
    }

    public function payloadRules(): array
    {
        return [
            'spo2' => ['required', 'numeric', 'between:50,100'],
            'pulse' => ['sometimes', 'integer', 'between:20,250'],
            'measured_at' => ['sometimes', 'date'],
        ];
    }

    protected function makeReading(array $payload): ClinicalReading
    {
        return new OxygenSaturationReading(
            percentage: (float) $payload['spo2'],
            pulse: isset($payload['pulse']) ? (int) $payload['pulse'] : null,
        );
    }
}
