<?php

namespace App\Support\Iot;

use App\Support\Iot\Readings\ClinicalReading;
use App\Support\Iot\Readings\GlucoseReading;

/** CREADOR CONCRETO: glucómetro. */
final class GlucometerFactory extends DeviceReadingFactory
{
    public function deviceType(): string
    {
        return 'glucometer';
    }

    public function payloadRules(): array
    {
        return [
            'mg_dl' => ['required', 'numeric', 'between:10,900'],
            'fasting' => ['sometimes', 'boolean'],
            'measured_at' => ['sometimes', 'date'],
        ];
    }

    protected function makeReading(array $payload): ClinicalReading
    {
        return new GlucoseReading(
            mgPerDl: (float) $payload['mg_dl'],
            fasting: (bool) ($payload['fasting'] ?? false),
        );
    }
}
