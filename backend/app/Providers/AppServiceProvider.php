<?php

namespace App\Providers;

use App\Filament\Pages\Auth\SetUpTwoFactor;
use App\Services\Dashboard\TodayPulse;
use App\Services\Inventory\SupplyForecast;
use App\Services\Web\ClinicInfo;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\DevCommands;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Una vez por petición: el horario se arma con los horarios de los doctores.
        $this->app->scoped(ClinicInfo::class);
        $this->app->scoped(SupplyForecast::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fechas en español: "martes 4 de noviembre".
        Carbon::setLocale(config('app.locale'));
        CarbonImmutable::setLocale(config('app.locale'));

        // En desarrollo, avisar de consultas N+1 y atributos inexistentes.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Una sola regla para toda contraseña del panel (usuarios, doctores, "Mi perfil" y recuperación):
        // "12345678" o "password" ya no pasan.
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        Event::listen(Login::class, fn (Login $event) => $event->user->forceFill(['last_login_at' => now()])->saveQuietly());

        // Entró escribiendo su contraseña (no con la sesión recordada): si le falta la doble verificación, al activarla
        // no se le vuelve a pedir.
        Event::listen(Login::class, function (Login $event): void {
            if (! Auth::guard($event->guard)->viaRemember()) {
                SetUpTwoFactor::rememberPasswordWasTyped();
            }
        });

        // Quien pone su contraseña con el enlace del correo ya la eligió: no se le vuelve a pedir al entrar.
        Event::listen(PasswordReset::class, fn (PasswordReset $event) => $event->user->forceFill(['must_change_password' => false])->saveQuietly());

        // Lo que guarda una persona lo ven las demás en pocos segundos, sin recargar.
        TodayPulse::watch();

        $this->limitPatientRequests();

        $this->observeRequests();

        self::trustConfiguredProxies();

        // "composer dev" levanta también el frontend (React), que vive en la carpeta de al lado.
        if (is_file(base_path('../frontend/package.json'))) {
            DevCommands::register('npm --prefix ../frontend run dev', 'vite');
        }
    }

    /**
     * Detrás del proxy de la DMZ (Azure), $request->ip() sería la IP del proxy: los límites por IP se compartirían
     * entre todos los pacientes y Laravel no sabría que la conexión es HTTPS. Confiar en el proxy le devuelve la IP
     * real (X-Forwarded-For) y el esquema (X-Forwarded-Proto). Sin TRUSTED_PROXIES no se confía en nadie.
     */
    public static function trustConfiguredProxies(): void
    {
        $proxies = trim((string) config('consultorio.trusted_proxies'));

        if ($proxies === '') {
            return;
        }

        TrustProxies::at($proxies === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $proxies)))));
    }

    /**
     * Lo que vigilan Azure y GitHub Actions (/up) y lo que queda en cada línea del registro "json", además del
     * identificador de la petición (AddRequestContext): la ruta y quién la hizo, por su id y no por su nombre.
     */
    private function observeRequests(): void
    {
        // /up responde 500 si la base de datos no contesta, y no solo cuando Laravel no arranca.
        Event::listen(DiagnosingHealth::class, fn () => DB::select('select 1'));

        Event::listen(RouteMatched::class, fn (RouteMatched $event) => Context::add('route', $event->route->getName() ?? $event->route->uri()));
        Event::listen(Authenticated::class, fn (Authenticated $event) => Context::add('user_id', $event->user->getAuthIdentifier()));
    }

    /**
     * Cuántas veces por minuto se puede abrir o usar lo del paciente (Mi cuenta y el enlace de WhatsApp) desde una
     * misma conexión. Cada cuenta va por su lado: la web hace varias peticiones a la API por pantalla (la página,
     * los horarios de cada día, cada botón), y en la sala de espera muchos celulares comparten la conexión.
     */
    private function limitPatientRequests(): void
    {
        RateLimiter::for('patient-pages', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('patient-api', fn (Request $request) => Limit::perMinute(240)->by($request->ip()));
    }
}
