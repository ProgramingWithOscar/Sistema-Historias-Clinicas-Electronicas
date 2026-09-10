<?php

namespace App\Support\Interop;

/**
 * Estándares de intercambio que sabe producir el sistema.
 *
 * Cada caso identifica una FAMILIA COMPLETA de productos —serializador de
 * paciente, serializador de observaciones y sobre del documento— no un producto
 * suelto. Ésa es la diferencia con el `device_type` del Factory Method: allí el
 * dato en runtime elige *un* objeto; aquí elige *un conjunto* de objetos que
 * tienen que ser compatibles entre sí.
 */
enum ExchangeStandard: string
{
    /** HL7 FHIR R4: exigido por la Res. 866 de 2021 para la HCEI. */
    case FhirR4 = 'fhir_r4';

    /** Resumen Digital de Atención en Salud (Res. 1888 de 2025). */
    case Rda = 'rda';

    /** Conjunto disociado para investigación y estadística (Ley 1581 de 2012). */
    case Anonymized = 'anonymized';

    public function label(): string
    {
        return match ($this) {
            self::FhirR4 => 'HL7 FHIR R4',
            self::Rda => 'Resumen Digital de Atención',
            self::Anonymized => 'Conjunto anonimizado',
        };
    }

    /** Norma que respalda el uso de esta familia. */
    public function legalBasis(): string
    {
        return match ($this) {
            self::FhirR4 => 'Resolución 866 de 2021',
            self::Rda => 'Resolución 1888 de 2025',
            self::Anonymized => 'Ley 1581 de 2012, art. 5 y 6',
        };
    }

    /** ¿La familia publica datos que identifican al paciente? */
    public function identifiesPatient(): bool
    {
        return $this !== self::Anonymized;
    }
}
