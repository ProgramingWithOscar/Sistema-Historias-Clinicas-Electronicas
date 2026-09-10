<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClinicalExportRequest;
use App\Models\User;
use App\Support\Interop\ClinicalExchangeFactoryResolver;
use App\Support\Interop\ClinicalRecordExporter;
use Illuminate\Http\JsonResponse;

/**
 * Exportación de la historia clínica hacia otro prestador.
 *
 * El controlador no conoce ningún formato: pide al resolver la familia que
 * corresponde al estándar solicitado y se la entrega al exportador. Añadir un
 * formato nuevo (CDA, HL7 v2, PDF firmado) no cambia una línea de este archivo.
 */
class ClinicalExportController extends Controller
{
    public function __construct(
        private readonly ClinicalExchangeFactoryResolver $resolver,
    ) {}

    /** Familias de exportación disponibles hoy. */
    public function standards(): JsonResponse
    {
        return response()->json(['data' => ClinicalExchangeFactoryResolver::catalog()]);
    }

    public function store(StoreClinicalExportRequest $request): JsonResponse
    {
        $factory = $this->resolver->for($request->string('standard')->toString());

        $patient = $request->filled('patient_id')
            ? User::findOrFail($request->integer('patient_id'))
            : $request->user();

        $export = (new ClinicalRecordExporter($factory))->export(
            patient: $patient,
            request: $request,
        );

        return response()->json(['data' => $export], 201);
    }
}
