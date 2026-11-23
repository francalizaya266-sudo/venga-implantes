<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\WebPresence;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasWebSlug;
use App\Services\Agenda\LearnedDurations;
use Database\Factories\DoctorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Perfil del doctor: datos públicos, horario y agenda. La clave del enlace de su agenda para el celular va oculta:
 * no sale en la web ni en el historial de cambios.
 *
 * @property-read string $name_with_article
 */
#[Fillable(['user_id', 'name', 'slug', 'specialty', 'cop_number', 'rne_number', 'bio', 'education', 'photo_path', 'agenda_color', 'is_public', 'accepts_online_booking', 'sort_order', 'is_active'])]
#[Hidden(['calendar_token'])]
class Doctor extends Model
{
    /** @use HasFactory<DoctorFactory> */
    use Auditable, HasFactory, HasWebSlug, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'accepts_online_booking' => 'boolean',
            'is_active' => 'boolean',
            'calendar_token' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function treatments(): BelongsToMany
    {
        return $this->belongsToMany(Treatment::class, 'doctor_treatment')->withPivot('duration_minutes', 'learned_minutes', 'learned_samples')->withTimestamps();
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(DoctorSchedule::class);
    }

    public function scheduleBlocks(): HasMany
    {
        return $this->hasMany(ScheduleBlock::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Citas de hoy en adelante que todavía esperan atención. Si el doctor deja de atender, siguen en la agenda
     * hasta que se atiendan, se cambien o se cancelen: no pueden desaparecer.
     */
    public function appointmentsToAttend(): HasMany
    {
        return $this->appointments()->whereIn('status', AppointmentStatus::open())->where('starts_at', '>=', today());
    }

    /** Dónde aparece: solo en el consultorio, también en la página web o también recibiendo reservas en línea. */
    public function webPresence(): WebPresence
    {
        return WebPresence::fromFlags((bool) $this->is_public, (bool) $this->accepts_online_booking);
    }

    /** Ya atendió o tiene citas: su nombre sale en la agenda y en las historias, así que no se elimina. */
    public function hasHistory(): bool
    {
        return $this->appointments()->exists()
            || collect([Consultation::class, TreatmentPlan::class, Prescription::class])
                ->contains(fn (string $model) => $model::query()->where('doctor_id', $this->getKey())->exists());
    }

    /**
     * Duración del tratamiento para este doctor: la propia puesta a mano; si no, lo que realmente suele tardar
     * (cuando se nota la diferencia); si no, la general del tratamiento.
     */
    public function durationFor(Treatment $treatment): int
    {
        $pivot = $this->treatments()->whereKey($treatment->getKey())->first()?->pivot;

        if ($pivot?->duration_minutes) {
            return (int) $pivot->duration_minutes;
        }

        $learned = $pivot?->learned_minutes !== null ? (int) $pivot->learned_minutes : null;

        return LearnedDurations::applies($learned, (int) $treatment->duration_minutes) ? $learned : (int) $treatment->duration_minutes;
    }

    /**
     * El nombre como se dice dentro de una frase al paciente, con su artículo: "con la Dra. Ana Torres", "con el Dr.
     * Luis Ramos". Sin "Dr." ni "Dra." delante va tal cual. Es la misma regla de la web (withArticle).
     */
    protected function nameWithArticle(): Attribute
    {
        return Attribute::get(fn (): string => match (true) {
            (bool) preg_match('/^(dra\.|doctora)\s/iu', (string) $this->name) => 'la '.$this->name,
            (bool) preg_match('/^(dr\.|doctor)\s/iu', (string) $this->name) => 'el '.$this->name,
            default => (string) $this->name,
        });
    }

    /** Con un solo doctor activo, la columna, el filtro y las tablas "por doctor" sobran. */
    public static function severalActive(): bool
    {
        return once(fn () => static::query()->active()->count() > 1);
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
