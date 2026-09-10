<?php

namespace App\Support\Interop\Rda;

use App\Support\Interop\Contracts\ExchangeEnvelope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * PRODUCTO CONCRETO C (familia RDA): envoltorio del Resumen Digital de Atención.
 *
 * Incluye la identificación del prestador que genera el resumen, que es dato
 * obligatorio del RDA y no existe en el Bundle de FHIR: otra prueba de que los
 * sobres de dos familias no son intercambiables.
 */
final class RdaDocumentEnvelope implements ExchangeEnvelope
{
    public function assemble(array $patient, array $observations, Carbon $generatedAt): array
    {
        return [
            'resumenDigitalAtencion' => [
                'identificador' => (string) Str::uuid(),
                'version' => '1.0',
                'normaAplicable' => 'Resolución 1888 de 2025',
                'fechaGeneracion' => $generatedAt->toIso8601String(),
                'prestador' => [
                    'nombre' => config('app.name'),
                    'codigoHabilitacion' => config('interop.rda.codigo_habilitacion'),
                ],
                'paciente' => $patient,
                'hallazgos' => $observations,
                'totalHallazgos' => count($observations),
            ],
        ];
    }

    public function mediaType(): string
    {
        return 'application/json';
    }

    public function filename(Carbon $generatedAt): string
    {
        return "rda-{$generatedAt->format('Ymd-His')}.json";
    }
}
