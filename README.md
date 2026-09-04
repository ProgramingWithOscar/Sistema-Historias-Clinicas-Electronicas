# Sistema Historias Clínicas Electronicas 🫆
Proyecto para la gestión de pacientes, citas, diagnósticos y tratamientos.
Integración con dispositivos IoT
Alerta de interacciones medicamentosas
Cumplimiento de normativas HIPAA/leyes de protección de datos

# Contextualización
## 1. Panorama del problema
 
El sector salud ha operado durante décadas con expedientes clínicos fragmentados: registros en papel, sistemas aislados por institución (islas de información) y procesos manuales para agendamiento, facturación y seguimiento de tratamientos. Esto genera consecuencias graves:
 
- **Errores médicos evitables**: la OMS estima que los eventos adversos por medicamentos (incluyendo interacciones farmacológicas no detectadas) son una de las diez principales causas de daño al paciente en el mundo.
- **Duplicidad de exámenes** y sobrecostos al sistema porque un prestador no accede al historial de otro.
- **Pérdida de continuidad asistencial** cuando el paciente se traslada de ciudad, cambia de EPS o requiere atención de urgencia lejos de su médico habitual.
- **Baja trazabilidad** en la adherencia a tratamientos crónicos (hipertensión, diabetes), donde el seguimiento con dispositivos IoT podría anticipar descompensaciones.
- **Riesgos de privacidad**: los datos de salud son de categoría especial y su filtración expone a los pacientes y a las instituciones a sanciones y demandas.
## 2. Marco normativo aplicable
 
### En Colombia
 
- **Ley 2015 de 2020**: crea la Historia Clínica Electrónica Interoperable (HCEI) y garantiza el acceso del paciente a su información respetando el Hábeas Data.
- **Resolución 866 de 2021** (MinSalud): reglamenta el conjunto de elementos de datos clínicos relevantes para la interoperabilidad.
- **Resolución 1888 de 2025**: adopta el Resumen Digital de Atención en Salud (RDA) como mecanismo para implementar la Interoperabilidad de la HCE a nivel nacional.
- **Resolución 1995 de 1999**: define contenidos mínimos, diligenciamiento, conservación y custodia de la historia clínica (sigue vigente).
- **Ley 1581 de 2012 y Decreto 1377 de 2013**: régimen general de protección de datos personales, con tratamiento reforzado para datos sensibles (salud).
- **Resolución 3100 de 2019**: habilitación de prestadores de servicios de salud.
### Estándares internacionales de referencia
 
- **HL7 FHIR** — estándar exigido de facto para interoperabilidad clínica; en Colombia la Resolución 866 requiere que los sistemas lo soporten junto con el conjunto mínimo de datos.
- **HIPAA** (EE.UU.) — referente global de privacidad y seguridad; útil como benchmark aunque no aplique legalmente en Colombia.
- **ISO 27799** — seguridad de la información en salud.
- **SNOMED CT, LOINC, CIE-10** — vocabularios clínicos controlados.
## 3. Justificación del proyecto
 
Un sistema de HCE moderno debe responder simultáneamente a tres presiones:
 
1. **Regulatoria**: la HCEI dejó de ser opcional en Colombia; los prestadores que no se alineen enfrentan riesgos de habilitación y acreditación.
2. **Clínica**: reducir errores mediante soporte a la decisión (alertas de interacciones medicamentosas, alergias, dosis) y aprovechar señales de dispositivos IoT (glucómetros, tensiómetros, oxímetros, wearables) para monitoreo remoto y alertas tempranas.
3. **Operacional**: unificar gestión de pacientes, agendamiento, diagnósticos, tratamientos y facturación en una sola plataforma con trazabilidad de auditoría.

## Patrón de Diseño: Singleton

### ¿Por qué Singleton en este proyecto?

En un Sistema de Historias Clínicas Electrónicas hay componentes que *deben existir en una única instancia* dentro de la aplicación, porque múltiples instancias generarían inconsistencias, condiciones de carrera o violaciones de las normativas de auditoría y seguridad (Res. 1995/1999, Ley 1581/2012, ISO 27799).

### Casos de uso concretos

*1. Conexión a la base de datos clínica*
Un único punto de acceso a la base de datos evita conexiones duplicadas descontroladas, centraliza el pool de conexiones y facilita el control transaccional cuando se registran diagnósticos, tratamientos o eventos IoT en tiempo real.

