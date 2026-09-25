<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClinicalEncounterRequest;
use App\Models\ClinicalEncounter;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Encounters\EncounterDirectorResolver;
use App\Support\Encounters\Parts\Prescription;
use App\Support\Interactions\InteractionCheckerResolver;
use App\Support\Interactions\InteractionReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Registro de notas de atención.
 *
 * El controlador no arma la nota ni conoce el contenido mínimo legal: pide el
 * director del tipo de atención, le entrega el payload y persiste el producto
 * que salga. Si la nota está incompleta nunca llega hasta aquí, porque
 * `build()` aborta antes.
 */
class ClinicalEncounterController extends Controller
{
    public function __construct(
        private readonly EncounterDirectorResolver $resolver,
        private readonly InteractionCheckerResolver $interactions,
    ) {}

    /** Tipos de atención disponibles y lo que exige cada uno. */
    public function types(): JsonResponse
    {
        return response()->json(['data' => EncounterDirectorResolver::catalog()]);
    }

    public function store(StoreClinicalEncounterRequest $request): JsonResponse
    {
        $director = $this->resolver->for($request->string('encounter_type')->toString());

        $note = $director->construct(
            payload: $request->validated(),
            patient: $request->filled('patient_id')
                ? User::findOrFail($request->integer('patient_id'))
                : $request->user(),
            professional: $request->user(),
        );

        $encounter = ClinicalEncounter::fromNote($note);

        // La fórmula que acaba de quedar en la historia se verifica contra la
        // fuente de interacciones (patrón Adapter). La nota ya está guardada: la
        // alerta informa al profesional, no le bloquea la atención.
        $report = $this->checkInteractions($note->prescriptions);

        AuditLogger::getInstance()->record(
            action: 'hce.encounter.created',
            actorId: $request->user()->id,
            subjectType: ClinicalEncounter::class,
            subjectId: $encounter->id,
            metadata: [
                'encounter_type' => $note->type->value,
                'patient_id' => $note->patientId,
                'primary_diagnosis' => $note->primaryDiagnosis()?->code,
                'prescriptions' => count($note->prescriptions),
                'interaction_warning' => $report?->mostSevere()?->severity->value,
            ],
            request: $request,
        );

        return response()->json([
            'data' => [
                ...$this->present($encounter),
                'interactions' => $report?->toArray(),
            ],
        ], 201);
    }

    /** Últimas notas registradas, de más reciente a más antigua. */
    public function index(Request $request): JsonResponse
    {
        $encounters = ClinicalEncounter::query()
            ->when(
                $request->filled('encounter_type'),
                fn ($query) => $query->where('type', $request->string('encounter_type'))
            )
            ->latest('attended_at')
            ->limit(25)
            ->get()
            ->map(fn (ClinicalEncounter $encounter) => $this->present($encounter));

        return response()->json(['data' => $encounters]);
    }

    /**
     * Verifica las prescripciones de la nota, si hay al menos dos.
     *
     * Devuelve `null` cuando no hay nada que comparar, para distinguir «no se
     * verificó» de «se verificó y salió limpio».
     *
     * @param  list<Prescription>  $prescriptions
     */
    private function checkInteractions(array $prescriptions): ?InteractionReport
    {
        $principios = array_values(array_unique(array_map(
            fn (Prescription $p) => $p->activeIngredient,
            $prescriptions,
        )));

        if (count($principios) < 2) {
            return null;
        }

        return $this->interactions->for()->check($principios);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ClinicalEncounter $encounter): array
    {
        return [
            'id' => $encounter->id,
            'type' => $encounter->type->value,
            'type_label' => $encounter->type->label(),
            'patient_id' => $encounter->patient_id,
            'professional_id' => $encounter->professional_id,
            'professional_license' => $encounter->professional_license,
            'attended_at' => $encounter->attended_at?->toIso8601String(),
            'chief_complaint' => $encounter->chief_complaint,
            'present_illness' => $encounter->present_illness,
            'history' => $encounter->history,
            'physical_exam' => $encounter->physical_exam,
            'vital_signs' => $encounter->vital_signs ?? [],
            'diagnoses' => $encounter->diagnoses,
            'treatment_plan' => $encounter->treatment_plan,
            'prescriptions' => $encounter->prescriptions ?? [],
            'triage' => $encounter->triage?->value,
            'triage_label' => $encounter->triage?->label(),
            'follow_up_at' => $encounter->follow_up_at?->toIso8601String(),
            'metadata' => $encounter->metadata ?? [],
        ];
    }
}
