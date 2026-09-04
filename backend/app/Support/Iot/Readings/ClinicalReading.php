<?php

namespace App\Support\Iot\Readings;

/**
 * PRODUCTO del patrón Factory Method.
 *
 * Es el contrato que el creador (`DeviceReadingFactory`) conoce y manipula.
 * Gracias a él, el flujo de ingesta trabaja siempre contra la misma abstracción
 * —una lectura clínica normalizada— sin saber si detrás hay un glucómetro, un
 * tensiómetro o un oxímetro.
 *
 * La normalización es el requisito de interoperabilidad de la HCEI: cada lectura
 * se expone con su código LOINC, su valor y su unidad, tal como exige la
 * Resolución 866 de 2021 para el conjunto mínimo de datos clínicos.
 */
interface ClinicalReading
{
    /** Código LOINC del observable (vocabulario exigido por la Res. 866/2021). */
    public function loincCode(): string;

    /** Nombre legible del observable, para mostrar en la historia clínica. */
    public function display(): string;

    /** Valor principal de la observación. */
    public function value(): float;

    /** Unidad UCUM del valor principal (mg/dL, mm[Hg], %). */
    public function unit(): string;

    /** Interpretación clínica del valor, calculada por el propio producto. */
    public function severity(): ReadingSeverity;

    /**
     * Datos adicionales propios de cada tipo de lectura (la diastólica de una
     * presión arterial, si la glucemia fue en ayunas, la frecuencia de pulso…).
     *
     * @return array<string, mixed>
     */
    public function components(): array;
}
