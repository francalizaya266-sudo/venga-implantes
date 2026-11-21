<?php

namespace App\Filament\Actions;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentMethod;
use App\Filament\Forms\PaymentFields;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Models\Appointment;
use App\Models\DailyClosing;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Billing\BillingService;
use App\Services\Billing\CashClosing;
use App\Services\Patients\PatientInsights;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * Registrar un pago: el mismo formulario en Caja, en la ficha del paciente, en las listas de cobro y al
 * terminar la atención. Dice lo que debe y por qué, propone el saldo y deja la constancia a un clic.
 */
final class RegisterPaymentAction
{
    /**
     * @param  Closure|null  $patient  Devuelve el paciente (puede recibir $record). Sin él, se elige en el formulario.
     * @param  Closure|null  $amount  Lo que se propone cobrar (puede recibir $record). Por defecto, lo que debe.
     */
    public static function make(?Closure $patient = null, ?Closure $amount = null): Action
    {
        $resolve = fn (Action $action): ?Patient => $patient ? $action->evaluate($patient) : null;

        return Action::make('registerPayment')
            ->label('Cobrar')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color(fn (Action $action) => ($resolve($action)?->balance() ?? 0) > 0 ? 'success' : 'gray')
            ->visible(fn () => self::user()->can('create', Payment::class))
            ->modalHeading(fn (Action $action) => ($found = $resolve($action)) ? 'Cobrar a '.$found->full_name : 'Cobrar')
            // "Cobrar" grande a la derecha, como "Agendar" y "Pasar a sala".
            ->modalSubmitAction(fn (Action $action) => $action->label('Cobrar')->color('primary')->size(Size::Large))
            ->modalFooterActionsAlignment(Alignment::End)
            ->modalWidth('lg')
            ->fillForm(function (Action $action) use ($resolve, $amount) {
                $found = $resolve($action);
                self::chargeDueInstallments($found);
                $proposed = $amount ? $action->evaluate($amount) : max(0, $found?->balance() ?? 0);
                $now = now()->format('Y-m-d H:i');

                return [
                    'patient_id' => $found?->getKey(),
                    'amount' => $proposed > 0 ? round($proposed, 2) : null,
                    'method' => ($found?->usualPaymentMethod() ?? PaymentMethod::Cash)->value,
                    'paid_at' => $now,
                    'opened_at' => $now,
                ];
            })
            ->schema([
                Select::make('patient_id')
                    ->label('Paciente')
                    ->placeholder('Busca por nombre, DNI o celular')
                    ->searchable()
                    ->required()
                    // Antes de escribir, a quienes se les cobra desde aquí: lo de hoy sin pagar, y quienes están en la clínica y deben.
                    ->options(fn () => self::toCollect())
                    ->getSearchResultsUsing(fn (string $search) => Patient::query()
                        ->search($search)
                        ->with('account')
                        ->limit(20)
                        ->get()
                        ->mapWithKeys(fn (Patient $found) => [$found->getKey() => $found->selectLabel()]))
                    ->getOptionLabelUsing(fn ($value) => Patient::with('account')->find($value)?->selectLabel())
                    ->live()
                    ->afterStateUpdated(function (Set $set, mixed $state) {
                        if (! $found = Patient::find($state)) {
                            return;
                        }

                        self::chargeDueInstallments($found);
                        $balance = $found->balance();
                        $set('amount', $balance > 0 ? $balance : null);
                        $set('method', $found->usualPaymentMethod()->value);
                    })
                    ->hidden($patient !== null),
                Text::make(fn (Get $get) => self::owed(Patient::find($get('patient_id'))))
                    ->visible(fn (Get $get) => filled($get('patient_id'))),
                Text::make(fn (Get $get) => self::installments($get)['text'] ?? null)
                    ->color(fn (Get $get) => (self::installments($get)['late'] ?? false) ? 'danger' : 'gray')
                    ->visible(fn (Get $get) => self::installments($get) !== null),
                self::visitUnderwayCallout(),
                ...PaymentFields::make(),
                DateTimePicker::make('paid_at')
                    ->label('Fecha')
                    ->seconds(false)
                    ->maxDate(now()->endOfDay())
                    ->required()
                    ->rule(fn () => self::openDay()),
                TextInput::make('notes')
                    ->label('Nota')
                    ->placeholder('Por ejemplo: cuota 2 de ortodoncia')
                    ->maxLength(255),
                // Cuándo se abrió la ventana: si la fecha sigue siendo esa, el pago se guarda con la hora en que se cobra.
                Hidden::make('opened_at'),
            ])
            ->action(function (array $data, Action $action) use ($resolve) {
                $found = $resolve($action) ?? Patient::findOrFail($data['patient_id']);
                $data['paid_at'] = self::paidAt($data['paid_at'] ?? null, $data['opened_at'] ?? null);
                $payment = app(BillingService::class)->registerPayment($found, $data, self::user());

                self::notifyRegistered($payment);
            });
    }

