<?php

namespace App\Support\Interop;

use App\Support\Interop\Anonymized\AnonymizedExchangeFactory;
use App\Support\Interop\Contracts\ClinicalExchangeFactory;
use App\Support\Interop\Fhir\FhirR4ExchangeFactory;
use App\Support\Interop\Rda\RdaExchangeFactory;
use Illuminate\Validation\ValidationException;

/**
 * Elige la familia a partir del estándar que pide el receptor.
 *
 * Es el único punto del sistema que menciona una fábrica concreta. A partir de
 * aquí —exportador, controlador, vista— todo trabaja contra
 * `ClinicalExchangeFactory` y sus productos abstractos.
 */
final class ClinicalExchangeFactoryResolver
{
    /** @var array<string, class-string<ClinicalExchangeFactory>> */
    private const FACTORIES = [
        ExchangeStandard::FhirR4->value => FhirR4ExchangeFactory::class,
        ExchangeStandard::Rda->value => RdaExchangeFactory::class,
        ExchangeStandard::Anonymized->value => AnonymizedExchangeFactory::class,
    ];

    /**
     * @throws ValidationException si el estándar no está soportado
     */
    public function for(string $standard): ClinicalExchangeFactory
    {
        $factory = self::FACTORIES[$standard] ?? null;

        if ($factory === null) {
            throw ValidationException::withMessages([
                'standard' => "El estándar «{$standard}» no está soportado.",
            ]);
        }

        return new $factory;
    }

    /** @return list<string> */
    public static function supportedStandards(): array
    {
        return array_keys(self::FACTORIES);
    }

    /**
     * Catálogo para la interfaz: qué familias hay y con qué norma se respaldan.
     *
     * @return list<array<string, mixed>>
     */
    public static function catalog(): array
    {
        return array_map(
            fn (ExchangeStandard $standard) => [
                'standard' => $standard->value,
                'label' => $standard->label(),
                'legal_basis' => $standard->legalBasis(),
                'identifies_patient' => $standard->identifiesPatient(),
            ],
            ExchangeStandard::cases(),
        );
    }
}
