<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInteractionCheckRequest;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Interactions\InteractionCheckerResolver;
use Illuminate\Http\JsonResponse;

/**
 * Verificación de interacciones medicamentosas.
 *
 * El controlador no sabe si detrás hay un API en inglés con códigos RxCUI o un
 * CSV en español: pide el verificador que corresponde a la fuente y programa
 * contra `DrugInteractionChecker`. Cambiar de proveedor no toca este archivo.
 */
class InteractionCheckController extends Controller
{
    public function __construct(
        private readonly InteractionCheckerResolver $resolver,
    ) {}

    /** Fuentes disponibles y de dónde salen sus datos. */
    public function sources(): JsonResponse
    {
        return response()->json(['data' => InteractionCheckerResolver::catalog()]);
    }

    public function store(StoreInteractionCheckRequest $request): JsonResponse
    {
        $checker = $this->resolver->for($request->input('source'));

        $report = $checker->check($request->array('drugs'));

        AuditLogger::getInstance()->record(
            action: 'hce.interaction.checked',
            actorId: $request->user()?->id,
            subjectType: $request->filled('patient_id') ? User::class : null,
            subjectId: $request->filled('patient_id') ? $request->integer('patient_id') : null,
            metadata: [
                'source' => $report->source,
                'drugs' => $report->checkedDrugs,
                'found' => count($report->interactions),
                'most_severe' => $report->mostSevere()?->severity->value,
            ],
            request: $request,
        );

        return response()->json(['data' => $report->toArray()]);
    }
}
