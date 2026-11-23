<?php

namespace App\Services\Booking;

use App\Enums\DayPeriod;

/**
 * Lo que el paciente llenó en "Avísame si se libera un horario", en el paso de horarios de la reserva web (el
 * tratamiento y el doctor son los que ya eligió en la reserva).
 */
final readonly class WebWaitlistData
{
    public function __construct(
        public string $phone,
        // Familiar ya registrado (solo se puede elegir desde un celular recordado).
        public ?int $patientId = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public DayPeriod $period = DayPeriod::Any,
        // Los días que le sirven, con sus palabras ("lunes o sábados").
        public ?string $days = null,
        public bool $acceptsPrivacy = false,
    ) {}
}
