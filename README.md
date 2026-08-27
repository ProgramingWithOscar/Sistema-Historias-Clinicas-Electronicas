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

## Link de video explicativo: https://drive.google.com/file/d/15UcTiR7eaimJ0NZa0y0N0Fs_M6TaDz42/view?usp=sharing

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
