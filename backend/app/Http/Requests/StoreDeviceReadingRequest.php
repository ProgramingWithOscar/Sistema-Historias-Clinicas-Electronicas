<?php

namespace App\Http\Requests;

use App\Support\Iot\DeviceReadingFactoryResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida sólo el "sobre" de la petición: qué dispositivo la envía y a qué
 * paciente pertenece. El contenido de `payload` lo valida la fábrica concreta
 * (`DeviceReadingFactory::payloadRules()`), porque cada dispositivo tiene sus
 * propios campos y rangos.
 */
class StoreDeviceReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'device_type' => ['required', 'string', Rule::in(DeviceReadingFactoryResolver::supportedDevices())],
            'patient_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'payload' => ['required', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'device_type.required' => 'Debe indicarse el tipo de dispositivo.',
            'device_type.in' => 'El dispositivo indicado no está soportado.',
            'payload.required' => 'La lectura del dispositivo es obligatoria.',
        ];
    }
}
