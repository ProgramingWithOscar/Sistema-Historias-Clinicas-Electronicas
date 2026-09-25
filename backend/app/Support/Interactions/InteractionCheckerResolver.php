<?php

namespace App\Support\Interactions;

use App\Support\Interactions\Adapters\RxNavInteractionAdapter;
use App\Support\Interactions\Adapters\VademecumInteractionAdapter;
use App\Support\Interactions\Contracts\DrugInteractionChecker;
use App\Support\Interactions\External\RxNavClient;
use App\Support\Interactions\External\VademecumNacionalReader;
use Illuminate\Validation\ValidationException;

/**
 * Elige el adaptador según la fuente pedida.
 *
 * Es el único punto del sistema que sabe que RxNav y el vademécum existen. A
 * partir de aquí —verificador, controlador, interfaz— todo programa contra
 * `DrugInteractionChecker`, y por eso cambiar de proveedor no toca ninguna de
 * esas capas.
 */
final class InteractionCheckerResolver
{
    /** @var list<string> */
    private const SOURCES = ['vademecum', 'rxnav'];

    /**
     * @throws ValidationException si la fuente no está soportada
     */
    public function for(?string $source = null): DrugInteractionChecker
    {
        $source ??= (string) config('interactions.default');

        return match ($source) {
            'vademecum' => new VademecumInteractionAdapter(
                new VademecumNacionalReader((string) config('interactions.vademecum.csv'))
            ),
            'rxnav' => new RxNavInteractionAdapter(new RxNavClient),
            default => throw ValidationException::withMessages([
                'source' => "La fuente de interacciones «{$source}» no está soportada.",
            ]),
        };
    }

    /** @return list<string> */
    public static function supportedSources(): array
    {
        return self::SOURCES;
    }

    /**
     * Catálogo para la interfaz: qué fuentes hay y de dónde salen sus datos.
     *
     * @return list<array<string, mixed>>
     */
    public static function catalog(): array
    {
        return [
            [
                'source' => 'vademecum',
                'label' => 'Vademécum institucional',
                'origin' => 'Archivo CSV entregado por el prestador',
                'requires_network' => false,
                'is_default' => config('interactions.default') === 'vademecum',
            ],
            [
                'source' => 'rxnav',
                'label' => 'RxNav — National Library of Medicine',
                'origin' => 'Servicio web externo (códigos RxCUI)',
                'requires_network' => true,
                'is_default' => config('interactions.default') === 'rxnav',
            ],
        ];
    }
}
