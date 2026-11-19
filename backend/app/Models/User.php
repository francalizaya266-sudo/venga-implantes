<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/** Persona del equipo que entra al panel: administrador, recepción o doctor. */
#[Fillable(['name', 'email', 'password', 'must_change_password', 'role', 'is_dentist', 'phone', 'is_active', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, Notifiable;

    /** Segundo factor del panel (app autenticadora y códigos de recuperación): cifrados y fuera del historial. */
    use InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery;

    protected static function booted(): void
    {
        // La contraseña y el secreto del segundo factor nunca se guardan en el historial, pero sí que cambiaron.
        static::updated(function (User $user): void {
            if ($user->wasChanged('password')) {
                $user->auditEvent('password_changed');
            }

            if ($user->wasChanged('app_authentication_secret')) {
                $user->auditEvent(filled($user->app_authentication_secret) ? 'mfa_enabled' : 'mfa_disabled');
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'role' => UserRole::class,
            'is_dentist' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** Perfil de doctor (también lo puede tener un administrador odontólogo). */
    public function doctor(): HasOne
    {
        return $this->hasOne(Doctor::class);
    }

    /** Ya entró al panel o tiene ficha de doctor: lo que hizo lleva su nombre, así que no se borra; se le quita el acceso. */
    public function hasHistory(): bool
    {
        return $this->last_login_at !== null || $this->doctor()->exists();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isReception(): bool
    {
        return $this->role === UserRole::Reception;
    }

    public function isDoctor(): bool
    {
        return $this->role === UserRole::Doctor;
    }

    /** Solo los doctores (y el administrador si es odontólogo) ven lo clínico. */
    public function canSeeClinical(): bool
    {
        return $this->isDoctor() || ($this->isAdmin() && $this->is_dentist);
    }

    /** Administración y recepción manejan la agenda de todos los doctores. */
    public function managesAllAgendas(): bool
    {
        return $this->isAdmin() || $this->isReception();
    }

    /** Id del doctor cuya agenda maneja este usuario, si es doctor. */
    public function doctorId(): ?int
    {
        return $this->doctor?->getKey();
    }
}
