<?php

namespace App\Http\Requests;

use App\Support\Interop\ClinicalExchangeFactoryResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida la solicitud de exportación: qué estándar y de qué paciente.
 *
 * El estándar es el dato en runtime que selecciona la familia completa de
 * serializadores (Abstract Factory).
 */
class StoreClinicalExportRequest extends FormRequest
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
            'standard' => ['required', 'string', Rule::in(ClinicalExchangeFactoryResolver::supportedStandards())],
            'patient_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'standard.required' => 'Debe indicarse el estándar de intercambio.',
            'standard.in' => 'El estándar indicado no está soportado.',
        ];
    }
}
