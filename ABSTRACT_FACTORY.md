# Patrón Abstract Factory en el código

## ¿Dónde se usa?

En el módulo de **interoperabilidad y exportación de la historia clínica**
(`backend/app/Support/Interop`), que responde al objetivo central del proyecto:
que la historia de un paciente pueda viajar a otro prestador sin perder
continuidad asistencial.

| Rol GoF | Archivo |
|---|---|
| **Fábrica abstracta** | `backend/app/Support/Interop/Contracts/ClinicalExchangeFactory.php` |
| **Fábricas concretas** | `Fhir/FhirR4ExchangeFactory.php`, `Rda/RdaExchangeFactory.php`, `Anonymized/AnonymizedExchangeFactory.php` |
| **Producto abstracto A** | `Contracts/PatientSerializer.php` |
| **Producto abstracto B** | `Contracts/ObservationSerializer.php` |
| **Producto abstracto C** | `Contracts/ExchangeEnvelope.php` |
| **Productos concretos (FHIR)** | `FhirPatientSerializer.php`, `FhirObservationSerializer.php`, `FhirBundleEnvelope.php` |
| **Productos concretos (RDA)** | `RdaPatientSerializer.php`, `RdaObservationSerializer.php`, `RdaDocumentEnvelope.php` |
| **Productos concretos (anonimizado)** | `AnonymizedPatientSerializer.php`, `AnonymizedObservationSerializer.php`, `AnonymizedDatasetEnvelope.php` |
| **Cliente** | `backend/app/Support/Interop/ClinicalRecordExporter.php` |
| Selección de la familia en runtime | `backend/app/Support/Interop/ClinicalExchangeFactoryResolver.php` |
| Quién lo expone | `backend/app/Http/Controllers/Api/ClinicalExportController.php` |
| Configuración | `backend/config/interop.php` |
| Pruebas del patrón | `backend/tests/Unit/ClinicalExchangeFactoryTest.php`, `backend/tests/Feature/ClinicalExportTest.php` |

## El núcleo del patrón

La fábrica abstracta declara un método de creación **por cada producto de la
familia**. Una fábrica concreta implementa los tres, y al hacerlo garantiza que
los objetos que devuelve están hechos para trabajar juntos.

```php
interface ClinicalExchangeFactory
{
    public function standard(): ExchangeStandard;

    public function createPatientSerializer(): PatientSerializer;
    public function createObservationSerializer(): ObservationSerializer;
    public function createEnvelope(): ExchangeEnvelope;
}
```

Cada fábrica concreta se limita a declarar su familia:

```php
final class FhirR4ExchangeFactory implements ClinicalExchangeFactory
{
    public function standard(): ExchangeStandard
    {
        return ExchangeStandard::FhirR4;
    }

    public function createPatientSerializer(): PatientSerializer
    {
        return new FhirPatientSerializer;      // recurso Patient
    }

    public function createObservationSerializer(): ObservationSerializer
    {
        return new FhirObservationSerializer;  // recurso Observation
    }

    public function createEnvelope(): ExchangeEnvelope
    {
        return new FhirBundleEnvelope;         // Bundle type=document
    }
}
```

El cliente arma el documento sin nombrar ni una vez FHIR, RDA o anonimización:

```php
// Los tres productos se piden a la MISMA fábrica: ahí está la garantía.
$patientSerializer     = $this->factory->createPatientSerializer();
$observationSerializer = $this->factory->createObservationSerializer();
$envelope              = $this->factory->createEnvelope();

$reference = $patientSerializer->reference($patient);

$observations = DeviceReading::where('patient_id', $patient->id)->get()
    ->map(fn (DeviceReading $r) => $observationSerializer->serialize($r, $reference))
    ->all();

$document = $envelope->assemble(
    patient: $patientSerializer->serialize($patient),
    observations: $observations,
    generatedAt: $generatedAt,
);
```

Y el controlador sólo traduce el estándar pedido a una familia:

```php
$factory = $this->resolver->for($request->string('standard')->toString());

$export = (new ClinicalRecordExporter($factory))->export($patient, $request);
```

## ¿Para qué se usa?

Para exportar la historia clínica —identificación del paciente más las lecturas
IoT que normalizó el Factory Method— en el formato que exija el destinatario,
con la garantía de que el documento es internamente coherente.

