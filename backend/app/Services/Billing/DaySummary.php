<?php

namespace App\Services\Billing;

use App\Enums\AppointmentStatus;
use App\Enums\MessageStatus;
use App\Enums\MessageTemplateKey;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Charge;
use App\Models\DailyClosing;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\StaffAlerts;
use App\Services\WhatsApp\WhatsAppOutbox;
use App\Support\Money;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * El resumen del día para quien administra, al cerrar la caja: lo cobrado por medio de pago, el arqueo, los pacientes
 * atendidos, las faltas, las citas que quedaron sin cerrar, los descuentos y cuántas citas del día siguiente siguen
 * sin confirmar. Llega a la campana de quien administra y queda en Avisos, listo para enviarlo por WhatsApp a su
 * celular (o, si nadie que administra tiene uno, al del consultorio).
 */
class DaySummary
{
    /** Cuántos descuentos se nombran uno por uno; del resto se dice cuántos más hubo. */
    private const NAMED_DISCOUNTS = 3;

    public function __construct(
        private readonly StaffAlerts $alerts,
        private readonly WhatsAppOutbox $outbox,
    ) {}

    /** @return int Cuántos avisos de WhatsApp quedaron en Avisos para enviar. */
    public function send(CashClosing $closing, DailyClosing $record): int
    {
        $figures = self::figures($closing, $record);
        $admins = User::query()->where('is_active', true)->where('role', UserRole::Admin)->get();
        // En la campana, un monto no se parte en dos líneas ("S/" al final de una y "240.00" en la otra).
        $body = collect(self::lines($figures))->map(fn (string $line) => str_replace('S/ ', "S/\u{00A0}", e($line)))->implode('<br>');

        foreach ($admins as $admin) {
            $this->alerts->toUser($admin, 'Resumen del '.$figures['fecha'], $body);
        }

        // Si el día se reabrió y se volvió a cerrar, el resumen anterior que no se envió ya no vale.
        WhatsappMessage::query()
            ->pending()
            ->where('template_key', MessageTemplateKey::DaySummary)
            ->where('params->dia', $figures['dia'])
            ->update(['status' => MessageStatus::Cancelled]);

        $phones = self::phones($admins);

        foreach ($phones as $phone) {
            $this->outbox->queueForPhone($phone, MessageTemplateKey::DaySummary, $figures);
        }

        return count($phones);
    }

    /**
     * Los datos del resumen, con el nombre que usa el texto del aviso ({cobrado}, {arqueo}…).
     *
     * @return array<string, string>
     */
    public static function figures(CashClosing $closing, DailyClosing $record): array
    {
        $date = $closing->date;
        $day = [$date->startOfDay(), $date->endOfDay()];
        $appointments = Appointment::query()->whereBetween('starts_at', $day)->get(['patient_id', 'status']);
        // El día que sigue al cerrado; si se cierra un día pasado, el que empieza hoy.
        $next = $date->addDay()->max(CarbonImmutable::today());

        return [
            'dia' => $date->toDateString(),
            'fecha' => $date->translatedFormat('l j \d\e F'),
            'cobrado' => self::collected($closing),
            'arqueo' => self::cashCount($record),
            'cerro' => $record->closedBy?->name ?? 'alguien que ya no está',
            'atendidos' => (string) $appointments->where('status', AppointmentStatus::Completed)->pluck('patient_id')->unique()->count(),
            'faltas' => (string) $appointments->where('status', AppointmentStatus::NoShow)->count(),
            'sin_cerrar' => (string) $closing->unclosedAppointments()->count(),
            'descuentos' => self::discounts($day),
            // Aquí van los cambios de precio del día (Charge::priceChange: lo que el dentista bajó o subió del precio
            // sugerido) cuando el cierre los muestre: un dato más, por ejemplo 'precios', con su línea en lines() y en
            // el texto del aviso (MessageTemplateKey::DaySummary y MessageTemplateResource::PLACEHOLDERS).
            'siguiente' => $next->translatedFormat('l j'),
            'sin_confirmar' => (string) Appointment::query()->awaitingConfirmation()->whereDate('starts_at', $next)->count(),
        ];
    }

