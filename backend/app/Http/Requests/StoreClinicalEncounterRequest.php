<?php

namespace App\Http\Requests;

use App\Support\Encounters\EncounterDirectorResolver;
use App\Support\Templates\TemplateLibrary;
use App\Support\Templates\TemplateRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida la petición de una nota de atención.
 *
 * Las reglas comunes viven aquí; las propias de cada tipo de atención las
 * aporta el director correspondiente (`payloadRules()`), igual que las fábricas
 * del Factory Method aportan las suyas por dispositivo.
 */
class StoreClinicalEncounterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    private ?TemplateRegistry $registry = null;

    /**
     * Si la petición trae una plantilla, se copia el prototipo y sus valores
     * rellenan los huecos ANTES de validar.
     *
     * Lo que envía el profesional siempre gana: la plantilla es un punto de
     * partida, nunca una imposición. Y el resto del flujo no se entera de que
     * existen plantillas —director, builder y producto siguen igual—.
     */
    protected function prepareForValidation(): void
    {
        $key = $this->input('template');

        if (! is_string($key) || ! $this->registry()->has($key)) {
            return;   // una clave desconocida la reporta la regla de abajo
        }

        // `get()` devuelve una copia: el catálogo original no se toca.
        $this->merge([...$this->registry()->get($key)->toPayload(), ...$this->all()]);
    }

    /** Se memoriza para no recomponer el catálogo dos veces por petición. */
    private function registry(): TemplateRegistry
    {
        return $this->registry ??= (new TemplateLibrary)->registry();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $comunes = [
            // Opcional: sin plantilla, el flujo es exactamente el de siempre.
            'template' => ['sometimes', 'nullable', 'string', Rule::in($this->registry()->keys())],
            'encounter_type' => ['required', 'string', Rule::in(EncounterDirectorResolver::supportedTypes())],
            'patient_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'professional_license' => ['required', 'string', 'max:40'],
            'attended_at' => ['sometimes', 'date'],

            'chief_complaint' => ['required', 'string', 'min:3', 'max:500'],
            'present_illness' => ['required', 'string', 'min:10'],
            'history' => ['sometimes', 'nullable', 'string'],

            'diagnoses' => ['required', 'array', 'min:1'],
            'diagnoses.*.code' => ['required', 'string', 'regex:/^[A-Z]\d{2}(\.?[0-9X])?$/'],
            'diagnoses.*.description' => ['required', 'string', 'max:250'],
            'diagnoses.*.primary' => ['sometimes', 'boolean'],

            'treatment_plan' => ['required', 'string', 'min:10'],

            'prescriptions' => ['sometimes', 'array'],
            'prescriptions.*.active_ingredient' => ['required', 'string', 'max:120'],
            'prescriptions.*.dose' => ['required', 'string', 'max:60'],
            'prescriptions.*.frequency' => ['required', 'string', 'max:60'],
            'prescriptions.*.duration_days' => ['required', 'integer', 'between:1,365'],
            'prescriptions.*.notes' => ['sometimes', 'nullable', 'string', 'max:250'],
        ];

        $tipo = (string) $this->input('encounter_type');

        if (! in_array($tipo, EncounterDirectorResolver::supportedTypes(), true)) {
            return $comunes;
        }

        return [...$comunes, ...(new EncounterDirectorResolver)->for($tipo)->payloadRules()];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'template.in' => 'La plantilla indicada no existe.',
            'encounter_type.required' => 'Debe indicarse el tipo de atención.',
            'encounter_type.in' => 'El tipo de atención indicado no está soportado.',
            'diagnoses.required' => 'La nota debe incluir al menos un diagnóstico.',
            'diagnoses.*.code.regex' => 'El diagnóstico debe usar un código CIE-10 válido (por ejemplo I10).',
            'physical_exam.prohibited' => 'No puede documentarse un examen físico en una teleconsulta.',
            'consent.accepted' => 'La teleconsulta exige el consentimiento informado del paciente.',
        ];
    }
}
