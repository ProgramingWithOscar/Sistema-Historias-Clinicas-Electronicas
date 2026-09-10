<?php

namespace App\Support\Interop\Fhir;

use App\Support\Interop\Contracts\ClinicalExchangeFactory;
use App\Support\Interop\Contracts\ExchangeEnvelope;
use App\Support\Interop\Contracts\ObservationSerializer;
use App\Support\Interop\Contracts\PatientSerializer;
use App\Support\Interop\ExchangeStandard;

/**
 * FÁBRICA CONCRETA: familia HL7 FHIR R4.
 *
 * Es el estándar que la Resolución 866 de 2021 exige para la interoperabilidad
 * de la HCE, y el formato en el que otro prestador puede importar la historia
 * sin transformaciones intermedias.
 */
final class FhirR4ExchangeFactory implements ClinicalExchangeFactory
{
    public function standard(): ExchangeStandard
    {
        return ExchangeStandard::FhirR4;
    }

    public function createPatientSerializer(): PatientSerializer
    {
        return new FhirPatientSerializer;
    }

    public function createObservationSerializer(): ObservationSerializer
    {
        return new FhirObservationSerializer;
    }

    public function createEnvelope(): ExchangeEnvelope
    {
        return new FhirBundleEnvelope;
    }
}
