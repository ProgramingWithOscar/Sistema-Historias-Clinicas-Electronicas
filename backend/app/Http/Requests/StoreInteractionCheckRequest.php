<?php

namespace App\Http\Requests;

use App\Support\Interactions\InteractionCheckerResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida una consulta de interacciones: qué principios activos y contra qué
 * fuente.
 */
class StoreInteractionCheckRequest extends FormRequest
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
            // Con un solo fármaco no hay nada que comparar.
            'drugs' => ['required', 'array', 'min:2', 'max:20'],
            'drugs.*' => ['required', 'string', 'max:120'],
            'source' => ['sometimes', 'nullable', 'string', Rule::in(InteractionCheckerResolver::supportedSources())],
            'patient_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'drugs.required' => 'Debe indicarse la lista de principios activos.',
            'drugs.min' => 'Se necesitan al menos dos principios activos para verificar interacciones.',
            'source.in' => 'La fuente de interacciones indicada no está soportada.',
        ];
    }
}
