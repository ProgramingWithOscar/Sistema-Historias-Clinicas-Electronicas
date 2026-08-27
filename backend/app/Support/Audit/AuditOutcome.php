<?php

namespace App\Support\Audit;

/**
 * Resultado de la operación auditada.
 *
 * Se guarda en su propia columna en lugar de deducirlo del sufijo del `action`
 * (`.failed`, `.succeeded`): así se puede consultar e indexar directamente
 * —"todos los fallos de la última hora"— sin depender de una convención de
 * nombres que cualquiera puede romper al añadir un evento nuevo.
 */
enum AuditOutcome: string
{
    /** La operación se completó. */
    case Success = 'success';

    /** La operación se rechazó: credenciales inválidas, validación, etc. */
    case Failure = 'failure';

    /** Bloqueo preventivo: límite de intentos, permisos insuficientes. */
    case Denied = 'denied';

    public function label(): string
    {
        return match ($this) {
            self::Success => 'Exitosa',
            self::Failure => 'Fallida',
            self::Denied => 'Bloqueada',
        };
    }
}
