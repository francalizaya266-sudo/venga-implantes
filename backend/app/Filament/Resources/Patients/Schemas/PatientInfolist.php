<?php

namespace App\Filament\Resources\Patients\Schemas;

use App\Enums\LabOrderStatus;
use App\Enums\PatientRelationship;
use App\Enums\TreatmentPlanStatus;
use App\Filament\Resources\Accounts\AccountActions;
use App\Filament\Resources\Accounts\AccountResource;
use App\Filament\Resources\LabOrders\LabOrderResource;
use App\Filament\Resources\Patients\Pages\ViewPatient;
use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Resources\Patients\RelationManagers\ChargesRelationManager;
use App\Filament\Resources\TreatmentPlans\Tables\TreatmentPlansTable;
use App\Filament\Resources\TreatmentPlans\TreatmentPlanResource;
use App\Filament\Support\ClinicalAlerts;
use App\Filament\Support\PageLayout;
use App\Filament\Support\PatientSummary;
use App\Filament\Support\PatientTimeline;
use App\Models\Charge;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Services\Clinical\ConsentService;
use App\Support\Money;
use App\Support\Phone;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

/**
 * La ficha del paciente. Arriba, el siguiente paso y su estado (saldo, próxima cita, control, laboratorio) y la
 * alerta médica. Debajo, en dos columnas: a la izquierda su perfil (celular y familia, datos y preferencias) y a
 * la derecha, en pestañas, lo que pasó (citas, atenciones, pagos y mensajes), su cuenta con los cargos, y sus
 * presupuestos y trabajos de laboratorio. En el celular, una debajo de la otra.
 */
class PatientInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(PageLayout::PROFILE_COLUMNS)
            ->components([
                View::make('filament.patients.summary')
                    ->viewData(fn (Patient $record, $livewire) => PatientSummary::viewData($record, auth()->user(), $livewire, withProfile: true))
                    ->columnSpanFull(),
                // La alerta médica arriba de todo, para quien atiende al paciente.
                Group::make(fn (Patient $record) => auth()->user()->can('viewClinical', $record) ? array_filter([ClinicalAlerts::callout($record, canComplete: true)]) : [])
                    ->key('clinicalAlert')
                    ->extraAttributes(['class' => 'pt-alert'])
                    ->columnSpanFull(),
                // Con el menú cerrado o en un monitor grande, el perfil sigue angosto y las pestañas se quedan con el resto.
                self::profile()
                    ->columnSpan(PageLayout::NARROW),
                self::sections()
                    ->columnSpan(PageLayout::WIDE),
            ]);
    }

    /** El perfil, a la izquierda: "3 visitas · S/ 350.00 pagados · sin faltas" y, debajo, cómo ubicarlo, sus datos y sus preferencias. */
    private static function profile(): Section
    {
        return Section::make('Perfil del paciente')
            ->key('profile')
            ->description(fn (Patient $record) => PatientSummary::profileLine($record, auth()->user()))
            ->compact()
            ->extraAttributes(['class' => 'pt-profile'])
            ->schema([self::phoneAndFamily(), self::personalData(), self::preferences()]);
    }

    /**
     * El celular de la familia, con WhatsApp y Llamar al lado, y quiénes lo comparten (cada uno lleva a su ficha),
     * el parentesco y, si es menor, su apoderado. Desde aquí se le envía el enlace de Mi cuenta.
     */
    private static function phoneAndFamily(): Section
    {
        return Section::make('Contacto y familia')
            ->key('family')
            ->contained(false)
            ->inlineLabel()
            ->headerActions([
                AccountActions::sendAccessLink(fn (Patient $record) => $record->account)
                    ->link()
                    ->visible(fn (Patient $record) => $record->account !== null && ! $record->account->isBlocked() && auth()->user()->can('view', $record->account)),
            ])
            ->schema([
                TextEntry::make('account.phone')
                    ->label('Celular')
                    ->formatStateUsing(fn (string $state) => Phone::display($state))
                    ->url(fn (Patient $record) => $record->account && auth()->user()->can('view', $record->account) ? AccountResource::getUrl('view', ['record' => $record->account]) : null)
                    // Debajo, si está verificado y, al lado, WhatsApp y Llamar.
                    ->belowContent(fn (Patient $record) => array_values(array_filter([
                        self::phoneStatus($record),
                        self::contactButtons($record),
                    ])))
                    ->placeholder('Sin celular'),
                TextEntry::make('relationship')
                    ->label('Parentesco')
                    ->hidden(fn ($state) => blank($state)),
                TextEntry::make('family')
                    ->label('Familia con este celular')
                    ->state(fn (Patient $record) => $record->account
                        ?->patients()
                        ->whereKeyNot($record->getKey())
                        ->orderBy('first_name')
                        ->get()
                        ->map(fn (Patient $relative) => new HtmlString('<a href="'.e(PatientResource::getUrl('view', ['record' => $relative])).'">'.e($relative->full_name).'</a>'
                            .($relative->relationship ? ' · '.e($relative->relationship->getLabel()) : '')))
                        ->all())
                    ->listWithLineBreaks()
                    ->extraAttributes(['class' => 'pt-family'])
                    ->placeholder('Nadie más'),
                // Quién firma por él: su apoderado o, si no se registró, el titular del celular.
                TextEntry::make('guardian_name')
                    ->label('Apoderado')
                    ->state(function (Patient $record) {
                        $signer = app(ConsentService::class)->defaultSigner($record);

                        return $signer['signer_relationship'] === PatientRelationship::Self
                            ? null
                            : collect([$signer['signer_name'], $signer['signer_relationship']->getLabel(), $signer['signer_document']])->filter()->implode(' · ');
                    })
                    ->placeholder('Falta: quién firma por él')
                    ->visible(fn (Patient $record) => $record->isMinor()),
            ]);
    }

    /** WhatsApp y Llamar, al lado del celular. */
    private static function contactButtons(Patient $patient): ?Actions
    {
        $contact = PatientSummary::contact($patient);

        if ($contact === null) {
            return null;
        }

        return Actions::make([
            Action::make('whatsapp')
                ->label('WhatsApp')
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->color('success')
                ->size('sm')
                ->outlined()
                ->url($contact['whatsapp'], shouldOpenInNewTab: true),
            Action::make('call')
                ->label('Llamar')
                ->icon(Heroicon::OutlinedPhone)
                ->color('gray')
                ->size('sm')
                ->outlined()
                ->url($contact['call']),
        ]);
    }

    /**
     * Si el celular está verificado. Queda verificado solo cuando el paciente abre un enlace que le mandamos,
     * como el de Mi cuenta: no hace falta otro botón para eso.
     */
    private static function phoneStatus(Patient $patient): ?Text
    {
        return match (true) {
            $patient->account === null => null,
            $patient->account->isBlocked() => Text::make('Bloqueado para reservas web')->color('danger'),
            $patient->account->isVerified() => Text::make('Verificado por WhatsApp')->color('success'),
            default => Text::make('Sin verificar: se verifica al abrir Mi cuenta')->color('warning'),
        };
    }

    /**
     * Etiqueta y dato en la misma línea. Documento, edad y desde cuándo viene ya están bajo el nombre
     * (PatientSummary::subheading); la última visita, en las etiquetas y en la actividad.
     */
    private static function personalData(): Section
    {
        return Section::make('Datos personales')
            ->key('personal')
            ->contained(false)
            ->inlineLabel()
            ->schema([
                TextEntry::make('birth_date')
                    ->label('Nacimiento')
                    ->date('d/m/Y')
                    ->hidden(fn ($state) => blank($state)),
                TextEntry::make('sex')
                    ->label('Sexo')
                    ->hidden(fn ($state) => blank($state)),
                TextEntry::make('email')
                    ->label('Correo')
                    ->hidden(fn ($state) => blank($state)),
                TextEntry::make('address')
                    ->label('Dirección')
                    ->state(fn (Patient $record) => collect([$record->address, $record->district])->filter()->implode(', '))
                    ->hidden(fn ($state) => blank($state)),
                TextEntry::make('emergency_contact_name')
                    ->label('Emergencia')
                    ->state(fn (Patient $record) => collect([$record->emergency_contact_name, $record->emergency_contact_phone])->filter()->implode(' · '))
                    ->hidden(fn ($state) => blank($state)),
                TextEntry::make('admin_notes')
                    ->label('Notas internas')
                    ->hidden(fn ($state) => blank($state)),
            ])
            ->visible(fn (Patient $record) => collect([$record->birth_date, $record->sex, $record->email, $record->address, $record->district, $record->emergency_contact_name, $record->emergency_contact_phone, $record->admin_notes])->filter()->isNotEmpty());
    }

    /** Cuándo suele venir, con qué paga y cómo nos encontró: al agendarle y al cobrarle, eso ya viene propuesto. */
    private static function preferences(): Section
    {
        return Section::make('Preferencias')
            ->key('preferences')
            ->contained(false)
            ->schema([
                View::make('filament.patients.chips')
                    ->viewData(fn (Patient $record) => ['chips' => PatientSummary::preferences($record, auth()->user()), 'label' => 'Preferencias']),
            ])
            ->visible(fn (Patient $record) => PatientSummary::preferences($record, auth()->user()) !== []);
    }

    /** A la derecha, en pestañas: lo que pasó, su cuenta, y sus presupuestos y trabajos de laboratorio. */
    private static function sections(): Tabs
    {
        return Tabs::make('Ficha del paciente')
            ->key('sections')
            ->persistTabInQueryString('seccion')
            ->tabs([
                Tab::make('Actividad')
                    ->key('actividad')
                    ->schema([
                        View::make('filament.patients.timeline')
                            ->viewData(fn (Patient $record, $livewire) => [
                                'events' => PatientTimeline::for($record, auth()->user()),
                                // "Anular" abre su ventana en la ficha, que es la que la tiene.
                                'canVoid' => $livewire instanceof ViewPatient,
                            ]),
                    ]),
                Tab::make('Cuenta y cargos')
                    ->key('cuenta')
                    ->badge(fn (Patient $record) => Charge::query()->whereBelongsTo($record)->valid()->count() ?: null)
                    ->visible(fn () => auth()->user()->can('viewAny', Charge::class))
                    ->schema([
                        TextEntry::make('account_totals')
                            ->hiddenLabel()
                            ->state(fn (Patient $record) => self::accountTotals($record))
                            ->color('gray'),
                        Livewire::make(ChargesRelationManager::class, fn (Patient $record) => [
                            ...ChargesRelationManager::getDefaultProperties(),
                            'ownerRecord' => $record,
                            'pageClass' => ViewPatient::class,
                        ])->key('charges'),
                    ]),
                Tab::make('Presupuestos y laboratorio')
                    ->key('presupuestos')
                    ->badge(fn (Patient $record) => count(self::planRows($record)) + count(self::labRows($record)) ?: null)
                    ->visible(fn () => TreatmentPlanResource::canViewAny() || auth()->user()->can('viewAny', LabOrder::class))
                    ->schema([
                        View::make('filament.patients.plans')
                            ->viewData(fn (Patient $record) => ['heading' => 'Presupuestos y planes', 'rows' => self::planRows($record)])
                            ->visible(fn (Patient $record) => self::planRows($record) !== []),
                        View::make('filament.patients.plans')
                            ->viewData(fn (Patient $record) => ['heading' => 'Laboratorio', 'rows' => self::labRows($record)])
                            ->visible(fn (Patient $record) => self::labRows($record) !== []),
                        TextEntry::make('no_plans')
                            ->hiddenLabel()
                            ->state('Todavía no tiene presupuestos ni trabajos de laboratorio.')
                            ->color('gray')
                            ->visible(fn (Patient $record) => self::planRows($record) === [] && self::labRows($record) === []),
                    ]),
            ]);
    }

    /** "Cargado S/ 900.00 · Pagado S/ 50.00 · Debe S/ 850.00": de dónde sale el saldo. */
    private static function accountTotals(Patient $patient): string
    {
        $paid = (float) $patient->payments()->whereNull('voided_at')->sum('amount');
        $balance = $patient->balance();

        return 'Cargado '.Money::format($balance + $paid).' · Pagado '.Money::format($paid).' · '.Money::balance($balance);
    }

    /**
     * Los presupuestos del paciente que quien mira puede abrir, los vigentes primero. Quien no ve lo clínico
     * lee el procedimiento, no la pieza (Ley 29733).
     *
     * @return list<array{url: string, summary: string, total: string, status: TreatmentPlanStatus, since: ?string, payment: ?HtmlString, open: bool}>
     */
    private static function planRows(Patient $patient): array
    {
        if (! TreatmentPlanResource::canViewAny()) {
            return [];
        }

        $withTooth = auth()->user()->can('viewClinical', $patient);

        return once(fn () => TreatmentPlanResource::forPatient($patient)
            ->map(fn (TreatmentPlan $plan) => [
                'url' => TreatmentPlanResource::getUrl('view', ['record' => $plan]),
                'summary' => TreatmentPlansTable::summary($plan, $withTooth),
                'total' => Money::format($plan->total()),
                'status' => $plan->status,
                'since' => TreatmentPlansTable::since($plan),
                'payment' => TreatmentPlansTable::paymentSummary($plan),
                'open' => $plan->isOpen(),
            ])
            ->all());
    }

    /**
     * Sus trabajos de laboratorio, lo que sigue allá primero: qué es, de qué laboratorio, en qué quedó y lo que
     * hay que resolver. Cada uno lleva a Laboratorio, donde se marca que llegó o se paga.
     *
     * @return list<array{url: string, summary: string, total: null, status: LabOrderStatus, since: ?string, payment: null, open: bool}>
     */
    private static function labRows(Patient $patient): array
    {
        if (! auth()->user()->can('viewAny', LabOrder::class)) {
            return [];
        }

        return once(function () use ($patient) {
            /** @var Collection<int, LabOrder> $orders */
            $orders = LabOrderResource::getEloquentQuery()
                ->where('patient_id', $patient->getKey())
                ->orderByRaw('received_on is not null')
                ->orderByDesc('due_on')
                ->limit(10)
                ->get();

            return $orders->map(fn (LabOrder $order) => self::labRow($order, $patient))->values()->all();
        });
    }

    /** @return array{url: string, summary: string, total: null, status: LabOrderStatus, since: ?string, payment: null, open: bool} */
    private static function labRow(LabOrder $order, Patient $patient): array
    {
        /** @var CarbonInterface|null $received */
        $received = $order->received_on;
        /** @var CarbonInterface $due */
        $due = $order->due_on;
        $alert = $order->alert();

        return [
            'url' => LabOrderResource::getUrl('index', ['tab' => $alert ? 'attention' : ($received ? 'received' : 'pending'), 'search' => $patient->full_name]),
            'summary' => collect([$order->description, $order->laboratory])->filter()->implode(' · '),
            'total' => null,
            'status' => $order->status(),
            'since' => $alert ?? ($received ? 'Llegó el '.$received->format('d/m/Y') : 'Llega el '.$due->translatedFormat('D j M')),
            'payment' => null,
            'open' => $received === null,
        ];
    }
}
