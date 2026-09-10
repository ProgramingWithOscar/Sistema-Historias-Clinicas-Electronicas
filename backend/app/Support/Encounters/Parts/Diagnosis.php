<?php

namespace App\Support\Encounters\Parts;

/**
 * Diagnóstico codificado en CIE-10.
 *
 * Es una de las "partes" que el builder va acumulando. Se modela como objeto de
 * valor inmutable para que, una vez añadido a la nota, nadie pueda alterarlo:
 * cambiar un diagnóstico ya firmado es enmendar la historia clínica.
 */
final class Diagnosis
{
    public function __construct(
        public readonly string $code,
        public readonly string $description,
        public readonly bool $primary = false,
    ) {}

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