*2. Gestor de auditoría / logging clínico*
La trazabilidad exigida por la HCEI (Ley 2015 de 2020) requiere que *todos* los accesos y modificaciones a una historia clínica queden registrados de forma centralizada y cronológicamente consistente. Si existieran múltiples instancias del logger, se podrían perder o desordenar eventos críticos para una auditoría.

*3. Motor de reglas de interacciones medicamentosas*
El componente que valida interacciones fármaco-fármaco o fármaco-alergia debe cargar una única vez el catálogo de reglas (basado en vocabularios como SNOMED CT o bases de interacciones) y mantenerlo en memoria. Instanciarlo múltiples veces desperdiciaría recursos y podría generar respuestas inconsistentes ante la misma consulta.

*4. Gestor de configuración global*
Parámetros como las credenciales de integración con dispositivos IoT, endpoints de FHIR, o llaves de cifrado para datos sensibles deben leerse de un solo lugar consistente, evitando que distintos módulos trabajen con configuraciones desincronizadas.

*5. Gestor de sesión/autenticación*
Para cumplir con el control de acceso exigido por la protección de datos sensibles de salud, conviene centralizar la validación de sesiones activas y permisos por rol (médico, enfermero, administrativo) en un único componente.

### Semana 3 - PATRON DE DISEÑO SINGLETON

## ¿ Donde de usa ?

> **Implementación:** el patron singleton está implementado en la clase `AuditLogger`
> (`backend/app/Support/Audit/AuditLogger.php`) y se usa desde `AuthController`.

Extracto de `backend/app/Support/Audit/AuditLogger.php`:

```php
final class AuditLogger
{
    /** Única instancia viva del logger en el proceso. */
    private static ?self $instance = null;

    /** Correlativo compartido por todos los eventos de una misma petición. */
    private readonly string $requestId;

    /** Orden monotónico de los eventos; sólo tiene sentido si la instancia es única. */
    private int $sequence = 0;

    /** Privado: nadie fuera de la clase puede hacer `new AuditLogger()`. */
    private function __construct()
    {
        $this->requestId = (string) Str::uuid();
    }

    /** Único punto de acceso a la instancia. */
    public static function getInstance(): self
    {
        return self::$instance ??= new self;
    }

    /** Bloquea la clonación: `clone $logger` crearía una segunda instancia. */
    private function __clone(): void {}

    /** Bloquea la deserialización, la otra vía para duplicar la instancia. */
    public function __wakeup(): void
    {
        throw new \LogicException('AuditLogger es un Singleton y no puede deserializarse.');
    }
}
```

Y así lo consume `AuthController`, sin instanciarlo ni recibirlo por inyección:

```php
$audit = AuditLogger::getInstance();

$audit->record(
    action: 'auth.login.succeeded',
    actorId: $user->id,
    subjectType: User::class,
    subjectId: $user->id,
    request: $request,
);
```

## ¿Para qué se usa?

Para que *todos* los accesos y cambios sobre una historia clínica se registren a
través de un único componente. Hoy audita el flujo de autenticación
(`auth.login.succeeded`, `auth.login.failed`, `auth.logout`, `auth.session.read`)
y guarda cada evento en la tabla `audit_logs`.

## ¿Por qué tiene que ser Singleton?

Porque el logger mantiene dos datos que sólo son correctos si existe una sola
instancia en toda la petición:

- `requestId`: identificador que agrupa todos los eventos de una misma atención.
- `sequence`: contador que da el orden cronológico de esos eventos.
## Patrón de Diseño: Factory Method

### ¿Por qué Factory Method en este proyecto?

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

### Casos de uso concretos

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

### Semana 4 - PATRON DE DISEÑO FACTORY METHOD

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

Además, `ingest()` se declara `final` a propósito: si una subclase pudiera
reescribir el flujo, podría saltarse el registro de auditoría que exige la
trazabilidad de la HCEI (Ley 2015 de 2020).

## Relación con el Singleton

Los dos patrones son creacionales y conviven en cada ingesta: la fábrica crea un
producto nuevo por cada lectura, y ese producto se registra en la *única*
instancia del `AuditLogger`.

| | Singleton (`AuditLogger`) | Factory Method (`DeviceReadingFactory`) |
|---|---|---|
| Cuántos objetos | Exactamente **uno** por proceso | **Muchos**, uno por lectura |
| Qué resuelve | Garantizar unicidad y orden global | Diferir la elección de la clase concreta |
| Cómo se obtiene | `AuditLogger::getInstance()` | `$resolver->for($deviceType)` |

El detalle completo —tabla de roles GoF, ejemplos de request/response y pruebas—
está en [FACTORY_METHOD.md](FACTORY_METHOD.md).
