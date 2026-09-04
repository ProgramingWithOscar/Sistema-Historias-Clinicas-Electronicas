# Semana 4 - PATRON DE DISEÑO FACTORY METHOD

## ¿Por qué Factory Method en este proyecto?

El módulo de **monitoreo remoto con dispositivos IoT** recibe lecturas de equipos
heterogéneos —glucómetros, tensiómetros, oxímetros— cada uno con su propio
formato, sus propias unidades y sus propios rangos de alarma. El sistema, en
cambio, necesita almacenar y exportar siempre lo mismo: una observación clínica
normalizada con su código LOINC, su valor, su unidad y su criticidad, como exige
la Resolución 866 de 2021 para la interoperabilidad de la HCE.

El tipo de dispositivo llega dentro del JSON de la petición, es decir, **sólo se
conoce en tiempo de ejecución**, de modo que la creación del objeto no puede
fijarse con un `new` en el código. Ése es exactamente el problema que resuelve el
Factory Method: una clase creadora define el flujo de ingesta y delega en sus
subclases la decisión de qué producto concreto instanciar.

## Casos de uso concretos

*1. Normalización de lecturas de dispositivos IoT (implementado)*
Cada fábrica traduce el payload de su equipo al producto `ClinicalReading`, con
su código LOINC y su interpretación clínica. Dar de alta un termómetro o una
báscula es añadir una subclase, sin tocar el controlador ni la base de datos.

*2. Exportación de la historia clínica*
Un `ClinicalExportFactory` con variantes FHIR, PDF y RDA (Res. 1888 de 2025)
resuelve el formato de salida según lo que pida el prestador receptor.

*3. Notificación de alertas críticas*
El canal por el que se avisa una hipoxemia o una crisis hipertensiva (SMS, push,
correo, integración con el sistema de urgencias) depende de la configuración de
la institución y del turno, otro dato conocido sólo en runtime.

## ¿ Donde de usa ?

> **Implementación:** el patron factory method está implementado en la clase
> `DeviceReadingFactory` (`backend/app/Support/Iot/DeviceReadingFactory.php`),
> sus tres subclases (`GlucometerFactory`, `SphygmomanometerFactory`,
> `PulseOximeterFactory`) y el producto `ClinicalReading`
> (`backend/app/Support/Iot/Readings/ClinicalReading.php`). Se usa desde
> `DeviceReadingController`.

Extracto de `backend/app/Support/Iot/DeviceReadingFactory.php`:

```php
abstract class DeviceReadingFactory
{
    /** EL MÉTODO FÁBRICA: la superclase sabe *que* necesita una lectura, pero no *cuál*. */
    abstract protected function makeReading(array $payload): ClinicalReading;

    /** Identificador del dispositivo tal como llega en la petición. */
    abstract public function deviceType(): string;

    /** Reglas de validación del payload crudo, propias de cada dispositivo. */
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

        // Reutiliza el Singleton de auditoría: la lectura entra en la historia clínica.
        AuditLogger::getInstance()->record(
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

Cada creador concreto implementa sólo lo que le distingue:

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

Y así lo consume `DeviceReadingController`, sin instanciar ninguna lectura ni
conocer un solo umbral clínico:

```php
$factory = $this->resolver->for($request->string('device_type')->toString());

$reading = $factory->ingest(
    payload: $request->array('payload'),
    patientId: $request->integer('patient_id') ?: $request->user()?->id,
    request: $request,
);
```

## ¿Para qué se usa?

Para convertir el payload crudo de cualquier dispositivo IoT en una observación
clínica normalizada —código LOINC, valor, unidad y severidad— que pueda guardarse
en una sola tabla (`device_readings`) y exportarse a HL7 FHIR, como exige la
Resolución 866 de 2021. Hoy atiende tres dispositivos (`glucometer`,
`sphygmomanometer`, `pulse_oximeter`) a través de los endpoints
`POST /api/device-readings`, `GET /api/device-readings` y `GET /api/devices`, y
cada ingesta queda auditada con la acción `iot.reading.ingested`.

## ¿Por qué tiene que ser Factory Method?

Porque el objeto a crear se decide con un dato que sólo existe en tiempo de
ejecución y cada variante trae consigo reglas que no deben mezclarse:

- `device_type` llega dentro del JSON del equipo, así que no hay forma de fijar
  un `new` en el código.
- Cada dispositivo valida distinto: que la diastólica no supere a la sistólica
  sólo aplica al tensiómetro; que 150 mg/dL sea normal tras comer pero elevado en
  ayunas sólo aplica a la glucemia —y en ese caso hasta el código LOINC cambia.

Sin el patrón, toda esa lógica sería un `match` gigante en el controlador que
crecería con cada equipo nuevo. Con él, añadir un termómetro es escribir
`ThermometerFactory` más `TemperatureReading` y registrar una línea en el
resolver: el controlador, la ruta, el modelo y la migración no se tocan
(principio abierto/cerrado).