<?php

namespace App\Support\Interactions\Contracts;

use App\Support\Interactions\InteractionReport;

/**
 * TARGET del patrón Adapter (GoF).
 *
 * Ésta es la interfaz que el sistema *quiere* usar: se le pasan principios
 * activos por su nombre y devuelve un informe en el vocabulario del proyecto.
 *
 * Ninguna fuente real de datos de interacciones habla así. El API de la NLM
 * pide códigos RxCUI numéricos y responde en inglés con una estructura anidada;
 * el vademécum del prestador es un CSV que sólo sabe buscar un fármaco a la
 * vez. Cambiar cualquiera de las dos es imposible —no son nuestras—, y cambiar
 * el sistema para hablar como ellas significaría reescribirlo cada vez que se
 * cambie de proveedor.
 *
 * Por eso existe el adaptador: una clase por fuente que traduce en ambos
 * sentidos y hace que todas quepan por esta misma puerta.
 */
interface DrugInteractionChecker
{
    /**
     * Verifica el conjunto completo de principios activos.
     *
     * Recibe la lista entera y no pares sueltos a propósito: una interacción
     * puede aparecer sólo cuando concurren tres fármacos, y comprobarlos de dos
     * en dos desde fuera la ocultaría.
     *
     * @param  list<string>  $activeIngredients  nombres de principio activo
     */
    public function check(array $activeIngredients): InteractionReport;

    /** Identificador de la fuente, para poder trazar quién respondió qué. */
    public function source(): string;
}
