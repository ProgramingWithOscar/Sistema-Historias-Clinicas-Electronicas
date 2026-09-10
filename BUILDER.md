# Patrón Builder en el código

## ¿Dónde se usa?

En el módulo de **notas de atención clínica** (`backend/app/Support/Encounters`),
que documenta lo que ocurre en cada consulta: el artefacto central de toda
historia clínica electrónica.

| Rol GoF | Archivo |
|---|---|
| **Producto** (inmutable) | `backend/app/Support/Encounters/ClinicalNote.php` |
| **Builder** | `backend/app/Support/Encounters/ClinicalNoteBuilder.php` |
| **Director** (abstracto) | `backend/app/Support/Encounters/Directors/EncounterDirector.php` |
| **Directores concretos** | `EmergencyEncounterDirector.php`, `OutpatientControlDirector.php`, `TeleconsultationDirector.php` |
| Partes del producto | `Parts/Diagnosis.php`, `Parts/Prescription.php` |
| Vocabularios | `EncounterType.php`, `TriageLevel.php` |
| Guardián del contenido mínimo | `Exceptions/IncompleteClinicalNoteException.php` |
| Selección del director en runtime | `backend/app/Support/Encounters/EncounterDirectorResolver.php` |
| Quién lo usa | `backend/app/Http/Controllers/Api/ClinicalEncounterController.php` |
| Persistencia | `backend/app/Models/ClinicalEncounter.php` + migración `create_clinical_encounters_table` |
| Pruebas del patrón | `backend/tests/Unit/ClinicalNoteBuilderTest.php`, `backend/tests/Feature/ClinicalEncounterTest.php` |

## El núcleo del patrón

Cada paso añade una parte y devuelve `$this`; `build()` cierra la construcción
validando el contenido mínimo de la Resolución 1995 de 1999.

```php
final class ClinicalNoteBuilder
{
    public function withChiefComplaint(string $complaint): self
    {
        $this->chiefComplaint = trim($complaint);

        return $this;
    }

    public function withPhysicalExam(string $findings): self
    {
        // Regla de dominio: en una teleconsulta no hay exploración que documentar.
        if ($this->type !== null && ! $this->type->isInPerson()) {
            throw new LogicException(
                'No puede documentarse un examen físico en una atención no presencial.'
            );
        }

        $this->physicalExam = trim($findings);

        return $this;
    }

    /** EL CIERRE: una nota a medio llenar no llega a existir como objeto. */
    public function build(): ClinicalNote
    {
        $missing = $this->missingSections();

        if ($missing !== []) {
            throw new IncompleteClinicalNoteException($missing);
        }

        return ClinicalNote::fromBuilder([...]);
    }
}
```

El producto no se puede construir por fuera, porque su constructor es privado y
todas sus propiedades son de sólo lectura:

```php
final class ClinicalNote
{
    private function __construct(
        public readonly EncounterType $type,
        public readonly int $patientId,
        // …quince partes, todas readonly
    ) {}
}
```

El director aporta la secuencia:

```php
abstract class EncounterDirector
{
    abstract protected function assembleSpecificSections(
        ClinicalNoteBuilder $builder, array $payload, User $patient
    ): void;

    final public function construct(array $payload, User $patient, User $professional): ClinicalNote
    {
        $builder = new ClinicalNoteBuilder;

        $builder->ofType($this->type())->forPatient($patient)->attendedBy(...);   // 1. encabezado
        $builder->withChiefComplaint(...)->withPresentIllness(...);               // 2. anamnesis

        $this->assembleSpecificSections($builder, $payload, $patient);            // 3. lo propio

        foreach ($payload['diagnoses'] ?? [] as $d) { $builder->addDiagnosis(...); } // 4. CIE-10
        $builder->withTreatmentPlan(...);                                         // 5. plan

        return $builder->build();                                                 // 6. cierre
    }
}
```

Y el de teleconsulta muestra que un director también **omite** pasos:

```php
final class TeleconsultationDirector extends EncounterDirector
{
    protected function assembleSpecificSections(ClinicalNoteBuilder $builder, array $payload, User $patient): void
    {
        // Nunca llama a withPhysicalExam(): el paciente no está presente.
        $builder
            ->withDeviceReadings($this->recentReadings($patient, limit: 10))
            ->withMetadata([
                'consent' => (bool) ($payload['consent'] ?? false),
                'legal_basis' => 'Resolución 2654 de 2019',
            ]);
    }
}
```

## ¿Para qué se usa?

Para armar la nota de atención paso a paso y entregarla sólo si cumple el
contenido mínimo legal.

