<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeviceReadingRequest;
use App\Models\DeviceReading;
use App\Support\Iot\DeviceReadingFactoryResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceReadingController extends Controller
{
    public function __construct(
        private readonly DeviceReadingFactoryResolver $resolver,
    ) {}

    /**
     * Ingesta de una lectura de dispositivo IoT.
     *
     * El controlador nunca hace `new GlucoseReading(...)` ni conoce los rangos
     * clínicos: pide el creador que corresponde al dispositivo y delega. Ése es
     * el beneficio del Factory Method — este método no cambia cuando se añade
     * un equipo nuevo.
     */
    public function store(StoreDeviceReadingRequest $request): JsonResponse
    {
        $factory = $this->resolver->for($request->string('device_type')->toString());

        $reading = $factory->ingest(
            payload: $request->array('payload'),
            patientId: $request->integer('patient_id') ?: $request->user()?->id,
            request: $request,
        );

        return response()->json(['data' => $this->present($reading)], 201);
    }

    /** Últimas lecturas registradas, de más reciente a más antigua. */
    public function index(Request $request): JsonResponse
    {
        $readings = DeviceReading::query()
            ->when(
                $request->filled('device_type'),
                fn ($query) => $query->where('device_type', $request->string('device_type'))
            )
            ->latest('measured_at')
            ->limit(25)
            ->get()
            ->map(fn (DeviceReading $reading) => $this->present($reading));

        return response()->json(['data' => $readings]);
    }

    /** Dispositivos que el resolver sabe atender hoy. */
    public function devices(): JsonResponse
    {
        return response()->json(['data' => DeviceReadingFactoryResolver::supportedDevices()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(DeviceReading $reading): array
    {
        return [
            'id' => $reading->id,
            'device_type' => $reading->device_type,
            'loinc_code' => $reading->loinc_code,
            'display' => $reading->display,
            'value' => $reading->value,
            'unit' => $reading->unit,
            'severity' => $reading->severity->value,
            'severity_label' => $reading->severity->label(),
            'requires_attention' => $reading->severity->requiresAttention(),
            'components' => $reading->components,
            'patient_id' => $reading->patient_id,
            'measured_at' => $reading->measured_at?->toIso8601String(),
        ];
    }
}
