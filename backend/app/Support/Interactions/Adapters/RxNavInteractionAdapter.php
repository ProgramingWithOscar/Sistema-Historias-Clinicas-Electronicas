<?php

namespace App\Support\Interactions\Adapters;

use App\Support\Interactions\Contracts\DrugInteractionChecker;
use App\Support\Interactions\External\RxNavClient;
use App\Support\Interactions\InteractionReport;
use App\Support\Interactions\InteractionSeverity;
use App\Support\Interactions\Parts\DrugInteraction;
use Illuminate\Support\Carbon;

/**
 * ADAPTADOR del servicio de interacciones de la NLM (RxNav).
 *
 * Es el que mejor muestra para qué sirve el patrón, porque tiene que salvar
 * los tres desajustes a la vez:
 *
 * 1. **Los argumentos.** El sistema habla de «Losartán»; el API sólo entiende
 *    el código RxCUI 52175. El adaptador traduce la entrada.
 * 2. **El nombre y la forma de la llamada.** `check()` frente a
 *    `findInteractionsFromList()`.
 * 3. **La respuesta.** Llega una estructura anidada en inglés
 *    (`fullInteractionTypeGroup` → `fullInteractionType` → `interactionPair`)
 *    con gravedades `high` / `moderate` / `low`, y tiene que salir como un
 *    `InteractionReport` con la escala del proyecto.
 *
 * Nada de esto asoma fuera de esta clase: el resto del sistema sigue viendo
 * únicamente `DrugInteractionChecker`.
 */
final class RxNavInteractionAdapter implements DrugInteractionChecker
{
    public function __construct(
        private readonly RxNavClient $rxnav,
    ) {}

    public function source(): string
    {
        return 'rxnav_nlm';
    }

    public function check(array $activeIngredients): InteractionReport
    {
        // 1. Traducir la ENTRADA: nombres → códigos RxCUI.
        $porCodigo = [];

        foreach ($activeIngredients as $principio) {
            $codigo = $this->aRxcui($principio);

            if ($codigo !== null) {
                $porCodigo[$codigo] = $principio;
            }
        }

        // Un fármaco que la fuente no conoce no puede interactuar con nada:
        // se informa vacío en vez de fingir que no hay riesgo.
        if (count($porCodigo) < 2) {
            return InteractionReport::empty($activeIngredients, $this->source());
        }

        // 2. Llamar al adaptee con SU firma.
        $crudo = $this->rxnav->findInteractionsFromList(array_keys($porCodigo));

        // 3. Traducir la SALIDA a nuestro vocabulario.
        return new InteractionReport(
            interactions: $this->aInteracciones($crudo, $porCodigo),
            checkedDrugs: $activeIngredients,
            source: $this->source(),
            checkedAt: Carbon::now(),
        );
    }

    /** Nombre de principio activo → código RxCUI. */
    private function aRxcui(string $principio): ?int
    {
        $clave = strtr(mb_strtolower(trim($principio)), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        return config("interactions.rxnav.rxcui.{$clave}");
    }

    /**
     * Desanida la respuesta del proveedor.
     *
     * @param  array<string, mixed>  $crudo
     * @param  array<int, string>  $porCodigo  RxCUI → nombre tal como lo escribió el profesional
     * @return list<DrugInteraction>
     */
    private function aInteracciones(array $crudo, array $porCodigo): array
    {
        $encontradas = [];

        foreach ($crudo['fullInteractionTypeGroup'] ?? [] as $grupo) {
            foreach ($grupo['fullInteractionType'] ?? [] as $tipo) {
                foreach ($tipo['interactionPair'] ?? [] as $par) {
                    $conceptos = $par['interactionConcept'] ?? [];

                    if (count($conceptos) < 2) {
                        continue;
                    }

                    $interaccion = new DrugInteraction(
                        drugA: $this->nombreLocal($conceptos[0], $porCodigo),
                        drugB: $this->nombreLocal($conceptos[1], $porCodigo),
                        severity: $this->traducirGravedad($par['severity'] ?? ''),
                        description: trim($par['description'] ?? 'Interacción reportada sin descripción.'),
                        source: $this->source(),
                    );

                    $encontradas[$interaccion->pairKey()] = $interaccion;
                }
            }
        }

        return array_values($encontradas);
    }

    /**
     * Devuelve el fármaco con el nombre que usó el profesional, no con el que
     * trae el proveedor: la alerta debe hablar de «Losartán», no de
     * «losartan 50 MG Oral Tablet».
     *
     * @param  array<string, mixed>  $concepto
     * @param  array<int, string>  $porCodigo
     */
    private function nombreLocal(array $concepto, array $porCodigo): string
    {
        $item = $concepto['minConceptItem'] ?? [];
        $rxcui = (int) ($item['rxcui'] ?? 0);

        return $porCodigo[$rxcui] ?? ($item['name'] ?? 'Desconocido');
    }

    /** Escala del proveedor → escala del sistema. */
    private function traducirGravedad(string $severity): InteractionSeverity
    {
        return match (mb_strtolower(trim($severity))) {
            'contraindicated' => InteractionSeverity::Contraindicada,
            'high' => InteractionSeverity::Grave,
            'moderate' => InteractionSeverity::Moderada,
            'low', 'minor' => InteractionSeverity::Leve,
            // Una gravedad que no sabemos leer se trata como moderada: en
            // seguridad del paciente, el silencio es peor que una alerta de más.
            default => InteractionSeverity::Moderada,
        };
    }
}
