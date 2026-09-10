<?php

namespace App\Support\Interop\Fhir;

use App\Models\User;
use App\Support\Interop\Contracts\PatientSerializer;

/**
 * PRODUCTO CONCRETO A (familia FHIR R4): recurso `Patient`.
 *
 * Publica la identidad del paciente como `identifier` con el sistema de
 * identificación nacional, tal como espera un servidor FHIR colombiano.
 */
final class FhirPatientSerializer implements PatientSerializer
{
    /** Sistema de identificación de personas del MinSalud. */
    private const IDENTIFIER_SYSTEM = 'https://interoperabilidad.minsalud.gov.co/fhir/sid/documento';

    public function serialize(User $patient): array
    {
        [$given, $family] = $this->splitName($patient->name);

        return array_filter([
            'resourceType' => 'Patient',
            'id' => (string) $patient->id,
            'active' => true,
            'identifier' => [[
                'system' => self::IDENTIFIER_SYSTEM,
                'value' => $patient->document_number,
                'type' => ['text' => $patient->document_type],
            ]],
            'name' => [[
                'use' => 'official',
                'text' => $patient->name,
                'family' => $family,
                'given' => $given,
            ]],
            'telecom' => [[
                'system' => 'email',
                'value' => $patient->email,
            ]],
            'birthDate' => $patient->birth_date?->toDateString(),
        ]);
    }

    public function reference(User $patient): string
    {
        // Referencia relativa: así la resuelve el receptor dentro del Bundle.
        return "Patient/{$patient->id}";
    }

    /**
     * FHIR separa nombres y apellidos; el sistema los guarda en un solo campo,
     * así que se parte por la mitad y se deja el texto completo en `name.text`
     * para no perder información.
     *
     * @return array{0: list<string>, 1: string}
     */
    private function splitName(string $name): array
    {
        $partes = preg_split('/\s+/', trim($name)) ?: [];

        if (count($partes) <= 1) {
            return [$partes, ''];
        }

        $mitad = (int) ceil(count($partes) / 2);

        return [array_slice($partes, 0, $mitad), implode(' ', array_slice($partes, $mitad))];
    }
}
