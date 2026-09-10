<?php

namespace App\Support\Interop\Rda;

use App\Models\User;
use App\Support\Interop\Contracts\PatientSerializer;

/**
 * PRODUCTO CONCRETO A (familia RDA): bloque `paciente`.
 *
 * El Resumen Digital de Atención usa nomenclatura en español y los catálogos
 * nacionales de tipo de documento, no los recursos de FHIR.
 */
final class RdaPatientSerializer implements PatientSerializer
{
    public function serialize(User $patient): array
    {
        return [
            'tipoDocumento' => $patient->document_type ?? 'CC',
            'numeroDocumento' => $patient->document_number,
            'nombreCompleto' => $patient->name,
            'fechaNacimiento' => $patient->birth_date?->toDateString(),
            'edad' => $patient->age(),
            'correoElectronico' => $patient->email,
        ];
    }

    public function reference(User $patient): string
    {
        // El RDA no usa referencias tipo recurso: identifica al paciente por
        // documento, y así es como lo repiten sus observaciones.
        return ($patient->document_type ?? 'CC').'-'.$patient->document_number;
    }
}