| Tipo | Secciones que añade su director | Qué exige el builder |
|---|---|---|
| `emergency` | triaje, examen físico, servicio, tiempo máximo de espera | triaje + signos vitales (Res. 5596 de 2015) |
| `outpatient_control` | antecedentes, próxima cita, programa | antecedentes |
| `teleconsultation` | consentimiento, canal, modalidad — **nunca examen físico** | consentimiento (Res. 2654 de 2019) |

Los signos vitales los toma el director de las lecturas que ya normalizó el
Factory Method: la cifra que queda en la historia es la que midió el equipo, no
una transcripción. Cada nota queda auditada con `hce.encounter.created`.

## ¿Por qué tiene que ser Builder?

1. **Un constructor no sirve.** Quince parámetros, la mayoría opcionales y
   dependientes del tipo de atención; los constructores telescópicos son peores.
2. **Los setters tampoco.** Dejarían la nota mutable y permitirían que un objeto
   a medio llenar circule por el sistema. La historia clínica no se enmienda
   (Res. 1995 de 1999): el producto es `readonly` y su constructor `private`, así
   que el único camino es `build()`.
3. **La validación pertenece al cierre.** Sólo con la nota completa se sabe si
   falta algo, y `build()` informa de *todas* las secciones faltantes de una vez
   en lugar de morir en la primera.
4. **Extensión sin modificación (OCP).** Añadir una nota de cirugía o de
   enfermería es escribir un director y registrar una línea en el resolver.
5. **La secuencia no se puede evadir.** `construct()` es `final`: ninguna
   subclase puede omitir el encabezado o los diagnósticos.

Reparto de responsabilidades:

| Pieza | Qué sabe |
|---|---|
| **Builder** | *cómo* se añade cada parte y qué es una nota válida |
| **Director** | *qué* partes lleva su tipo de atención y en qué orden |
| **Producto** | nada: sólo guarda el resultado, ya validado |

## Relación con los otros tres patrones

Los cuatro conviven en el mismo recorrido del dato (ver [SINGLETON.md](SINGLETON.md),
[FACTORY_METHOD.md](FACTORY_METHOD.md) y [ABSTRACT_FACTORY.md](ABSTRACT_FACTORY.md)):

| | Singleton | Factory Method | Abstract Factory | Builder |
|---|---|---|---|---|
| Qué resuelve | Unicidad y orden global | Diferir la elección de **una** clase | Coherencia de **una familia** | Construir **un objeto complejo** |
| Cuántos objetos | Uno por proceso | Uno por lectura | Tres por exportación | Uno por atención |
| Dato que decide | — | `device_type` | `standard` | `encounter_type` |
| Cómo se obtiene | `AuditLogger::getInstance()` | `$resolver->for($deviceType)` | `$resolver->for($standard)` | `$director->construct(...)` |

El recorrido completo: el **Factory Method** normaliza la lectura del
dispositivo, el **Builder** la incorpora a la nota de atención junto con el
diagnóstico y el plan, el **Abstract Factory** publica todo eso en el estándar
que pida el receptor, y el **Singleton** deja trazado cada paso.

## Endpoints

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/api/encounter-types` | Tipos de atención y lo que exige cada uno |
| `GET` | `/api/clinical-encounters` | Últimas 25 notas (filtro opcional `encounter_type`) |
| `POST` | `/api/clinical-encounters` | Registra una nota |

Ejemplo de petición:

```http
POST /api/clinical-encounters
{
  "encounter_type": "emergency",
  "patient_id": 10,
  "professional_license": "RM-12345",
  "chief_complaint": "Cefalea intensa y visión borrosa",
  "present_illness": "Cuadro de tres horas de evolución, sin trauma previo.",
  "triage": "II",
  "physical_exam": "Paciente álgida, TA 190/125, sin focalización neurológica.",
  "diagnoses": [
    { "code": "I10", "description": "Hipertensión esencial", "primary": true }
  ],
  "treatment_plan": "Antihipertensivo endovenoso y monitorización continua."
}
```

Si falta contenido mínimo, la respuesta es `422` y la nota **no se persiste**:

```json
{
  "message": "La nota clínica no cumple el contenido mínimo: clasificación de triaje, signos vitales.",
  "errors": {
    "note": ["clasificación de triaje", "signos vitales"]
  }
}
```

## Pruebas

```bash
cd backend && php artisan test --filter="ClinicalNoteBuilderTest|ClinicalEncounterTest"
```

Cubren que el producto sólo puede crearse desde el builder y es inmutable, que
cada paso devuelve el mismo builder, que cada tipo de atención exige lo suyo, que
la teleconsulta rechaza el examen físico, que el director no revienta ante un
payload incompleto sino que deja hablar a `build()`, que una urgencia sin signos
vitales no llega a persistirse, y que toda nota queda auditada.
