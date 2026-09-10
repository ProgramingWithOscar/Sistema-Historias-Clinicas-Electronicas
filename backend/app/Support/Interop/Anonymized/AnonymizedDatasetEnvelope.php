<?php

namespace App\Support\Interop\Anonymized;

use App\Support\Interop\Contracts\ExchangeEnvelope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * PRODUCTO CONCRETO C (familia anonimizada): cabecera del dataset.
 *
 * Declara explícitamente la finalidad y la base legal del tratamiento, que es
 * lo que exige la Ley 1581 de 2012 para poder ceder datos de salud sin
 * autorización individual.
 */
final class AnonymizedDatasetEnvelope implements ExchangeEnvelope
{
    public function assemble(array $patient, array $observations, Carbon $generatedAt): array
    {
        return [
            'dataset' => [
                'identificador' => (string) Str::uuid(),
                'finalidad' => 'investigacion-y-estadistica',
                'baseLegal' => 'Ley 1581 de 2012, art. 5 y 6 (datos disociados)',
                'contieneDatosIdentificables' => false,
                'fechaGeneracion' => $generatedAt->toDateString(),
                'sujeto' => $patient,
                'registros' => $observations,
                'totalRegistros' => count($observations),
            ],
        ];
    }

    public function mediaType(): string
    {
        return 'application/json';
    }

    public function filename(Carbon $generatedAt): string
    {
        return "dataset-anonimizado-{$generatedAt->format('Ymd')}.json";
    }
}
