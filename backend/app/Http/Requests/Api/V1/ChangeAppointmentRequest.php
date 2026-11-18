<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Los horarios que el paciente elige al cambiar su cita y, si quiere, el motivo. */
class ChangeAppointmentRequest extends FormRequest
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
            'chosen' => ['required', 'array', 'min:1'],
            'chosen.*' => ['required', 'date_format:Y-m-d H:i', 'distinct'],
            'reason' => ['nullable', 'string', 'max:250'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['chosen' => 'Horario', 'reason' => 'Motivo'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['chosen.required' => 'Elige al menos un horario.'];
    }
}
