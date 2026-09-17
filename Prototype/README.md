# PATRON DE DISEÑO PROTOTYPE


## ¿Por qué Prototype en este proyecto?

Un médico de consulta externa documenta veinte o treinta controles de
hipertensión en una jornada. Todos comparten el diagnóstico (I10), el plan de
manejo, la medicación habitual y el intervalo de control; lo único que cambia es
el paciente. Rellenar los quince pasos del Builder cada vez no es sólo lento:
**es la causa principal de que las notas clínicas salgan incompletas**, porque
bajo presión asistencial se escribe lo mínimo.

La solución real que usan todas las HCE es la plantilla. Y una plantilla no es
una clase: es un **objeto ya configurado** del que se saca una copia y se ajusta.
Ése es exactamente el problema del Prototype —cuando crear el objeto desde cero
es caro o repetitivo, se clona uno existente— y trae dos consecuencias que en
una historia clínica no son teóricas:

1. **La copia tiene que ser profunda.** El `clone` de PHP es superficial: copiaría
   la plantilla pero los dos objetos seguirían compartiendo las mismas
   prescripciones. Ajustar la dosis de un paciente cambiaría entonces la
   plantilla institucional —y con ella la dosis del siguiente paciente—.
2. **La copia tiene que despersonalizarse.** Al crear una plantilla desde una
   nota real hay que descartar la anamnesis, los antecedentes y los hallazgos de
   ese paciente. Arrastrarlos es el error de *copy-forward*, una fuente
   documentada de daño al paciente y de historias que describen a quien no es.

Por eso `__clone()` se escribe a mano en lugar de dejar que PHP improvise.

## Casos de uso concretos

*1. Plantillas de atención clínica (implementado)*
El registro guarda una instancia configurada de cada motivo de consulta
frecuente y entrega copias. El médico carga una, la ajusta y el Builder sigue
validando el contenido mínimo igual que siempre.

*2. Protocolos institucionales de tratamiento*
Un protocolo de anticoagulación o de manejo de sepsis se define una vez y cada
paciente recibe una copia que se personaliza por peso, función renal o alergias,
sin que las modificaciones vuelvan al protocolo.

*3. Series de citas recurrentes*
Un paciente crónico con control trimestral durante dos años son ocho citas
idénticas salvo la fecha: se clona la cita prototipo y se desplaza el
calendario, en vez de construir ocho agendamientos desde cero.


## ¿ Donde de usa ?

> **Implementación:** el patrón prototype está implementado en la interfaz
> `ClinicalPrototype` (`backend/app/Support/Templates/ClinicalPrototype.php`), el
> prototipo concreto `EncounterTemplate`
> (`backend/app/Support/Templates/EncounterTemplate.php`) y el registro de
> prototipos `TemplateRegistry`. Se usa desde `ClinicalTemplateController` y desde
> `StoreClinicalEncounterRequest`.

Extracto de `backend/app/Support/Templates/EncounterTemplate.php`:

```php
final class EncounterTemplate implements ClinicalPrototype
{
    /** @var list<DiagnosisDraft> */
    private array $diagnoses = [];

    /** @var list<PrescriptionDraft> */
    private array $prescriptions = [];

    /** LA OPERACIÓN DEL PATRÓN: `clone` dispara `__clone()`. */
    public function copy(): static
    {
        return clone $this;
    }

    /**
     * Gancho de copia de PHP: se ejecuta sobre el objeto YA duplicado.
     *
     * Sin este método la copia sería SUPERFICIAL y los arreglos de la copia
     * apuntarían a los MISMOS objetos que el original. Ajustar la dosis del
     * borrador cambiaría la plantilla institucional, y con ella la dosis de
     * todos los pacientes que se atiendan después.
     */
    public function __clone(): void
    {
        $this->diagnoses = array_map(
            fn (DiagnosisDraft $diagnostico) => clone $diagnostico,
            $this->diagnoses,
        );

        $this->prescriptions = array_map(
            fn (PrescriptionDraft $medicamento) => clone $medicamento,
            $this->prescriptions,
        );
    }
}
```

El registro de prototipos nunca entrega el original:

