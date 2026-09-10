<?php

namespace App\Support\Interop\Contracts;

use App\Models\User;

/**
 * PRODUCTO ABSTRACTO A: representación del paciente.
 *
 * Cada familia lo resuelve a su manera —un recurso `Patient` de FHIR, el bloque
 * `paciente` del RDA o un seudónimo sin datos identificables— pero el cliente
 * siempre ve esta misma interfaz.
 */
interface PatientSerializer
{
    /**
     * Convierte al paciente a la representación de la familia.
     *
     * @return array<string, mixed>
     */
    public function serialize(User $patient): array;

    /**
     * Referencia con la que las observaciones de ESTA MISMA familia apuntan al
     * paciente (`Patient/12` en FHIR, el número de documento en el RDA, el
     * seudónimo en el conjunto anonimizado).
     *
     * Es el punto donde se ve por qué la familia no puede mezclarse: una
     * observación FHIR que apuntara al seudónimo anonimizado sería un documento
     * inválido, y una observación identificada dentro de un dataset disociado
     * sería una fuga de datos sensibles.
     */
    public function reference(User $patient): string;
}
