<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el «guardar como plantilla»: de qué nota sale el prototipo y con qué
 * nombre queda en el catálogo.
 */
class StoreClinicalTemplateRequest extends FormRequest
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
            'encounter_id' => ['required', 'integer', 'exists:clinical_encounters,id'],
            'key' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/', 'unique:clinical_templates,key'],
            'name' => ['required', 'string', 'max:160'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'encounter_id.required' => 'Debe indicarse la nota de la que sale la plantilla.',
            'key.regex' => 'La clave sólo admite minúsculas, números y guion bajo.',
            'key.unique' => 'Ya existe una plantilla con esa clave.',
        ];
    }
}
