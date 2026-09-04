# Patrón Factory Method en el código

## ¿Dónde se usa?

En el módulo de **ingesta de dispositivos IoT** (`backend/app/Support/Iot`), que
implementa el monitoreo remoto descrito en el README (glucómetros, tensiómetros,
oxímetros).

| Rol GoF | Archivo |
|---|---|
| **Producto** (interfaz) | `backend/app/Support/Iot/Readings/ClinicalReading.php` |
| **Productos concretos** | `GlucoseReading.php`, `BloodPressureReading.php`, `OxygenSaturationReading.php` |
| **Creador** (con el método fábrica) | `backend/app/Support/Iot/DeviceReadingFactory.php` |
| **Creadores concretos** | `GlucometerFactory.php`, `SphygmomanometerFactory.php`, `PulseOximeterFactory.php` |
| Selección del creador en runtime | `backend/app/Support/Iot/DeviceReadingFactoryResolver.php` |
| Quién lo usa | `backend/app/Http/Controllers/Api/DeviceReadingController.php` |
| Persistencia | `backend/app/Models/DeviceReading.php` + migración `create_device_readings_table` |
| Pruebas del patrón | `backend/tests/Unit/DeviceReadingFactoryTest.php`, `backend/tests/Feature/DeviceReadingIngestionTest.php` |

## El núcleo del patrón

El creador define el flujo completo de ingesta, pero **no decide qué lectura se
crea**: delega esa decisión en el método fábrica `makeReading()`.

```php
abstract class DeviceReadingFactory
{
    /** EL MÉTODO FÁBRICA: cada subclase decide el producto concreto. */
    abstract protected function makeReading(array $payload): ClinicalReading;

    abstract public function deviceType(): string;

    /** @return array<string, mixed> */
    abstract public function payloadRules(): array;

    /** Plantilla del flujo: idéntica para todos los dispositivos, por eso es `final`. */
    final public function ingest(array $payload, ?int $patientId = null, ?Request $request = null): DeviceReading
    {
        $this->validate($payload);

        $reading = $this->makeReading($payload);   // ← delegación a la subclase

        $record = DeviceReading::create([
            'device_type' => $this->deviceType(),
            'loinc_code'  => $reading->loincCode(),
            'value'       => $reading->value(),
            'unit'        => $reading->unit(),
            'severity'    => $reading->severity(),
            'components'  => $reading->components(),
            'patient_id'  => $patientId,
        ]);

        AuditLogger::getInstance()->record(         // reutiliza el Singleton
            action: 'iot.reading.ingested',
            subjectType: DeviceReading::class,
            subjectId: $record->id,
            metadata: ['device_type' => $this->deviceType(), 'severity' => $reading->severity()->value],
            request: $request,
        );

        return $record;
    }
}
```

Un creador concreto sólo implementa lo que le distingue:

```php
final class GlucometerFactory extends DeviceReadingFactory
{
    public function deviceType(): string { return 'glucometer'; }

    public function payloadRules(): array
    {
        return [
            'mg_dl'   => ['required', 'numeric', 'between:10,900'],
            'fasting' => ['sometimes', 'boolean'],
        ];
    }

    protected function makeReading(array $payload): ClinicalReading
    {
        return new GlucoseReading(
            mgPerDl: (float) $payload['mg_dl'],
            fasting: (bool) ($payload['fasting'] ?? false),
        );
    }
}
```

Y el controlador nunca instancia una lectura ni conoce un solo umbral clínico:

```php
$factory = $this->resolver->for($request->string('device_type')->toString());

$reading = $factory->ingest(
    payload: $request->array('payload'),
    patientId: $request->integer('patient_id') ?: $request->user()?->id,
    request: $request,
);
```

## ¿Para qué se usa?

Para convertir el payload crudo de cualquier dispositivo IoT en una **observación
clínica normalizada** —código LOINC, valor, unidad y severidad— que pueda
guardarse en una sola tabla y exportarse a HL7 FHIR, como exige la Resolución 866
de 2021 para la interoperabilidad de la HCE.

## ¿Por qué tiene que ser Factory Method?

1. **El tipo se conoce sólo en tiempo de ejecución.** El `device_type` llega en
   el JSON del equipo; no hay forma de fijar un `new` en el código.
2. **Cada dispositivo tiene reglas propias que no deben mezclarse.** Que la
   diastólica no supere a la sistólica sólo aplica al tensiómetro; que 150 mg/dL
   sea normal tras comer pero elevado en ayunas sólo aplica a la glucemia. Sin
   el patrón, todo eso sería un `match` gigante en el controlador que crecería
   con cada equipo nuevo.
3. **Extensión sin modificación (OCP).** Añadir un termómetro es escribir
   `ThermometerFactory` + `TemperatureReading` y registrar una línea en el
   resolver. El controlador, la ruta, el modelo y la migración no se tocan.
4. **La auditoría no se puede evadir.** `ingest()` es `final`: ninguna subclase
   puede reescribir el flujo para saltarse el registro que exige la trazabilidad
   de la HCEI (Ley 2015 de 2020).

## Relación con el Singleton

Los dos patrones conviven y se complementan (ver [SINGLETON.md](SINGLETON.md)):

| | Singleton (`AuditLogger`) | Factory Method (`DeviceReadingFactory`) |
|---|---|---|
| Familia | Creacional | Creacional |
| Cuántos objetos | Exactamente **uno** por proceso | **Muchos**, uno por lectura |
| Qué resuelve | Garantizar unicidad y orden global | Diferir la elección de la clase concreta |
| Cómo se obtiene | `AuditLogger::getInstance()` | `$resolver->for($deviceType)` |

Cada ingesta crea productos nuevos mediante la fábrica y los registra en la
**única** instancia del logger.

## Endpoints

| Método | Ruta | Descripción |
|---|---|---|
| `POST` | `/api/device-readings` | Ingesta una lectura (requiere `device_type` y `payload`) |
| `GET` | `/api/device-readings` | Últimas 25 lecturas (filtro opcional `device_type`) |
| `GET` | `/api/devices` | Dispositivos soportados por el resolver |

Ejemplo de petición:

```http
POST /api/device-readings
{
  "device_type": "sphygmomanometer",
  "payload": { "systolic": 190, "diastolic": 125, "pulse": 92 }
}
```

Respuesta (`201`):

```json
{
  "data": {
    "device_type": "sphygmomanometer",
    "loinc_code": "85354-9",
    "display": "Presión arterial",
    "value": 190,
    "unit": "mm[Hg]",
    "severity": "critical",
    "severity_label": "Crítica",
    "requires_attention": true,
    "components": { "systolic": 190, "diastolic": 125, "pulse": 92 }
  }
}
```

## Pruebas

```bash
cd backend && php artisan test --filter=DeviceReading
```

Cubren que el creador y su método fábrica son abstractos, que `ingest()` es
`final`, que cada creador concreto devuelve su producto, que todos los productos
cumplen el mismo contrato, que las reglas clínicas y de validación son propias de
cada dispositivo, y que toda ingesta queda auditada.
