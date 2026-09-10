<?php

namespace App\Support\Interop\Fhir;

use App\Models\DeviceReading;
use App\Support\Interop\Contracts\ObservationSerializer;
use App\Support\Iot\Readings\ReadingSeverity;

/**
 * PRODUCTO CONCRETO B (familia FHIR R4): recurso `Observation`.
 *
 * Traduce la lectura ya normalizada por el Factory Method al recurso que el
 * estándar define para signos vitales, con su `valueQuantity` en UCUM y su
 * `interpretation` en el vocabulario v3-ObservationInterpretation.
 */
final class FhirObservationSerializer implements ObservationSerializer
{
    private const LOINC_SYSTEM = 'http://loinc.org';

    private const UCUM_SYSTEM = 'http://unitsofmeasure.org';

    private const INTERPRETATION_SYSTEM = 'http://terminology.hl7.org/CodeSystem/v3-ObservationInterpretation';

    public function serialize(DeviceReading $reading, string $patientReference): array
    {
        return array_filter([
            'resourceType' => 'Observation',
            'id' => (string) $reading->id,
            'status' => 'final',
            'category' => [[
                'coding' => [[
                    'system' => 'http://terminology.hl7.org/CodeSystem/observation-category',
                    'code' => 'vital-signs',
                    'display' => 'Vital Signs',
                ]],
            ]],
            'code' => [
                'coding' => [[
                    'system' => self::LOINC_SYSTEM,
                    'code' => $reading->loinc_code,
                    'display' => $reading->display,
                ]],
                'text' => $reading->display,
            ],
            'subject' => ['reference' => $patientReference],
            'device' => ['display' => $reading->device_type],
            'effectiveDateTime' => $reading->measured_at?->toIso8601String(),
            'valueQuantity' => $this->quantity($reading->value, $reading->unit),
            'interpretation' => [[
                'coding' => [$this->interpretation($reading->severity)],
            ]],
            'component' => $this->components($reading),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function quantity(float $value, string $unit): array
    {
        return [
            'value' => $value,
            'unit' => $unit,
            'system' => self::UCUM_SYSTEM,
            'code' => $unit,
        ];
    }

    /**
     * La severidad que calculó el producto del Factory Method se traduce al
     * vocabulario de interpretación de HL7: N (normal), A (anormal) y HH
     * (crítico), que es lo que el receptor sabe leer.
     *
     * @return array<string, string>
     */
    private function interpretation(ReadingSeverity $severity): array
    {
        [$code, $display] = match ($severity) {
            ReadingSeverity::Normal => ['N', 'Normal'],
            ReadingSeverity::Warning => ['A', 'Abnormal'],
            ReadingSeverity::Critical => ['HH', 'Critical high'],
        };

        return ['system' => self::INTERPRETATION_SYSTEM, 'code' => $code, 'display' => $display];
    }

    /**
     * Los datos propios de cada dispositivo (diastólica, pulso, ayuno) viajan
     * como `component`, que es donde FHIR admite las medidas acompañantes.
     *
     * @return list<array<string, mixed>>
     */
    private function components(DeviceReading $reading): array
    {
        $mapa = [
            'diastolic' => ['8462-4', 'Presión diastólica', 'mm[Hg]'],
            'pulse' => ['8867-4', 'Frecuencia cardiaca', '/min'],
            'fasting' => ['49541-6', 'Estado de ayuno', null],
        ];

        $componentes = [];

        foreach ($reading->components ?? [] as $clave => $valor) {
            if (! isset($mapa[$clave]) || $valor === null) {
                continue;
            }

            [$codigo, $descripcion, $unidad] = $mapa[$clave];

            $componentes[] = [
                'code' => [
                    'coding' => [[
                        'system' => self::LOINC_SYSTEM,
                        'code' => $codigo,
                        'display' => $descripcion,
                    ]],
                ],
                ...$unidad === null
                    ? ['valueBoolean' => (bool) $valor]
                    : ['valueQuantity' => $this->quantity((float) $valor, $unidad)],
            ];
        }

        return $componentes;
    }
}
