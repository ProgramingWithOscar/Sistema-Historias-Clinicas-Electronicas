<?php

namespace App\Support\Interop\Fhir;

use App\Support\Interop\Contracts\ExchangeEnvelope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * PRODUCTO CONCRETO C (familia FHIR R4): `Bundle` de tipo `document`.
 *
 * Envuelve los recursos en el contenedor que define el estándar. Sólo tiene
 * sentido con recursos FHIR dentro: si recibiera el bloque `paciente` del RDA
 * produciría un Bundle sintácticamente válido pero clínicamente inservible.
 */
final class FhirBundleEnvelope implements ExchangeEnvelope
{
    private const BASE_URL = 'urn:uuid:';

    public function assemble(array $patient, array $observations, Carbon $generatedAt): array
    {
        $entradas = [$this->entry($patient)];

        foreach ($observations as $observacion) {
            $entradas[] = $this->entry($observacion);
        }

        return [
            'resourceType' => 'Bundle',
            'id' => (string) Str::uuid(),
            'type' => 'document',
            'timestamp' => $generatedAt->toIso8601String(),
            'total' => count($entradas),
            'entry' => $entradas,
        ];
    }

    public function mediaType(): string
    {
        return 'application/fhir+json';
    }

    public function filename(Carbon $generatedAt): string
    {
        return "hce-fhir-{$generatedAt->format('Ymd-His')}.json";
    }

    /**
     * @param  array<string, mixed>  $resource
     * @return array<string, mixed>
     */
    private function entry(array $resource): array
    {
        return [
            'fullUrl' => self::BASE_URL.Str::uuid(),
            'resource' => $resource,
        ];
    }
}
