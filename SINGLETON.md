# Patrón Singleton en el código

## ¿Dónde se usa?

El patrón está implementado en la clase **`AuditLogger`**, que corresponde al caso
de uso 2 descrito en el README (*gestor de auditoría / logging clínico*).

| | Archivo |
|---|---|
| **La clase Singleton** | `backend/app/Support/Audit/AuditLogger.php` |
| **Quién la usa** | `backend/app/Http/Controllers/Api/AuthController.php` (login, logout y consulta de sesión) |
| Enlace con el contenedor de Laravel | `backend/app/Providers/AppServiceProvider.php` |
| Pruebas que verifican el patrón | `backend/tests/Unit/AuditLoggerSingletonTest.php` |

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

Si hubiera dos instancias, los eventos de un mismo login quedarían repartidos en
dos identificadores distintos y **ambos empezarían a contar desde 1**, con lo que
se perdería el orden exigido por la trazabilidad de la HCEI (Ley 2015 de 2020 y
Res. 1995 de 1999). Además, centralizarlo evita que un módulo pueda "saltarse" la
auditoría creando su propio logger.

## ¿Cómo se garantiza la instancia única?

Constructor `private` (nadie puede hacer `new AuditLogger()`), acceso sólo por
`AuditLogger::getInstance()`, `__clone()` privado y `__wakeup()` que lanza
excepción para cerrar la vía de la deserialización.

