<?php

namespace App\Support\Interop\Rda;

use App\Support\Interop\Contracts\ClinicalExchangeFactory;
use App\Support\Interop\Contracts\ExchangeEnvelope;
use App\Support\Interop\Contracts\ObservationSerializer;
use App\Support\Interop\Contracts\PatientSerializer;
use App\Support\Interop\ExchangeStandard;

/**
 * FÁBRICA CONCRETA: familia Resumen Digital de Atención (Res. 1888 de 2025).
 *
 * Es el mecanismo con el que Colombia implementa la interoperabilidad de la HCE
 * a nivel nacional, y el formato que espera el nodo del MinSalud.
 */
final class RdaExchangeFactory implements ClinicalExchangeFactory
{
    public function standard(): ExchangeStandard
    {
        return ExchangeStandard::Rda;
    }

    public function createPatientSerializer(): PatientSerializer
    {
        return new RdaPatientSerializer;
    }

    public function createObservationSerializer(): ObservationSerializer
    {
        return new RdaObservationSerializer;
    }

    public function createEnvelope(): ExchangeEnvelope
    {
        return new RdaDocumentEnvelope;
    }
}
