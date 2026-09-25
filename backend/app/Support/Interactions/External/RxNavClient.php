<?php

namespace App\Support\Interactions\External;

use Illuminate\Support\Facades\Http;

/**
 * ADAPTEE 1: cliente del servicio de interacciones de la NLM (RxNav).
 *
 * Representa el código que NO controlamos. Su interfaz es la que el proveedor
 * decidió, y refleja fielmente la del API real:
 *
 * - pide **códigos RxCUI numéricos**, no nombres de principio activo;
 * - devuelve una estructura anidada en inglés (`fullInteractionTypeGroup` →
 *   `fullInteractionType` → `interactionPair`);
 * - clasifica la gravedad con las cadenas `high`, `moderate`, `low`.
 *
 * Deliberadamente NO implementa `DrugInteractionChecker`: si lo hiciera ya no
 * haría falta adaptador. El caso realista es justo éste —una clase ajena que
 * no puedes tocar—, y por eso se deja tal cual y se envuelve.
 *
 * Nota de alcance: en este proyecto la clase apunta a la URL del API pero se
 * ejercita con `Http::fake()` en las pruebas; lo que se demuestra es el patrón,
 * no la conectividad con la NLM.
 */
final class RxNavClient
{
    private const BASE_URL = 'https://rxnav.nlm.nih.gov/REST';

    /**
     * Firma del proveedor: lista de códigos, respuesta cruda como arreglo.
     *
     * @param  list<int>  $rxcuis
     * @return array<string, mixed>
     */
    public function findInteractionsFromList(array $rxcuis): array
    {
        if (count($rxcuis) < 2) {
            return [];
        }

        $response = Http::timeout(config('interactions.rxnav.timeout'))
            ->acceptJson()
            ->get(self::BASE_URL.'/interaction/list.json', [
                'rxcuis' => implode('+', $rxcuis),
            ]);

        return $response->successful() ? $response->json() ?? [] : [];
    }
}
