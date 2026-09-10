<?php

namespace App\Support\Interop\Anonymized;

use App\Support\Interop\Contracts\ClinicalExchangeFactory;
use App\Support\Interop\Contracts\ExchangeEnvelope;
use App\Support\Interop\Contracts\ObservationSerializer;
use App\Support\Interop\Contracts\PatientSerializer;
use App\Support\Interop\ExchangeStandard;

/**
 * FÁBRICA CONCRETA: familia de exportación disociada.
 *
 * Es la que demuestra con más claridad para qué sirve el Abstract Factory. La
 * anonimización no es una propiedad de un objeto suelto sino del documento
 * entero: no basta con ocultar el nombre del paciente si las observaciones
 * siguen llevando la fecha exacta de medición, ni con agrupar la edad si el
 * sobre no declara la base legal del tratamiento.
 *
 * Al venir los tres productos de esta única fábrica, la garantía de disociación
 * se sostiene en el tipo y no en que alguien recuerde aplicarla en cada capa.
 */
final class AnonymizedExchangeFactory implements ClinicalExchangeFactory
{
    public function standard(): ExchangeStandard
    {
        return ExchangeStandard::Anonymized;
    }

    public function createPatientSerializer(): PatientSerializer
    {
        return new AnonymizedPatientSerializer;
    }

    public function createObservationSerializer(): ObservationSerializer
    {
        return new AnonymizedObservationSerializer;
    }

    public function createEnvelope(): ExchangeEnvelope
    {
        return new AnonymizedDatasetEnvelope;
    }
}