| Estándar | Paciente | Observaciones | Sobre |
|---|---|---|---|
| `fhir_r4` (Res. 866 de 2021) | recurso `Patient` con `identifier` nacional | `Observation` con LOINC, UCUM e `interpretation` HL7 | `Bundle` de tipo `document` |
| `rda` (Res. 1888 de 2025) | bloque `paciente` con tipo y número de documento | `hallazgos` en español con la severidad traducida | envoltorio con prestador y código de habilitación |
| `anonymized` (Ley 1581 de 2012) | seudónimo con sal y grupo etario en quinquenios | registros sin fecha exacta: sólo el mes | dataset que declara finalidad y base legal |

Cada exportación queda auditada por el Singleton con la acción
`hce.export.generated`.

## ¿Por qué tiene que ser Abstract Factory?

1. **Los productos no son independientes.** Exportar en FHIR no es crear un
   `Patient`: es crear un `Patient`, unas `Observation` que lo referencien como
   FHIR espera y un `Bundle` que las envuelva. La referencia con la que la
   observación apunta al paciente la produce el serializador de paciente de esa
   misma familia (`Patient/1` en FHIR, `CC-52847391` en el RDA, `SUJ-7a30…` en
   el dataset): cruzarlas rompe el documento.
2. **La anonimización es una propiedad del documento entero.** No basta con
   ocultar el nombre si las observaciones conservan la fecha exacta de medición,
   ni con agrupar la edad si el sobre no declara la base legal. Al venir los tres
   productos de `AnonymizedExchangeFactory`, esa garantía la sostiene el sistema
   de tipos y no la disciplina del programador.
3. **El estándar se conoce sólo en tiempo de ejecución.** Llega en la petición
   del receptor, igual que el `device_type` del Factory Method.
4. **Extensión sin modificación (OCP).** Añadir CDA, HL7 v2 o un PDF firmado es
   escribir una fábrica con sus tres productos y registrar una línea en el
   resolver. El exportador, el controlador y la ruta no se tocan.

## Relación con los otros dos patrones

Los tres conviven en el mismo flujo (ver [SINGLETON.md](SINGLETON.md) y
[FACTORY_METHOD.md](FACTORY_METHOD.md)):

| | Singleton (`AuditLogger`) | Factory Method (`DeviceReadingFactory`) | Abstract Factory (`ClinicalExchangeFactory`) |
|---|---|---|---|
| Qué resuelve | Unicidad y orden global | Diferir la elección de **una** clase concreta | Garantizar la coherencia de **una familia** de clases |
| Cuántos productos | Ninguno: es la instancia | Uno por lectura | Tres por exportación, obligatoriamente compatibles |
| Dato que decide | — | `device_type` | `standard` |
| Cómo se obtiene | `AuditLogger::getInstance()` | `$resolver->for($deviceType)` | `$resolver->for($standard)` |

El recorrido completo del dato: el **Factory Method** convierte el payload crudo
del dispositivo en una observación normalizada, el **Abstract Factory** la
publica en el estándar que pide el receptor, y el **Singleton** deja trazado
cada paso.

## Endpoints

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/api/exchange-standards` | Familias de exportación disponibles |
| `POST` | `/api/clinical-exports` | Genera el documento (requiere `standard`; `patient_id` opcional) |

Ejemplo de petición:

```http
POST /api/clinical-exports
{
  "standard": "anonymized"
}
```

Respuesta (`201`):

```json
{
  "data": {
    "standard": "anonymized",
    "label": "Conjunto anonimizado",
    "legal_basis": "Ley 1581 de 2012, art. 5 y 6",
    "media_type": "application/json",
    "filename": "dataset-anonimizado-20260910.json",
    "observations": 1,
    "document": {
      "dataset": {
        "finalidad": "investigacion-y-estadistica",
        "baseLegal": "Ley 1581 de 2012, art. 5 y 6 (datos disociados)",
        "contieneDatosIdentificables": false,
        "sujeto": { "seudonimo": "SUJ-7a3044b64b99591b", "grupoEtario": "40-44", "disociado": true },
        "registros": [
          {
            "seudonimo": "SUJ-7a3044b64b99591b",
            "codigoLoinc": "85354-9",
            "observable": "Presión arterial",
            "valor": 150,
            "unidad": "mm[Hg]",
            "interpretacion": "warning",
            "periodo": "2026-09"
          }
        ]
      }
    }
  }
}
```

## Pruebas

```bash
cd backend && php artisan test --filter="ClinicalExchangeFactoryTest|ClinicalExportTest"
```

Cubren que cada fábrica entrega la familia completa de su estándar, que todas
cumplen los mismos contratos abstractos, que ninguna comparte productos con
otra, que la observación referencia al paciente de su propia familia, que el
mismo paciente produce documentos estructuralmente distintos según la familia,
que la exportación anonimizada no publica ningún dato identificable **en el
documento completo**, y que toda exportación queda auditada.
