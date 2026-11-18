<?php

namespace App\Http\Requests\Api\V1;

use App\Models\MedicalBackground;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** "Antes de tu cita": lo que el paciente cuenta de su salud desde el celular recordado. */
class StoreHealthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'has_allergies' => ['required', 'in:no,yes'],
            'allergies' => ['nullable', 'required_if:has_allergies,yes', 'string', 'max:300'],
            'takes_medications' => ['required', 'in:no,yes'],
            'medications' => ['nullable', 'required_if:takes_medications,yes', 'string', 'max:300'],
            'conditions' => ['nullable', 'array'],
            'conditions.*' => [Rule::in(array_keys(MedicalBackground::CONDITIONS))],
            'is_pregnant' => ['nullable', 'boolean'],
            'is_breastfeeding' => ['nullable', 'boolean'],
            'smoker' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'has_allergies.required' => 'Elige No o Sí.',
            'allergies.required_if' => 'Escribe a qué.',
            'takes_medications.required' => 'Elige No o Sí.',
            'medications.required_if' => 'Escribe cuáles.',
        ];
    }
}
