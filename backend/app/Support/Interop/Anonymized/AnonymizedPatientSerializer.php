<?php

namespace App\Support\Interop\Anonymized;

use App\Models\User;
use App\Support\Interop\Contracts\PatientSerializer;

/**
 * PRODUCTO CONCRETO A (familia anonimizada): sujeto disociado.
 *
 * No publica nombre, documento, correo ni fecha de nacimiento: sólo un
 * seudónimo estable y el grupo etario. Es el tratamiento que la Ley 1581 de
 * 2012 permite para datos sensibles con fines de investigación y estadística.
 *
 * El seudónimo es estable —el mismo paciente produce el mismo código entre
 * exportaciones, lo que permite estudios longitudinales— pero no reversible,
 * porque el hash lleva una sal que no sale del servidor.
 */
final class AnonymizedPatientSerializer implements PatientSerializer
{
    public function serialize(User $patient): array
    {
        return [
            'seudonimo' => $this->reference($patient),
            'grupoEtario' => $this->ageBand($patient->age()),
            'disociado' => true,
        ];
    }

    public function reference(User $patient): string
    {
        $salt = (string) config('interop.anonymized.salt');

        return 'SUJ-'.substr(hash('sha256', $salt.'|'.$patient->id), 0, 16);
    }

    /**
     * La edad exacta puede reidentificar a un paciente en una cohorte pequeña,
     * así que se publica agrupada en quinquenios.
     */
    private function ageBand(?int $age): string
    {
        if ($age === null) {
            return 'desconocido';
        }

        // Techo de 90 años: por encima quedan tan pocos casos que el dato
        // volvería a ser identificador.
        if ($age >= 90) {
            return '90+';
        }

        $inicio = intdiv($age, 5) * 5;

        return $inicio.'-'.($inicio + 4);
    }
}