    /**
     * "Pago registrado" (o "Devolución registrada"), con el saldo que quedó y la constancia para imprimir o enviar.
     * Al cobrar se dice también cuándo vuelve: su próxima cita si ya la tiene o, si no, lo que le toca y el botón
     * para agendarla ahí mismo, mientras el paciente sigue en el mostrador.
     */
    public static function notifyRegistered(Payment $payment): void
    {
        $next = (float) $payment->amount > 0 ? self::nextVisit($payment) : null;

        Notification::make()
            ->success()
            ->title((float) $payment->amount < 0 ? 'Devolución registrada' : 'Pago registrado')
            ->body(collect(['Saldo de '.$payment->patient->full_name.': '.Money::balance($payment->patient->balance()), $next['text'] ?? null])->filter()->implode('<br>'))
            ->actions(array_values(array_filter([
                ($next['url'] ?? null) ? Action::make('book')
                    ->label('Agendar próxima cita')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->url($next['url'])
                    ->button() : null,
                Action::make('receipt')
                    ->label('Ver constancia')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->url(self::receiptUrl($payment), shouldOpenInNewTab: true)
                    ->button()
                    ->color(($next['url'] ?? null) ? 'gray' : 'primary'),
            ])))
            // Se queda en pantalla con la constancia a mano mientras el paciente sigue en el mostrador.
            ->persistent()
            ->send();
    }

    /**
     * Cuándo vuelve el paciente que acaba de pagar: la cita que ya tiene, la sesión que sigue de su plan con su
     * fecha sugerida, o su control. Con url, falta agendarla.
     *
     * @return array{text: string, url: string|null}
     */
    private static function nextVisit(Payment $payment): array
    {
        $insights = app(PatientInsights::class);
        $patient = $payment->patient;
        $user = self::user();

        // Pagó mientras está en su cita de hoy: lo que sigue se ve al terminar, con su cobro.
        if ($underway = self::visitUnderway($payment->patient_id)) {
            return ['text' => 'Hoy sigue en su cita de las '.self::startsAt($underway)->format('H:i').': la próxima se agenda al terminar.', 'url' => null];
        }

        if ($booked = $insights->upcomingVisit($patient)) {
            return ['text' => 'Su próxima cita: '.$booked->starts_at->translatedFormat('l j \d\e F, g:i a').' · '.$booked->treatment->name.'.', 'url' => null];
        }

        $canBook = $user->can('create', Appointment::class);
        $doctorId = $payment->appointment?->doctor_id ?? $payment->doctor_id;

        if ($suggested = $insights->suggestedNextVisit($patient, withTooth: $user->canSeeClinical())) {
            return [
                'text' => 'Sin próxima cita. Le toca: '.$suggested['label'].', el '.$suggested['on']->translatedFormat('l j \d\e F').' ('.mb_strtolower(mb_substr($suggested['why'], 0, 1)).mb_substr($suggested['why'], 1).').',
                'url' => $canBook ? AppointmentResource::nextVisitUrl($patient, $doctorId, $suggested['on']) : null,
            ];
        }

        $checkup = $insights->checkup($patient);

        return [
            'text' => $checkup
                ? 'Sin próxima cita. Su '.$checkup['kind']->checkupLabel().' le toca en '.$checkup['on']->translatedFormat('F \d\e Y').'.'
                : 'Sin próxima cita.',
            'url' => $canBook ? AppointmentResource::nextVisitUrl($patient, $doctorId, $checkup && ! $checkup['due'] ? $checkup['on'] : null) : null,
        ];
    }

