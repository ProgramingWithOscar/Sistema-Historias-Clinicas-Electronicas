<?php

namespace App\Support\Interop\Contracts;

use Illuminate\Support\Carbon;

/**
 * PRODUCTO ABSTRACTO C: el sobre del documento.
 *
 * Es la pieza que ensambla paciente y observaciones en el documento que viaja
 * al prestador receptor: un `Bundle` de FHIR, el envoltorio del RDA o la
 * cabecera de un dataset de investigación.
 */
interface ExchangeEnvelope
{
    /**
     * @param  array<string, mixed>  $patient  salido del PatientSerializer de la misma familia
     * @param  list<array<string, mixed>>  $observations  salidas del ObservationSerializer de la misma familia
     * @return array<string, mixed>
     */
    public function assemble(array $patient, array $observations, Carbon $generatedAt): array;

    /** Media type con el que debe publicarse el documento. */
    public function mediaType(): string;

    /** Nombre sugerido del archivo exportado. */
    public function filename(Carbon $generatedAt): string;
}
