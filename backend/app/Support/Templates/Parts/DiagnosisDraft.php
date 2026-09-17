<?php

namespace App\Support\Templates\Parts;

use App\Support\Encounters\Parts\Diagnosis;

/**
 * Diagnóstico propuesto por una plantilla.
 *
 * A diferencia de `Diagnosis` —que es inmutable porque ya forma parte de una
 * nota firmada— este borrador SÍ es mutable: el médico ajusta el código o marca
 * otro como principal antes de cerrar la atención.
 *
 * Precisamente por ser mutable es una de las piezas que obliga a copiar en
 * profundidad: si dos borradores compartieran el mismo objeto, cambiar el
 * diagnóstico principal en uno lo cambiaría también en el otro.
 */
final class DiagnosisDraft
{
    public function __construct(
        public string $code,
        public string $description,
        public bool $primary = false,
    ) {}

    /** Congela el borrador en el diagnóstico inmutable que va a la nota. */
    public function toDiagnosis(): Diagnosis
    {
        return new Diagnosis($this->code, $this->description, $this->primary);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'description' => $this->description,
            'primary' => $this->primary,
        ];
    }
}