    /**
     * Lo mismo que el aviso de WhatsApp, línea por línea, para la campana.
     *
     * @param  array<string, string>  $figures
     * @return list<string>
     */
    public static function lines(array $figures): array
    {
        return [
            'Cobrado: '.$figures['cobrado'],
            'Arqueo: '.$figures['arqueo'].' · cerró '.$figures['cerro'],
            'Pacientes atendidos: '.$figures['atendidos'].' · Faltas: '.$figures['faltas'].' · Citas sin cerrar: '.$figures['sin_cerrar'],
            'Descuentos: '.$figures['descuentos'],
            'Citas del '.$figures['siguiente'].' sin confirmar: '.$figures['sin_confirmar'],
        ];
    }

    /** "S/ 290.00 (Efectivo S/ 50.00 · Yape S/ 240.00)". */
    private static function collected(CashClosing $closing): string
    {
        $byMethod = collect(PaymentMethod::cases())
            ->filter(fn (PaymentMethod $method) => $closing->totalFor($method) > 0)
            ->map(fn (PaymentMethod $method) => $method->getLabel().' '.Money::format($closing->totalFor($method)));

        return Money::format($closing->total()).($byMethod->isEmpty() ? '' : ' ('.$byMethod->implode(' · ').')');
    }

    /** "cuadra", "faltan S/ 10.00" o "sobran S/ 5.00", con lo que se anotó al cerrar. */
    private static function cashCount(DailyClosing $record): string
    {
        $difference = $record->difference();
        $count = match (true) {
            abs($difference) < 0.005 => 'cuadra',
            $difference < 0 => 'faltan '.Money::format(-$difference),
            default => 'sobran '.Money::format($difference),
        };

        return $count.(filled($record->notes) ? ' ('.$record->notes.')' : '');
    }

    /**
     * "S/ 30.00 en 2 cargos (Ana Flores: familiar · Luis Soto)", o "ninguno".
     *
     * @param  array{CarbonImmutable, CarbonImmutable}  $day
     */
    private static function discounts(array $day): string
    {
        /** @var Collection<int, Charge> $charges */
        $charges = Charge::query()
            ->valid()
            ->with('patient')
            ->whereBetween('charged_at', $day)
            ->where('discount', '>', 0)
            ->orderBy('charged_at')
            ->get();

        if ($charges->isEmpty()) {
            return 'ninguno';
        }

        $named = $charges->take(self::NAMED_DISCOUNTS)
            ->map(fn (Charge $charge) => $charge->patient->full_name.(filled($charge->discount_reason) ? ': '.$charge->discount_reason : ''))
            ->implode(' · ');
        $more = $charges->count() > self::NAMED_DISCOUNTS ? ' y '.($charges->count() - self::NAMED_DISCOUNTS).' más' : '';

        return Money::format($charges->sum(fn (Charge $charge) => (float) $charge->discount))
            .' en '.($charges->count() === 1 ? '1 cargo' : $charges->count().' cargos')
            .' ('.$named.$more.')';
    }

    /**
     * A qué celulares va el aviso: el de cada persona que administra y tiene uno; si nadie lo tiene, el WhatsApp del
     * consultorio.
     *
     * @param  Collection<int, User>  $admins
     * @return list<string>
     */
    private static function phones(Collection $admins): array
    {
        $phones = $admins->pluck('phone')
            ->filter(fn (?string $phone) => filled($phone) && Phone::isValid($phone))
            ->map(fn (string $phone) => Phone::normalize($phone))
            ->unique()
            ->values()
            ->all();
        $clinic = (string) Setting::get('clinic.whatsapp');

        return $phones === [] && Phone::isValid($clinic) ? [Phone::normalize($clinic)] : $phones;
    }
}
