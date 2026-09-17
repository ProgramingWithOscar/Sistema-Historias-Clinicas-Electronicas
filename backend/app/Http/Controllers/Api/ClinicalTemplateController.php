<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClinicalTemplateRequest;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalTemplate;
use App\Support\Audit\AuditLogger;
use App\Support\Templates\EncounterTemplate;
use App\Support\Templates\TemplateLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catálogo de plantillas de atención.
 *
 * El controlador nunca construye una plantilla a mano: se la pide al registro,
 * que siempre devuelve una copia. Y para crear una nueva tampoco compone nada:
 * clona una nota ya existente.
 */
class ClinicalTemplateController extends Controller
{
    public function __construct(
        private readonly TemplateLibrary $library,
    ) {}

    /** Plantillas disponibles: institucionales y guardadas por profesionales. */
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->library->registry()->catalog()]);
    }

    /**
     * Borrador con el que arranca la atención.
     *
     * Cada llamada devuelve una COPIA independiente: dos médicos pueden cargar
     * la misma plantilla a la vez y ajustarla cada uno por su lado sin pisarse.
     */
    public function draft(Request $request, string $key): JsonResponse
    {
        $template = $this->library->registry()->get($key);

        AuditLogger::getInstance()->record(
            action: 'hce.template.applied',
            actorId: $request->user()?->id,
            metadata: ['template' => $key, 'encounter_type' => $template->type()->value],
            request: $request,
        );

        return response()->json([
            'data' => [
                'template' => $template->toArray(),
                // Listo para enviarse a POST /api/clinical-encounters.
                'payload' => $template->toPayload(),
            ],
        ]);
    }

    /**
     * «Guardar como plantilla»: crea un prototipo nuevo clonando una nota real.
     *
     * Lo que NO se copia es tan importante como lo que sí: `EncounterTemplate`
     * descarta la anamnesis, los antecedentes, los hallazgos y la identidad del
     * paciente que originó la nota.
     */
    public function store(StoreClinicalTemplateRequest $request): JsonResponse
    {
        $encounter = ClinicalEncounter::findOrFail($request->integer('encounter_id'));

        $prototype = EncounterTemplate::fromEncounter(
            encounter: $encounter,
            key: $request->string('key')->toString(),
            name: $request->string('name')->toString(),
            authorId: $request->user()->id,
        );

        $stored = ClinicalTemplate::fromPrototype($prototype);

        AuditLogger::getInstance()->record(
            action: 'hce.template.saved',
            actorId: $request->user()->id,
            subjectType: ClinicalTemplate::class,
            subjectId: $stored->id,
            metadata: [
                'template' => $prototype->key(),
                'origin_encounter_id' => $encounter->id,
                // Deja constancia de que la plantilla salió despersonalizada.
                'omitted_sections' => EncounterTemplate::SECCIONES_NO_HEREDABLES,
            ],
            request: $request,
        );

        return response()->json(['data' => $prototype->toArray()], 201);
    }
}
