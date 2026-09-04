<?php

namespace App\Support\Iot;

use App\Models\DeviceReading;
use App\Support\Audit\AuditLogger;
use App\Support\Iot\Readings\ClinicalReading;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * CREADOR del patrón Factory Method (GoF).
 *
 * Define el algoritmo completo de ingesta —validar, crear la lectura,
 * persistirla y auditarla— pero *no decide qué lectura concreta se crea*: esa
 * decisión se delega en el método fábrica `makeReading()`, que cada subclase
 * implementa para su dispositivo.
 *
 * El tipo de dispositivo sólo se conoce en tiempo de ejecución (llega en el
 * payload del equipo), así que la creación no puede resolverse con un `new`
 * fijo. Añadir un termómetro o una báscula es escribir una subclase nueva y
 * registrarla en el resolver: ni el controlador ni esta clase se modifican
 * (principio abierto/cerrado).
 */
abstract class DeviceReadingFactory
{
    /**
     * EL MÉTODO FÁBRICA.
     *
     * Es abstracto a propósito: la superclase sabe *que* necesita una lectura,
     * pero no *cuál*. Cada subclase traduce el payload crudo de su dispositivo
     * al producto que le corresponde.
     *
     * @param  array<string, mixed>  $payload
     */
    abstract protected function makeReading(array $payload): ClinicalReading;

    /** Identificador del dispositivo tal como llega en la petición. */
    abstract public function deviceType(): string;

    /**
     * Reglas de validación del payload crudo, propias de cada dispositivo.
     *
     * @return array<string, mixed>
     */
    abstract public function payloadRules(): array;

    /**
     * Plantilla del flujo de ingesta: es idéntico para todos los dispositivos y
     * por eso se declara `final`. Lo único que varía —el producto— ya se aisló
     * en `makeReading()`.
     *
     * @param  array<string, mixed>  $payload
     */
    final public function ingest(array $payload, ?int $patientId = null, ?Request $request = null): DeviceReading
    {
        $this->validate($payload);

        $reading = $this->makeReading($payload);   // ← delegación a la subclase

        $record = DeviceReading::create([
            'device_type' => $this->deviceType(),
            'loinc_code' => $reading->loincCode(),
            'display' => $reading->display(),
            'value' => $reading->value(),
            'unit' => $reading->unit(),
            'severity' => $reading->severity(),
            'components' => $reading->components(),
            'patient_id' => $patientId,
            'measured_at' => $payload['measured_at'] ?? now(),
        ]);

        // Reutiliza el Singleton de auditoría: la lectura entra en la historia
        // clínica, así que debe quedar trazada igual que cualquier otro acceso.
        AuditLogger::getInstance()->record(
            action: 'iot.reading.ingested',
            subjectType: DeviceReading::class,
            subjectId: $record->id,
            metadata: [
                'device_type' => $this->deviceType(),
                'loinc_code' => $reading->loincCode(),
                'severity' => $reading->severity()->value,
            ],
            request: $request,
        );

        return $record;
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    private function validate(array $payload): void
    {
        validator($payload, $this->payloadRules())->validate();
    }
}
