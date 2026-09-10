<?php

namespace App\Support\Interop\Anonymized;

use App\Models\DeviceReading;
use App\Support\Interop\Contracts\ObservationSerializer;

/**
 * PRODUCTO CONCRETO B (familia anonimizada): registro disociado.
 *
 * Conserva el valor clínico —que es lo que da utilidad al estudio— pero elimina
 * la fecha exacta de medición, que combinada con otros registros permitiría
 * reidentificar al paciente. Se publica sólo el mes.
 *
 * Este serializador SÓLO es correcto acompañado del sujeto anonimizado de su
 * misma familia: emparejado con el paciente FHIR publicaría un dataset que dice
 * ser disociado llevando dentro nombre y documento.
 */
final class AnonymizedObservationSerializer implements ObservationSerializer
{
    public function serialize(DeviceReading $reading, string $patientReference): array
    {
        return [
            'seudonimo' => $patientReference,
            'codigoLoinc' => $reading->loinc_code,
            'observable' => $reading->display,
            'valor' => $reading->value,
            'unidad' => $reading->unit,
            'interpretacion' => $reading->severity->value,
            'origen' => $reading->device_type,
            'periodo' => $reading->measured_at?->format('Y-m'),
        ];
    }
}
