<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tramo del horario semanal de un doctor (ISO: 1 = lunes … 7 = domingo). */
#[Fillable(['doctor_id', 'weekday', 'starts_at', 'ends_at', 'valid_from', 'valid_until'])]
class DoctorSchedule extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * La hora de un tramo es la misma con o sin segundos: MySQL y MariaDB la devuelven como "09:00:00" y el selector
     * del panel la manda como "09:00". Sin esto, guardar la ficha de un doctor sin tocar su horario volvía a guardar
     * cada tramo y dejaba en el Historial de cambios una fila sin cambios por cada uno.
     */
    public function originalIsEquivalent(mixed $key): bool
    {
        $time = $this->getAttributes()[$key] ?? null;
        $original = $this->getRawOriginal($key);

        if (in_array($key, ['starts_at', 'ends_at'], true) && is_string($time) && is_string($original)) {
            return self::withSeconds($time) === self::withSeconds($original);
        }

        return parent::originalIsEquivalent($key);
    }

    /** "09:00" y "09:00:00", escritas igual para compararlas. */
    private static function withSeconds(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }

    /** Lo mismo que validOn, con el tramo ya cargado: es de ese día de la semana y está vigente en esa fecha. */
    public function isValidOn(CarbonInterface $date): bool
    {
        /** @var CarbonInterface|null $from */
        $from = $this->valid_from;
        /** @var CarbonInterface|null $until */
        $until = $this->valid_until;

        return $this->weekday === $date->dayOfWeekIso
            && ($from === null || $from->toDateString() <= $date->toDateString())
            && ($until === null || $until->toDateString() >= $date->toDateString());
    }

    #[Scope]
    protected function validOn(Builder $query, CarbonInterface $date): Builder
    {
        return $query
            ->where('weekday', $date->dayOfWeekIso)
            ->where(fn (Builder $q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', $date))
            ->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $date));
    }
}