    /**
     * Si una cuota de su plan ya venció y todavía no se cargó (la tarea de las 6:00 no corrió), entra al saldo antes
     * de decir cuánto debe: como en su ficha, su cita, su plan y el cobro al terminar la atención.
     */
    private static function chargeDueInstallments(?Patient $patient): void
    {
        if ($patient !== null) {
            app(BillingService::class)->chargeDueInstallments($patient, self::user());
        }
    }

    /** La hoja de la constancia, para imprimirla o enviarla por WhatsApp. */
    public static function receiptUrl(Payment $payment): string
    {
        return Filament::getPanel('admin')->route('print.receipt', ['payment' => $payment]);
    }

    /** Un pago no entra en un día cuya caja ya se cerró (salvo que lo registre la administradora). */
    public static function openDay(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (filled($value) && DailyClosing::locksFor(self::user(), $value)) {
                $fail(DailyClosing::LOCKED);
            }
        };
    }

    /**
     * A quién se le cobra antes de buscarlo. Primero "Olivia Arenas · debe S/ 120.00": a quienes se les cargó algo hoy
     * y no lo pagaron, los mismos que cuentan "Cobrado hoy" y el cierre del día, quien más debe primero. Después
     * "Juan Pérez · en sala · debe S/ 500.00": quienes están en la clínica y deben de antes, por la hora de su cita;
     * a primera hora son los únicos. Lo que dice es todo su saldo, que es lo que se propone cobrar.
     *
     * @return array<int, string>
     */
    private static function toCollect(): array
    {
        // Quién está hoy en la clínica, por la hora de su cita: en sala o con el doctor.
        $here = Appointment::query()
            ->whereBetween('starts_at', [today()->startOfDay(), today()->endOfDay()])
            ->whereIn('status', AppointmentStatus::inClinic())
            ->orderBy('starts_at')
            ->get(['patient_id', 'status'])
            ->unique('patient_id')
            ->mapWithKeys(fn (Appointment $visit) => [$visit->patient_id => self::isBeingSeen($visit) ? ' · en atención' : ' · en sala']);
        $label = fn (Patient $found) => $found->full_name.$here->get($found->getKey(), '').' · debe '.Money::format((float) $found->balance_due);

        $fromToday = (new CashClosing(CarbonImmutable::today()))->pending()
            ->take(20)
            ->mapWithKeys(fn (array $line) => [$line['patient']->getKey() => $label($line['patient'])])
            ->all();

        $waiting = Patient::query()
            ->owing()
            ->withBalance()
            ->whereKey($here->keys()->all())
            ->whereKeyNot(array_keys($fromToday))
            ->get()
            ->sortBy(fn (Patient $found) => $here->keys()->search($found->getKey()))
            ->mapWithKeys(fn (Patient $found) => [$found->getKey() => $label($found)])
            ->all();

        return $fromToday + $waiting;
    }

    /**
     * Las cuotas de su plan: la que se le pasó, en rojo, y cuándo vence la próxima. "Cuota vencida desde el 1 de
     * noviembre. La próxima, S/ 250.00, vence el martes 1 de diciembre." Una sola vez por pedido.
     *
     * @return array{text: string, late: bool}|null
     */
    private static function installments(Get $get): ?array
    {
        $patientId = $get('patient_id');

        if (blank($patientId)) {
            return null;
        }

        $memo = 'register-payment.installments.'.$patientId;

        if (! request()->attributes->has($memo)) {
            request()->attributes->set($memo, ($patient = Patient::find($patientId)) ? self::installmentsOf($patient) : null);
        }

        return request()->attributes->get($memo);
    }

    /** @return array{text: string, late: bool}|null */
    private static function installmentsOf(Patient $patient): ?array
    {
        $insights = app(PatientInsights::class);
        $late = $insights->lateInstallments($patient);
        $next = $insights->upcomingInstallment($patient, today()->addYear());
        $nextText = $next ? Money::format($next['due']).', vence el '.$next['on']->translatedFormat('l j \d\e F') : null;

        return match (true) {
            $late !== null => [
                'text' => 'Cuota vencida desde el '.$late['since']->translatedFormat('j \d\e F').'.'.($nextText ? ' La próxima, '.$nextText.'.' : ''),
                'late' => true,
            ],
            $nextText !== null => ['text' => 'Próxima cuota: '.$nextText.'.', 'late' => false],
            default => null,
        };
    }

    /**
     * Está en su cita de hoy (en sala o con el doctor): lo de hoy todavía no entra a lo que debe. Si puede esperar,
     * conviene cobrar todo junto al terminar, con el "Cobrar" de su cita.
     */
    private static function visitUnderwayCallout(): Callout
    {
        return Callout::make(fn (Get $get) => ($visit = self::visitUnderwayOf($get)) ? self::visitUnderwayHeading($visit) : null)
            ->key('visitUnderway')
            ->description('Lo de hoy se carga al terminar y todavía no está en lo que debe. Si puede esperar, cobra todo junto al terminar.')
            ->icon(Heroicon::OutlinedClock)
            ->warning()
            ->visible(fn (Get $get) => self::visitUnderwayOf($get) !== null);
    }

    /** "Lo están atendiendo: Endodoncia de las 10:00" o "Está en sala: …". */
    private static function visitUnderwayHeading(Appointment $visit): string
    {
        /** @var Treatment $treatment */
        $treatment = $visit->treatment;

        return (self::isBeingSeen($visit) ? 'Lo están atendiendo' : 'Está en sala').': '.$treatment->name.' de las '.self::startsAt($visit)->format('H:i');
    }

    private static function visitUnderwayOf(Get $get): ?Appointment
    {
        $patientId = $get('patient_id');

        if (blank($patientId)) {
            return null;
        }

        $memo = 'register-payment.underway.'.$patientId;

        if (! request()->attributes->has($memo)) {
            request()->attributes->set($memo, self::visitUnderway((int) $patientId));
        }

        return request()->attributes->get($memo);
    }

    /** Su cita de hoy en la que está ahora: en sala o con el doctor. */
    private static function visitUnderway(int $patientId): ?Appointment
    {
        return Appointment::query()
            ->with('treatment')
            ->where('patient_id', $patientId)
            ->whereBetween('starts_at', [today()->startOfDay(), today()->endOfDay()])
            ->whereIn('status', AppointmentStatus::inClinic())
            ->orderBy('starts_at')
            ->first();
    }

    /**
     * La fecha del pago. Si no se cambió la que puso la ventana al abrirse, es ahora mismo: así lo que se le cargó
     * mientras la ventana estaba abierta (la cuota que venció hoy, al elegirlo) entra antes del pago, y la constancia
     * no dice "A favor" a quien quedó al día.
     */
    private static function paidAt(?string $chosen, ?string $openedAt): CarbonInterface|string|null
    {
        if (blank($chosen) || blank($openedAt)) {
            return $chosen;
        }

        return Carbon::parse($chosen)->equalTo(Carbon::parse($openedAt)) ? now() : $chosen;
    }

    /** Ya pasó con el doctor (si no, está en sala). */
    private static function isBeingSeen(Appointment $visit): bool
    {
        /** @var AppointmentStatus $status */
        $status = $visit->status;

        return $status === AppointmentStatus::InProgress;
    }

    private static function startsAt(Appointment $appointment): CarbonInterface
    {
        /** @var CarbonInterface $startsAt */
        $startsAt = $appointment->starts_at;

        return $startsAt;
    }

    /** "Debe S/ 120.00: Curación con resina S/ 120.00 (5/11)." */
    private static function owed(?Patient $patient): string
    {
        if (! $patient) {
            return '';
        }

        $balance = $patient->balance();

        if ($balance <= 0) {
            return $balance < 0 ? 'Tiene a favor '.Money::format(-$balance).': lo que pague queda a cuenta.' : 'Está al día: lo que pague queda a cuenta.';
        }

        $owed = app(BillingService::class)->unpaidCharges($patient);
        $detail = $owed->take(4)
            ->map(fn (array $row) => $row['charge']->description.' '.Money::format($row['due']).' ('.$row['charge']->charged_at->format('j/n').')')
            ->implode(' · ');

        return 'Debe '.Money::format($balance).': '.$detail.($owed->count() > 4 ? ' y '.($owed->count() - 4).' más' : '').'.';
    }

    private static function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
