<?php

namespace App\Support\Templates;

/**
 * PROTOTIPO del patrón Prototype (GoF).
 *
 * Declara la única operación del patrón: producir una copia independiente de un
 * objeto ya configurado, sin que quien la pide conozca su clase concreta ni
 * tenga que reconstruirlo paso a paso.
 *
 * Se declara explícitamente en vez de confiar en el `clone` de PHP porque el
 * `clone` nativo es SUPERFICIAL: copia el objeto pero no lo que hay dentro. En
 * una historia clínica eso no es un matiz académico —dos borradores compartiendo
 * el mismo objeto de prescripción significa que ajustar la dosis de un paciente
 * cambia la del siguiente—, así que cada prototipo debe declarar qué implica
 * copiarlo.
 */
interface ClinicalPrototype
{
    /**
     * Devuelve una copia profunda e independiente de este prototipo.
     *
     * Independiente quiere decir que ninguna modificación posterior sobre la
     * copia puede alcanzar al original, ni al revés.
     */
    public function copy(): static;
}
