<?php

namespace App\Models;

use App\Support\Iot\Readings\ReadingSeverity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lectura de dispositivo IoT ya normalizada por su fábrica.
 *
 * La tabla es única para todos los dispositivos porque el producto que crea el
 * Factory Method también lo es: código LOINC, valor, unidad y severidad. Lo
 * específico de cada equipo vive en `components`.
 */
#[Fillable([
    'device_type',
    'loinc_code',
    'display',
    'value',
    'unit',
    'severity',
    'components',
    'patient_id',
    'measured_at',
])]
class DeviceReading extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'float',
            'components' => 'array',
            'severity' => ReadingSeverity::class,
            'measured_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }
}
