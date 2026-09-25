<?php

namespace App\Support\Interactions\Adapters;

use App\Support\Interactions\Contracts\DrugInteractionChecker;
use App\Support\Interactions\External\VademecumNacionalReader;
use App\Support\Interactions\InteractionReport;
use App\Support\Interactions\InteractionSeverity;
use App\Support\Interactions\Parts\DrugInteraction;
use Illuminate\Support\Carbon;

/**
 * ADAPTADOR del vademécum institucional.
 *
 * El desajuste que resuelve es el del **flujo de la consulta**: el sistema
 * pregunta por un conjunto de fármacos de una vez, pero el lector del CSV sólo
 * sabe buscar de uno en uno y devuelve sus filas crudas, que pueden referirse a
 * fármacos que el paciente ni siquiera está tomando.
 *
 * El adaptador recorre la lista, descarta las filas cuyo otro extremo no esté
 * en la fórmula y elimina los duplicados —cada par aparece dos veces, una por
 * cada fármaco consultado—.
 *
 * Usa COMPOSICIÓN: recibe el lector ya construido en lugar de heredar de él.
 * Es la variante recomendada del patrón —adaptador de objeto—, porque permite
 * envolver una clase `final`, cambiar la instancia en pruebas y no arrastrar la
 * interfaz del adaptee.
 */
final class VademecumInteractionAdapter implements DrugInteractionChecker
{
    public function __construct(
        private readonly VademecumNacionalReader $vademecum,
    ) {}

    public function source(): string
    {
        return 'vademecum_nacional';
    }

    public function check(array $activeIngredients): InteractionReport
    {
        $enFormula = array_map(
            fn (string $p) => $this->vademecum->normalizar($p),
            $activeIngredients,
        );

        $encontradas = [];

        foreach ($activeIngredients as $principio) {
            foreach ($this->vademecum->buscarPorPrincipio($principio) as $fila) {
                $a = $this->vademecum->normalizar($fila['principio_a']);
                $b = $this->vademecum->normalizar($fila['principio_b']);

                // El CSV conoce interacciones con medio mundo: sólo interesan
                // las que involucran a dos fármacos de ESTA fórmula.
                if (! in_array($a, $enFormula, true) || ! in_array($b, $enFormula, true)) {
                    continue;
                }

                $interaccion = new DrugInteraction(
                    drugA: $fila['principio_a'],
                    drugB: $fila['principio_b'],
                    severity: $this->traducirGravedad($fila['gravedad']),
                    description: $fila['descripcion'],
                    source: $this->source(),
                );

                // Cada par sale dos veces, una por cada extremo consultado.
                $encontradas[$interaccion->pairKey()] = $interaccion;
            }
        }

        return new InteractionReport(
            interactions: array_values($encontradas),
            checkedDrugs: $activeIngredients,
            source: $this->source(),
            checkedAt: Carbon::now(),
        );
    }

    /**
     * La escala del CSV ya coincide con la del sistema, pero se traduce
     * igualmente: si mañana el proveedor cambia sus etiquetas, el cambio se
     * absorbe aquí y no en el resto del código.
     */
    private function traducirGravedad(string $gravedad): InteractionSeverity
    {
        return match (mb_strtolower(trim($gravedad))) {
            'contraindicada', 'contraindicado' => InteractionSeverity::Contraindicada,
            'grave', 'alta' => InteractionSeverity::Grave,
            'moderada', 'media' => InteractionSeverity::Moderada,
            default => InteractionSeverity::Leve,
        };
    }
}
