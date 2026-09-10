<?php

namespace App\Support\Encounters;

use App\Support\Encounters\Directors\EmergencyEncounterDirector;
use App\Support\Encounters\Directors\EncounterDirector;
use App\Support\Encounters\Directors\OutpatientControlDirector;
use App\Support\Encounters\Directors\TeleconsultationDirector;
use Illuminate\Validation\ValidationException;

/**
 * Elige el director según el tipo de atención que llega en la petición.
 *
 * Mismo papel que los resolvers de los otros dos patrones: concentra en un solo
 * sitio la lista de directores para que el controlador sólo conozca la clase
 * abstracta `EncounterDirector`.
 */
final class EncounterDirectorResolver
{
    /** @var array<string, class-string<EncounterDirector>> */
    private const DIRECTORS = [
        EncounterType::Emergency->value => EmergencyEncounterDirector::class,
        EncounterType::OutpatientControl->value => OutpatientControlDirector::class,
        EncounterType::Teleconsultation->value => TeleconsultationDirector::class,
    ];

    /**
     * @throws ValidationException si el tipo de atención no está soportado
     */
    public function for(string $encounterType): EncounterDirector
    {
        $director = self::DIRECTORS[$encounterType] ?? null;

        if ($director === null) {
            throw ValidationException::withMessages([
                'encounter_type' => "El tipo de atención «{$encounterType}» no está soportado.",
            ]);
        }

        return new $director;
    }

    /** @return list<string> */
    public static function supportedTypes(): array
    {
        return array_keys(self::DIRECTORS);
    }

    /**
     * Catálogo para la interfaz: qué tipos hay y qué exige cada uno.
     *
     * @return list<array<string, mixed>>
     */
    public static function catalog(): array
    {
        return array_map(
            fn (EncounterType $type) => [
                'encounter_type' => $type->value,
                'label' => $type->label(),
                'in_person' => $type->isInPerson(),
                'required_sections' => match ($type) {
                    EncounterType::Emergency => ['Triaje', 'Examen físico', 'Signos vitales'],
                    EncounterType::OutpatientControl => ['Antecedentes', 'Próxima cita'],
                    EncounterType::Teleconsultation => ['Consentimiento', 'Canal'],
                },
            ],
            EncounterType::cases(),
        );
    }
}
