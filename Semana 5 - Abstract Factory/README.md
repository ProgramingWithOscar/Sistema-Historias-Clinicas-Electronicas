# PATRON DE DISEÑO ABSTRACT FACTORY


## ¿Por qué Abstract Factory en este proyecto?

El objetivo central del sistema es la **interoperabilidad de la historia
clínica**: que un paciente atendido en otra ciudad, en otra EPS o en una
urgencia lejos de su médico habitual llegue con su historia completa. Eso
significa exportar la misma información en formatos distintos según quién la
reciba —HL7 FHIR R4 para el nodo nacional (Res. 866 de 2021), el Resumen Digital
de Atención para el MinSalud (Res. 1888 de 2025), un conjunto disociado para
investigación (Ley 1581 de 2012)—.

Y aquí aparece un problema que el Factory Method no resuelve: **un formato no es
un objeto, es una familia de objetos**. Exportar en FHIR no significa crear un
recurso `Patient`; significa crear un `Patient`, unas `Observation` que lo
referencien como FHIR espera y un `Bundle` que las envuelva. Los tres tienen que
venir del mismo estándar. Un `Patient` de FHIR dentro del envoltorio del RDA
produce un documento que el receptor rechaza; y un paciente identificado dentro
de un dataset que se declara anonimizado es, directamente, una violación del
tratamiento de datos sensibles.

Ése es exactamente el problema del Abstract Factory: no es "no sé qué objeto
crear", es **"no puedo permitir que se mezclen objetos de familias distintas"**.

## Casos de uso concretos

*1. Exportación de la historia clínica por estándar (implementado)*
Cada fábrica produce la terna completa —serializador de paciente, serializador
de observaciones y sobre del documento— del estándar que pide el receptor.
Añadir CDA o HL7 v2 es escribir una fábrica nueva con sus tres productos.

*2. Canales de notificación de alertas críticas*
Una institución que usa Twilio necesita a la vez el emisor SMS, la plantilla SMS
y el registro de entrega de Twilio; otra que usa el sistema de urgencias local
necesita los tres suyos. Mezclar la plantilla de un proveedor con el emisor de
otro no compila un mensaje válido.

*3. Perfiles de cifrado y custodia de datos sensibles*
El almacenamiento en producción, en el entorno de pruebas y en el nodo de
investigación exigen cada uno su propia terna de cifrador, gestor de llaves y
política de retención (ISO 27799). Una llave de producción con la retención de
pruebas borraría historias que la Res. 1995 de 1999 obliga a conservar.


## ¿ Donde de usa ?

> **Implementación:** el patrón abstract factory está implementado en la interfaz
> `ClinicalExchangeFactory` (`backend/app/Support/Interop/Contracts/ClinicalExchangeFactory.php`),
> sus tres fábricas concretas (`FhirR4ExchangeFactory`, `RdaExchangeFactory`,
> `AnonymizedExchangeFactory`) y los tres productos abstractos
> (`PatientSerializer`, `ObservationSerializer`, `ExchangeEnvelope`). El cliente
> es `ClinicalRecordExporter` y se usa desde `ClinicalExportController`.

Extracto de `backend/app/Support/Interop/Contracts/ClinicalExchangeFactory.php`:

```php
interface ClinicalExchangeFactory
{
    /** Estándar que representa esta familia. */
    public function standard(): ExchangeStandard;

    /** Crea el producto A de la familia. */
    public function createPatientSerializer(): PatientSerializer;

    /** Crea el producto B de la familia. */
    public function createObservationSerializer(): ObservationSerializer;

    /** Crea el producto C de la familia. */
    public function createEnvelope(): ExchangeEnvelope;
}
```

Cada fábrica concreta se limita a declarar su familia completa:

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

Y así lo consume el cliente `ClinicalRecordExporter`, que arma la historia
exportable sin nombrar ni una sola vez FHIR, RDA ni anonimización:

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

El controlador sólo elige la familia con el estándar que llega en la petición:

```php
$factory = $this->resolver->for($request->string('standard')->toString());

$export = (new ClinicalRecordExporter($factory))->export(
    patient: $patient,
    request: $request,
);
```

## ¿Para qué se usa?

Para exportar la historia clínica de un paciente —sus datos de identificación
más las lecturas IoT que normalizó el Factory Method— en el estándar que exija
el destinatario, garantizando que el documento resultante es internamente
coherente. Hoy produce tres familias completas a través de los endpoints
`POST /api/clinical-exports` y `GET /api/exchange-standards`:

| Estándar | Paciente | Observaciones | Sobre |
|---|---|---|---|
| `fhir_r4` (Res. 866 de 2021) | recurso `Patient` con `identifier` nacional | recurso `Observation` con LOINC, UCUM e `interpretation` HL7 | `Bundle` de tipo `document` |
| `rda` (Res. 1888 de 2025) | bloque `paciente` con tipo y número de documento | `hallazgos` en español con la severidad traducida | envoltorio con el prestador y su código de habilitación |
| `anonymized` (Ley 1581 de 2012) | seudónimo con sal y grupo etario en quinquenios | registros sin fecha exacta: sólo el mes | dataset que declara finalidad y base legal |

Cada exportación queda auditada por el Singleton con la acción
`hce.export.generated`, porque toda salida de información clínica debe poder
trazarse (Ley 2015 de 2020).

## ¿Por qué tiene que ser Abstract Factory?

Porque los productos **no son independientes entre sí**, y ésa es justo la
diferencia con el Factory Method que ya usa el módulo IoT:

- El Factory Method resuelve *un* producto: dado un `device_type`, qué
  `ClinicalReading` instanciar.
- El Abstract Factory resuelve *un conjunto*: dado un `standard`, qué tres
  serializadores usar **juntos**.

La consistencia de la familia no es un detalle estético, es un requisito legal y
funcional:

- La referencia con la que la observación apunta al paciente la produce el
  serializador de paciente de esa misma familia (`Patient/12` en FHIR,
  `CC-52847391` en el RDA, `SUJ-a3f9…` en el dataset). Cruzarlas rompe el
  documento.
- La anonimización es una propiedad del documento **entero**, no de una pieza:
  no basta con ocultar el nombre si las observaciones conservan la fecha exacta
  de medición, ni con agrupar la edad si el sobre no declara la base legal del
  tratamiento. Al venir los tres productos de `AnonymizedExchangeFactory`, esa
  garantía la sostiene el sistema de tipos y no la memoria del programador. La
  prueba `la_exportacion_anonimizada_no_publica_ningun_dato_identificable`
  verifica precisamente eso sobre el JSON completo.

Sin el patrón, el exportador tendría un `match` por estándar en cada uno de los
tres pasos —tres puntos distintos donde equivocarse— y nada impediría combinar
mal las piezas. Con él, añadir un formato nuevo (CDA, HL7 v2, PDF firmado) es
escribir una fábrica con sus tres productos y registrar una línea en
`ClinicalExchangeFactoryResolver`: el exportador, el controlador y la ruta no se
tocan (principio abierto/cerrado).

