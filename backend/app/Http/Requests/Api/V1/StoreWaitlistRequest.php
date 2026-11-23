<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\DayPeriod;
use App\Models\Account;
use App\Models\RememberedDevice;
use App\Services\Access\PatientAccess;
use App\Support\Phone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Avísame si se libera un horario" en el paso de horarios de la reserva web. Como al reservar: desde un celular
 * recordado no se pide el número, y a un familiar ya registrado no se le vuelven a pedir sus datos.
 */
class StoreWaitlistRequest extends FormRequest
{
    private ?RememberedDevice $device = null;

    private bool $deviceResolved = false;

    public function authorize(): bool
    {
        return true;
    }

    /** El celular recordado desde el que se anota, si lo hay. */
    public function device(): ?RememberedDevice
    {
        if (! $this->deviceResolved) {
            $this->device = app(PatientAccess::class)->device($this);
            $this->deviceResolved = true;
        }

        return $this->device;
    }

    public function privacyAlreadyAccepted(): bool
    {
        /** @var Account|null $account */
        $account = $this->device()?->account;

        return $account?->privacy_accepted_at !== null;
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
            'patient_id' => ['nullable', 'integer'],
            'phone' => [Rule::requiredIf(! $remembered), 'nullable', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail) use ($remembered) {
                if (! $remembered && ($problem = Phone::problem((string) $value)) !== null) {
                    $fail($problem);
                }
            }],
            'first_name' => [Rule::requiredIf($newPerson), 'nullable', 'string', 'max:80'],
            'last_name' => [Rule::requiredIf($newPerson), 'nullable', 'string', 'max:80'],
            'preferred_period' => ['required', Rule::enum(DayPeriod::class)],
            'days' => ['nullable', 'string', 'max:120'],
            'accepts_privacy' => [$this->privacyAlreadyAccepted() ? 'boolean' : 'accepted'],
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
            'preferred_period' => 'A qué hora puedes venir',
            'days' => 'Qué días te sirven',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'accepts_privacy.accepted' => 'Para anotarte, acepta la política de privacidad.',
            'preferred_period.required' => 'Elige a qué hora puedes venir.',
        ];
    }
}
