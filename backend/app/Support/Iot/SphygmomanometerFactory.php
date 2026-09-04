<?php

namespace App\Support\Iot;

use App\Support\Iot\Readings\BloodPressureReading;
use App\Support\Iot\Readings\ClinicalReading;

/** CREADOR CONCRETO: tensiómetro. */
final class SphygmomanometerFactory extends DeviceReadingFactory
{
    public function deviceType(): string
    {
        return 'sphygmomanometer';
    }

    public function payloadRules(): array
    {
        return [
            'systolic' => ['required', 'numeric', 'between:50,300'],
            'diastolic' => ['required', 'numeric', 'between:20,200', 'lt:systolic'],
            'pulse' => ['sometimes', 'integer', 'between:20,250'],
            'measured_at' => ['sometimes', 'date'],
        ];
    }

    protected function makeReading(array $payload): ClinicalReading
    {
        return new BloodPressureReading(
            systolic: (float) $payload['systolic'],
            diastolic: (float) $payload['diastolic'],
            pulse: isset($payload['pulse']) ? (int) $payload['pulse'] : null,
        );
    }
}
