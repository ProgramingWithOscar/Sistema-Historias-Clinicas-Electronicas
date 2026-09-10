<?php

namespace App\Support\Interop\Contracts;

use App\Support\Interop\ExchangeStandard;

/**
 * FÁBRICA ABSTRACTA del patrón Abstract Factory (GoF).
 *
 * Declara un método de creación por cada producto de la familia. Una fábrica
 * concreta —FHIR, RDA o anonimizada— implementa los tres, y al hacerlo GARANTIZA
 * que los objetos que devuelve están hechos para trabajar juntos.
 *
 * Ésa es la razón de ser del patrón y lo que lo separa del Factory Method: aquí
 * el problema no es "no sé qué objeto crear", sino "no puedo permitir que se
 * mezclen objetos de familias distintas". Un recurso `Patient` de FHIR dentro
 * del envoltorio del RDA produce un documento que el receptor rechaza; peor
 * aún, un paciente identificado dentro de un dataset de investigación es una
 * violación del tratamiento de datos sensibles (Ley 1581 de 2012).
 *
 * Al obligar a pedir los tres productos a la MISMA fábrica, la mezcla deja de
 * ser posible por construcción, no por disciplina del programador.
 */
interface ClinicalExchangeFactory
{
    /** Estándar que representa esta familia. */
    public function standard(): ExchangeStandard;

    /** Crea el producto A de la familia. */
    public function createPatientSerializer(): PatientSerializer;

    /** Crea el producto B de la familia. */
    public function createObservationSerializer(): ObservationSerializer;

    /** Crea el producto C de la familia. */
    public function createEnvelope(): ExchangeEnvelope;
}
