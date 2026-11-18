<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\AppointmentOrigin;
use App\Enums\PatientRelationship;
use App\Models\Campaign;
use App\Models\RememberedDevice;
use App\Services\Access\PatientAccess;
use App\Support\Phone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Los datos con que se reserva una cita desde la web. Desde un celular recordado no se pide el número, y a un
 * familiar ya registrado no se le vuelven a pedir sus datos.
 */
class StoreBookingRequest extends FormRequest
{
    private ?RememberedDevice $device = null;

    private bool $deviceResolved = false;

    public function authorize(): bool
    {
        return true;
    }

    /** El celular recordado desde el que se reserva, si lo hay. */
    public function device(): ?RememberedDevice
    {
        if (! $this->deviceResolved) {
            $this->device = app(PatientAccess::class)->device($this);
            $this->deviceResolved = true;
        }

        return $this->device;
    }

    /** La campaña con que reserva: por el código que escribió o por la página de la que llegó. */
    public function campaign(): ?Campaign
    {
        return Campaign::forBooking($this->input('promo_code'), $this->input('campaign_slug'));
    }

    public function privacyAlreadyAccepted(): bool
    {
        return $this->device()?->account->privacy_accepted_at !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'birth_date' => $this->composedBirthDate(),
            'promo_code' => strtoupper(trim((string) $this->input('promo_code'))) ?: null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $newPerson = $this->input('patient_id') === null;
        $remembered = $this->device() !== null;

        return [
            'treatment_id' => ['required', 'integer'],
            'doctor_id' => ['nullable', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'patient_id' => ['nullable', 'integer'],
            'phone' => [Rule::requiredIf(! $remembered), 'nullable', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail) use ($remembered) {
                if (! $remembered && ($problem = Phone::problem((string) $value)) !== null) {
                    $fail($problem);
                }
            }],
            'first_name' => [Rule::requiredIf($newPerson), 'nullable', 'string', 'max:80'],
            'last_name' => [Rule::requiredIf($newPerson), 'nullable', 'string', 'max:80'],
            'who' => ['nullable', Rule::in(['me', 'family'])],
            'relationship' => ['required', Rule::enum(PatientRelationship::class)],
            'birth_date' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'origin' => ['nullable', Rule::enum(AppointmentOrigin::class)],
            'accepts_privacy' => [$this->privacyAlreadyAccepted() ? 'boolean' : 'accepted'],
            'accepts_promotions' => ['nullable', 'boolean'],
            'promo_code' => ['nullable', 'string', 'max:30', function (string $attribute, mixed $value, Closure $fail) {
                if (filled($value) && $this->campaign() === null) {
                    $fail(Campaign::UNKNOWN_CODE);
                }
            }],
            'campaign_slug' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:300'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'phone' => 'Celular',
            'first_name' => 'Nombres',
            'last_name' => 'Apellidos',
            'relationship' => 'Para quién',
            'birth_date' => 'Fecha de nacimiento',
            'note' => 'Comentario',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'accepts_privacy.accepted' => 'Para reservar, acepta la política de privacidad.',
            'birth_date.before' => 'Revisa la fecha de nacimiento.',
            'birth_date.date' => 'Completa el día, el mes y el año de nacimiento.',
            'birth_date.after' => 'Revisa el año de nacimiento.',
        ];
    }

    /**
     * Junta día, mes y año en Y-m-d. Sin ninguno, no hay fecha; con alguno suelto o una fecha imposible
     * (31 de febrero), un valor que la validación rechaza. La fecha va en tres campos porque el calendario del
     * celular abre en el mes actual y llegar a 1985 toma decenas de toques.
     */
    private function composedBirthDate(): ?string
    {
        $parts = [trim((string) $this->input('birth_year')), trim((string) $this->input('birth_month')), trim((string) $this->input('birth_day'))];

        if ($parts === ['', '', '']) {
            return null;
        }

        if (in_array('', $parts, true) || ! checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]) || strlen($parts[0]) !== 4) {
            return 'fecha-incompleta';
        }

        return sprintf('%04d-%02d-%02d', $parts[0], $parts[1], $parts[2]);
    }
}
