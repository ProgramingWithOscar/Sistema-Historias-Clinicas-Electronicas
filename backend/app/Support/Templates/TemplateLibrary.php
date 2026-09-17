<?php

namespace App\Support\Templates;

use App\Models\ClinicalTemplate;

/**
 * Compone el registro de prototipos: las plantillas institucionales del código
 * más las que hayan guardado los profesionales.
 *
 * Existe para que `TemplateRegistry` no dependa de Eloquent —así puede probarse
 * sin base de datos— y para que controlador y pruebas obtengan el catálogo
 * completo con una sola llamada.
 */
final class TemplateLibrary
{
    public function registry(): TemplateRegistry
    {
        $registry = new TemplateRegistry;

        foreach (ClinicalTemplate::all() as $guardada) {
            $registry->register($guardada->toPrototype());
        }

        return $registry;
    }
}