```php
final class TemplateRegistry
{
    /** @var array<string, EncounterTemplate> */
    private array $prototypes = [];

    public function get(string $key): EncounterTemplate
    {
        $prototype = $this->prototypes[$key] ?? null;

        if ($prototype === null) {
            throw ValidationException::withMessages([
                'template' => "La plantilla «{$key}» no existe.",
            ]);
        }

        // El copy() no es una optimización: es lo que impide que el catálogo
        // se corrompa la primera vez que alguien ajusta una dosis.
        return $prototype->copy();
    }
}
```

Al crear una plantilla desde una nota real, lo que **no** se copia es lo
importante:

```php
/** Secciones que NUNCA se heredan: son el relato de un paciente concreto. */
public const SECCIONES_NO_HEREDABLES = [
    'present_illness',   // la anamnesis de ESA consulta
    'history',           // los antecedentes de ESE paciente
    'physical_exam',     // los hallazgos de ESA exploración
    'vital_signs',       // las cifras de ESE momento
    'patient_id',
    'professional_license',
    'attended_at',
    'triage',            // la gravedad de ESE episodio
];
```

Y así se engancha con el Builder, sin que éste se entere de que existen
plantillas (`StoreClinicalEncounterRequest`):

```php
protected function prepareForValidation(): void
{
    $key = $this->input('template');

    if (! is_string($key) || ! $this->registry()->has($key)) {
        return;
    }

    // La copia rellena los huecos; lo que envía el profesional siempre gana.
    $this->merge([...$this->registry()->get($key)->toPayload(), ...$this->all()]);
}
```

## ¿Para qué se usa?

Para que el médico arranque la nota desde una plantilla en vez de desde una
página en blanco, sin que eso degrade ni la calidad ni la privacidad del
registro. Hoy funciona a través de los endpoints
`GET /api/encounter-templates`, `GET /api/encounter-templates/{key}/draft` y
`POST /api/encounter-templates`, más el campo opcional `template` en
`POST /api/clinical-encounters`.

El catálogo trae cuatro plantillas institucionales y admite las que guarde cada
profesional:

| Clave | Plantilla | Tipo | Diagnóstico |
|---|---|---|---|
| `hta_control` | Control de hipertensión arterial | Control ambulatorio | I10 |
| `dm2_control` | Control de diabetes mellitus tipo 2 | Control ambulatorio | E11.9 |
| `crisis_hipertensiva` | Urgencia hipertensiva | Urgencias | I16.0 |
| `tele_ira` | Teleorientación por infección respiratoria | Teleconsulta | J06.9 |

Aplicar y guardar plantillas queda auditado por el Singleton con las acciones
`hce.template.applied` y `hce.template.saved`; esta última deja constancia
explícita de qué secciones se omitieron al despersonalizar.

## ¿Por qué tiene que ser Prototype?

Porque el objeto que se necesita **ya existe configurado**, y ninguno de los
patrones creacionales anteriores resuelve eso:

- Una **fábrica** construye desde cero a partir de un tipo. Aquí no hay nada que
  decidir: la plantilla de hipertensión ya está escrita, con su diagnóstico y su
  medicación. Reconstruirla es trabajo repetido.
- El **Builder** sabe armar una nota paso a paso, pero seguiría necesitando que
  alguien le dicte los mismos quince pasos treinta veces al día.
- Sin Prototype haría falta **una subclase por motivo de consulta**:
  `ControlHipertensionFactory`, `ControlDiabetesFactory`,
  `CrisisHipertensivaFactory`… Con él, dar de alta una plantilla es registrar un
  objeto, no escribir código — y por eso el propio médico puede crear las suyas
  desde la interfaz, algo imposible si cada plantilla fuera una clase.

Y sobre todo, porque el patrón obliga a responder una pregunta que en salud tiene
consecuencias: **¿qué significa exactamente copiar esto?**

- Copiar de más y en superficie → dos pacientes comparten la misma prescripción y
  ajustar una dosis contamina la del otro.
- Copiar de más y en profundidad → la plantilla arrastra la historia del paciente
  anterior (*copy-forward*).
- La respuesta correcta es copia profunda **de la estructura** y descarte
  **del relato**, y eso es justo lo que declaran `__clone()` y
  `SECCIONES_NO_HEREDABLES`.

El Prototype no debilita a los patrones anteriores: la copia sólo rellena huecos
y el Builder sigue siendo el que cierra la nota. Una urgencia cargada desde
plantilla sigue rechazándose si le falta el triaje.

