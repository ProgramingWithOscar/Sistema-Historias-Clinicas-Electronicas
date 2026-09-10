<?php

namespace App\Support\Interop\Rda;

use App\Models\DeviceReading;
use App\Support\Interop\Contracts\ObservationSerializer;

/**
 * PRODUCTO CONCRETO B (familia RDA): item de `hallazgos`.
 *
 * Mantiene el código LOINC y la unidad UCUM —el conjunto mínimo sigue siendo
 * obligatorio— pero con las etiquetas en español del resumen nacional y la
 * severidad ya traducida a la escala que entiende el personal asistencial.
 */
final class RdaObservationSerializer implements ObservationSerializer
{
    public function serialize(DeviceReading $reading, string $patientReference): array
    {
        return [
            'documentoPaciente' => $patientReference,
            'codigoLoinc' => $reading->loinc_code,
            'descripcion' => $reading->display,
            'valor' => $reading->value,
            'unidad' => $reading->unit,
            'interpretacion' => $reading->severity->label(),
            'requiereAtencion' => $reading->severity->requiresAttention(),
            'origen' => $reading->device_type,
            'fechaMedicion' => $reading->measured_at?->toIso8601String(),
            'datosAdicionales' => $reading->components ?? [],
        ];
    }
}
