<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Estado en lista de espera. */
enum WaitlistStatus: string implements HasColor, HasLabel
{
    case Waiting = 'waiting';
    case Offered = 'offered';
    case Booked = 'booked';
    case Removed = 'removed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Waiting => 'En espera',
            self::Offered => 'Avisado',
            self::Booked => 'Agendado',
            self::Removed => 'Retirado',
        };
    }

    /** "En espera" es lo normal de la lista: va en gris, para que el ámbar quede para lo que pide hacer algo ya. */
    public function getColor(): string
    {
        return match ($this) {
            self::Waiting => 'gray',
            self::Offered => 'info',
            self::Booked => 'success',
            self::Removed => 'gray',
        };
    }
}
