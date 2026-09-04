<?php

namespace App\Support\Iot;

use Illuminate\Validation\ValidationException;

/**
 * Selecciona el creador concreto a partir del tipo de dispositivo.
 *
 * Es el único punto del sistema que conoce la lista de creadores. Concentrar
 * aquí el `match` es deliberado: así el controlador programa sólo contra
 * `DeviceReadingFactory`, y dar de alta un dispositivo nuevo toca una única
 * línea en lugar de esparcirse por validación, persistencia y alertas.
 */
final class DeviceReadingFactoryResolver
{
    /** @var array<string, class-string<DeviceReadingFactory>> */
    private const FACTORIES = [
        'glucometer' => GlucometerFactory::class,
        'sphygmomanometer' => SphygmomanometerFactory::class,
        'pulse_oximeter' => PulseOximeterFactory::class,
    ];

    /**
     * @throws ValidationException si el dispositivo no está soportado
     */
    public function for(string $deviceType): DeviceReadingFactory
    {
        $factory = self::FACTORIES[$deviceType] ?? null;

        if ($factory === null) {
            throw ValidationException::withMessages([
                'device_type' => "El dispositivo «{$deviceType}» no está soportado.",
            ]);
        }

        return new $factory;
    }

    /** @return list<string> */
    public static function supportedDevices(): array
    {
        return array_keys(self::FACTORIES);
    }
}
