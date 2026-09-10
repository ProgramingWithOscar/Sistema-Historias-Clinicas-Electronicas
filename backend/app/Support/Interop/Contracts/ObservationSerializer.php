<?php

namespace App\Support\Interop\Contracts;

use App\Models\DeviceReading;

/**
 * PRODUCTO ABSTRACTO B: representación de una observación clínica.
 *
 * Recibe la lectura que ya normalizó el Factory Method (`device_readings`) y la
 * expresa en el formato de su familia, conservando siempre el código LOINC y la
 * unidad UCUM que exige el conjunto mínimo de datos de la Res. 866 de 2021.
 */
interface ObservationSerializer
{
    /**
     * @return array<string, mixed>
     */
    public function serialize(DeviceReading $reading, string $patientReference): array;
}
