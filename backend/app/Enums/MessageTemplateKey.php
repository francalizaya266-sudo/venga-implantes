<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Avisos de WhatsApp que prepara el sistema. Los textos se editan en el panel. */
enum MessageTemplateKey: string implements HasLabel
{
    case BookingConfirmation = 'booking_confirmation';
    case FirstWebBooking = 'first_web_booking';
    case AppointmentScheduled = 'appointment_scheduled';
    case Reminder = 'appointment_reminder';
    case Rescheduled = 'appointment_rescheduled';
    case Cancelled = 'appointment_cancelled';
    case RescheduleApproved = 'reschedule_approved';
    case RescheduleRejected = 'reschedule_rejected';
    case AccessLink = 'access_link';
    case Prescription = 'prescription';
    case TreatmentPlan = 'treatment_plan';
    case WaitlistOffer = 'waitlist_offer';
    case InstallmentReminder = 'installment_reminder';
    case InstallmentOverdue = 'installment_overdue';
    case PlanFollowUp = 'plan_follow_up';
    case PlanNextSession = 'plan_next_session';
    case CheckupRecall = 'checkup_recall';
    case Birthday = 'birthday';
    case PaymentReceipt = 'payment_receipt';
    case ReviewRequest = 'review_request';
    case CampaignAnnouncement = 'campaign_announcement';
    case Aftercare = 'aftercare';
    case MissedVisit = 'missed_visit';
    case DaySummary = 'day_summary';

    /** Avisos que le dicen al paciente cuándo es su cita y con quién. */
    public const TELLS_THE_DATE = [
        self::Reminder,
        self::BookingConfirmation,
        self::FirstWebBooking,
        self::AppointmentScheduled,
        self::Rescheduled,
        self::RescheduleApproved,
    ];

    /** Avisos de que la cita cambió de horario: el nuevo reemplaza a los que todavía decían el horario anterior. */
    public const CHANGE_NOTICES = [
        self::Rescheduled,
        self::RescheduleApproved,
    ];

    /**
     * Avisos que también pueden salir en la pantalla del celular del paciente (y solo ahí, si así lo eligió): los de
     * sus citas y sus cuotas. Los enlaces para entrar, las recetas, los planes y lo que pide una respuesta siguen por
     * WhatsApp.
     */
    public const PHONE_NOTICES = [
        self::BookingConfirmation,
        self::AppointmentScheduled,
        self::Reminder,
        self::Rescheduled,
        self::RescheduleApproved,
        self::RescheduleRejected,
        self::Cancelled,
        self::InstallmentReminder,
        self::InstallmentOverdue,
    ];

    /** Avisos que se arman con una cita: además de los datos de su texto, saben llenar los demás datos de la cita. */
    private const ABOUT_AN_APPOINTMENT = [
        self::BookingConfirmation,
        self::FirstWebBooking,
        self::AppointmentScheduled,
        self::Reminder,
        self::Rescheduled,
        self::Cancelled,
        self::RescheduleApproved,
        self::RescheduleRejected,
        self::Aftercare,
    ];

    /** Los datos de la cita que cualquiera de esos avisos puede llevar, aunque su texto original no los use. */
    private const APPOINTMENT_DATA = ['tratamiento', 'cuando', 'fecha', 'hora', 'doctor', 'donde', 'precio', 'indicaciones', 'consultorio', 'paciente'];

    /** @var array<string, string> Qué pone cada dato entre llaves. Donde un aviso lo usa para otra cosa, lo dice ownPlaceholderHelp(). */
    private const PLACEHOLDER_HELP = [
        'nombre' => 'el nombre de quien recibe el aviso',
        'paciente' => 'el nombre completo del paciente',
        'consultorio' => 'el nombre del consultorio',
        'donde' => '"Te esperamos en" y la dirección del consultorio. Si en Ajustes no hay dirección, no pone nada',
        'tratamiento' => 'el tratamiento de la cita',
        'fecha' => 'el día de la cita, como "lunes 16 de noviembre"',
        'hora' => 'la hora de la cita',
        'cuando' => 'el día contado desde hoy, como "de mañana lunes 16 de noviembre"',
        'doctor' => 'el nombre del doctor',
        'precio' => 'el precio del tratamiento y cómo se paga. Si el tratamiento no muestra su precio, no pone nada',
        'indicaciones' => 'lo que el tratamiento tiene escrito en "Antes de venir". Si está vacío, no pone nada',
        'cuidados' => 'lo que el tratamiento tiene escrito en "Cuidados después de la atención"',
        'enlace' => 'su enlace personal para confirmar, cambiar o cancelar la cita',
        'receta' => 'los medicamentos con sus indicaciones',
        'plan' => 'los procedimientos del plan con sus precios y las formas de pago',
        'total' => 'el total del presupuesto',
        'vigencia' => 'hasta cuándo vale el presupuesto. Si no tiene fecha, no pone nada',
        'hasta' => 'hasta cuándo vale',
        'cuota' => 'qué cuota es, como "cuota 2 de 6"',
        'monto' => 'el monto',
        'pendiente' => 'lo que falta hacer de su plan',
        'control' => 'el control que le toca, como "control y limpieza"',
        'medio' => 'cómo pagó: Yape, efectivo…',
        'numero' => 'el número de la constancia',
        'detalle' => 'lo que pagó, renglón por renglón',
        'saldo' => 'lo que le queda por pagar',
        'opinion' => 'el enlace para opinar en Google, el que está en Ajustes',
        'promocion' => 'el nombre de la promoción y lo que ofrece',
        'pagina' => 'el enlace a la página de la promoción',
        'que_paso' => '"No pudimos verte en tu cita de…" si faltó, o "Se canceló tu cita de…" si se canceló',
        'cobrado' => 'lo cobrado en el día, por medio de pago',
        'arqueo' => 'cómo salió el arqueo de caja',
        'cerro' => 'quién cerró la caja',
        'atendidos' => 'cuántos pacientes se atendieron',
        'faltas' => 'cuántos faltaron',
        'sin_cerrar' => 'cuántas citas quedaron sin cerrar',
        'descuentos' => 'los descuentos puestos antes de cobrar',
        'precios' => 'los precios cambiados y los descuentos al cobrar',
        'siguiente' => 'el siguiente día de atención',
        'sin_confirmar' => 'cuántas citas de ese día siguen sin confirmar',
    ];

    /** Su {enlace} abre Mi cuenta, aunque el aviso sea de una cita: así entra directo a su cuenta y la maneja desde ahí. */
    public function opensMyAccount(): bool
    {
        return in_array($this, [self::AccessLink, self::FirstWebBooking], true);
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::BookingConfirmation => 'Confirmación de reserva web',
            self::FirstWebBooking => 'Primera reserva web: su enlace de Mi cuenta',
            self::AppointmentScheduled => 'Cita programada por el consultorio',
            self::Reminder => 'Recordatorio de la cita',
            self::Rescheduled => 'Cambio de horario',
            self::Cancelled => 'Cita cancelada por el consultorio',
            self::RescheduleApproved => 'Solicitud de cambio aprobada',
            self::RescheduleRejected => 'Solicitud de cambio no aprobada',
            self::AccessLink => 'Enlace de acceso a Mi cuenta',
            self::Prescription => 'Receta e indicaciones',
            self::TreatmentPlan => 'Plan de tratamiento (presupuesto)',
            self::WaitlistOffer => 'Horario libre para lista de espera',
            self::InstallmentReminder => 'Aviso de cuota por vencer',
            self::InstallmentOverdue => 'Cuota atrasada',
            self::PlanFollowUp => 'Presupuesto sin respuesta',
            self::PlanNextSession => 'Plan sin próxima cita',
            self::CheckupRecall => 'Le toca control',
            self::Birthday => 'Saludo de cumpleaños',
            self::PaymentReceipt => 'Constancia de pago',
            self::ReviewRequest => 'Pedido de opinión en Google',
            self::CampaignAnnouncement => 'Promoción a quien acepta recibirlas',
            self::Aftercare => 'Cuidados después de la atención',
            self::MissedVisit => 'Faltó o canceló y no tiene otra cita',
            self::DaySummary => 'Resumen del día para quien administra',
        };
    }

    /**
     * Los datos entre llaves que este aviso sabe llenar, cada uno con lo que pone: los de su texto original y, si se
     * arma con una cita, también los demás datos de la cita. Es lo que se explica al editar su texto.
     *
     * @return array<string, string> "{dato}" => qué pone.
     */
    public function placeholders(): array
    {
        preg_match_all('/\{(\w+)\}/', $this->defaultBody(), $used);
        $names = in_array($this, self::ABOUT_AN_APPOINTMENT, true) ? [...$used[1], ...self::APPOINTMENT_DATA] : $used[1];
        $help = [...self::PLACEHOLDER_HELP, ...$this->ownPlaceholderHelp()];

        return collect($names)->unique()->mapWithKeys(fn (string $name) => ['{'.$name.'}' => $help[$name]])->all();
    }

    /**
     * Lo que en este aviso un dato pone distinto que en los demás: {fecha} es la de la cita, la de la cuota o la del pago.
     *
     * @return array<string, string>
     */
    private function ownPlaceholderHelp(): array
    {
        return match ($this) {
            self::AccessLink, self::FirstWebBooking => ['enlace' => 'su enlace personal para entrar a Mi cuenta'],
            self::WaitlistOffer => [
                'fecha' => 'el día del horario que se liberó',
                'hora' => 'la hora del horario que se liberó',
                'hasta' => 'hasta qué hora se le guarda ese horario',
            ],
            self::InstallmentReminder, self::InstallmentOverdue => [
                'plan' => 'el nombre de su plan de tratamiento',
                'fecha' => 'el día en que vence la cuota',
                'monto' => 'lo que falta pagar de esa cuota',
            ],
            self::PaymentReceipt => ['fecha' => 'el día del pago', 'monto' => 'cuánto pagó'],
            self::CampaignAnnouncement => ['hasta' => 'el último día de la promoción'],
            self::MissedVisit => ['tratamiento' => 'el tratamiento de la cita que perdió', 'fecha' => 'el día de esa cita'],
            self::DaySummary => ['fecha' => 'el día del resumen'],
            default => [],
        };
    }

    /** Texto inicial; el administrador puede cambiarlo desde el panel. */
    public function defaultBody(): string
    {
        return match ($this) {
            self::BookingConfirmation => "Hola {nombre} 👋, te escribimos de {consultorio}.\nTu cita de {tratamiento} quedó reservada para el {fecha} a las {hora} con {doctor}.\n{donde}\n{precio}\n\nConfirma tu asistencia, cambia el horario o cancela aquí:\n{enlace}",
            // Su primera reserva: el enlace no es el de la cita sino el de su cuenta, donde la confirma y ve todo lo demás.
            self::FirstWebBooking => "Hola {nombre} 👋, te escribimos de {consultorio}.\nTu cita de {tratamiento} quedó reservada para el {fecha} a las {hora} con {doctor}.\n{donde}\n{precio}\n\nYa tienes tu cuenta con nosotros. Entra a Mi cuenta para confirmar tu asistencia, cambiar el horario o cancelar:\n{enlace}\n\nNo compartas este enlace con nadie.",
            self::AppointmentScheduled => "Hola {nombre}, te escribimos de {consultorio}.\nTu próxima cita de {tratamiento} es el {fecha} a las {hora} con {doctor}.\n{donde}\n\nPuedes confirmarla aquí:\n{enlace}",
            self::Reminder => "Hola {nombre}, te recordamos tu cita de {tratamiento} {cuando} a las {hora} con {doctor} en {consultorio}.\n{donde}\n{indicaciones}\n\nConfirma tu asistencia aquí:\n{enlace}",
            self::Rescheduled => "Hola {nombre}, tu cita de {tratamiento} cambió al {fecha} a las {hora} con {doctor}.\n\nConfirma el nuevo horario aquí:\n{enlace}",
            self::Cancelled => 'Hola {nombre}, lamentamos avisarte que tu cita del {fecha} a las {hora} fue cancelada. Escríbenos y te ayudamos a elegir otro horario.',
            self::RescheduleApproved => "Hola {nombre}, aprobamos tu cambio. Tu cita de {tratamiento} ahora es el {fecha} a las {hora} con {doctor}.\n\nDetalles aquí:\n{enlace}",
            self::RescheduleRejected => 'Hola {nombre}, no pudimos mover tu cita a los horarios que propusiste. Tu cita sigue el {fecha} a las {hora}. Escríbenos y buscamos otra opción.',
            self::AccessLink => "Hola, este es tu enlace para entrar a Mi cuenta en {consultorio}:\n{enlace}\n\nNo lo compartas con nadie.",
            self::Prescription => "Hola {nombre}, te enviamos tu receta e indicaciones de {doctor}:\n\n{receta}\n\nSi tienes alguna duda o una reacción al medicamento, escríbenos.",
            self::TreatmentPlan => "Hola {nombre}, te enviamos tu plan de tratamiento con {doctor}:\n\n{plan}\n\nTotal: {total}{vigencia}\nEs un presupuesto referencial: si en el camino el tratamiento cambia, te lo explicamos antes. Cualquier duda, escríbenos.",
            self::WaitlistOffer => 'Hola {nombre}, se liberó un horario para {tratamiento} el {fecha} a las {hora} con {doctor}. Te lo guardamos hasta las {hasta}: ¿lo quieres? Respóndenos y te lo reservamos.',
            self::InstallmentReminder => "Hola {nombre}, te escribimos de {consultorio}.\nEl {fecha} vence tu {cuota} de {plan}: {monto}.\n\nPuedes pagarla en el consultorio con Yape, Plin, tarjeta o efectivo. Si ya la pagaste, no te preocupes.",
            self::InstallmentOverdue => "Hola {nombre}, te escribimos de {consultorio}.\nTu {cuota} de {plan} venció el {fecha} y queda {monto} por pagar.\n\nPuedes pagarla en el consultorio con Yape, Plin, tarjeta o efectivo. Si ya la pagaste, avísanos y lo revisamos.",
            self::PlanFollowUp => "Hola {nombre}, te escribimos de {consultorio}.\n¿Pudiste revisar tu plan de tratamiento con {doctor}? Si tienes dudas sobre el tratamiento o las formas de pago, escríbenos y te ayudamos.\n\nCuando quieras, te reservamos la primera cita.",
            self::PlanNextSession => "Hola {nombre}, te escribimos de {consultorio}.\nEn tu tratamiento queda pendiente: {pendiente}.\n\n¿Te reservamos tu próxima cita? Respóndenos con el día y la hora que te acomodan.",
            self::CheckupRecall => "Hola {nombre}, te escribimos de {consultorio}.\nYa es momento de tu {control}. ¿Te reservamos una cita? Respóndenos con el día y la hora que te acomodan.",
            self::Birthday => '¡Feliz cumpleaños, {nombre}! 🎂 Todo el equipo de {consultorio} te desea un gran día.',
            self::PaymentReceipt => "Hola {nombre}, te escribimos de {consultorio}.\nRecibimos tu pago de {monto} ({medio}) el {fecha}. Constancia {numero}:\n{detalle}\n\nSaldo: {saldo}. ¡Gracias!",
            self::ReviewRequest => "Hola {nombre}, gracias por confiar en {consultorio}. Si te gustó cómo te atendimos, ¿nos dejas tu opinión en Google? Toma un minuto y ayuda a otras personas a encontrarnos:\n{opinion}",
            self::CampaignAnnouncement => "Hola {nombre}, te escribimos de {consultorio}.\n{promocion}\nVale hasta el {hasta}.\n\nMira los detalles y reserva aquí:\n{pagina}\n\nSi prefieres no recibir promociones, respóndenos «no» y no te escribimos más.",
            // {que_paso}: "No pudimos verte en tu cita de…" si no vino, o "Se canceló tu cita de…" si se canceló.
            self::MissedVisit => "Hola {nombre}, te escribimos de {consultorio}.\n{que_paso} ¿Te reservamos otro horario? Respóndenos con el día y la hora que te acomodan.",
            self::Aftercare => "Hola {nombre}, gracias por venir hoy a {consultorio}. Estos son los cuidados después de tu {tratamiento}:\n\n{cuidados}\n\nSi tienes dolor fuerte, sangrado que no para o fiebre, escríbenos.",
            // Al cerrar la caja, a quien administra (DaySummary): lo cobrado por medio, el arqueo y quién cerró, cómo fue el día, lo que se cambió al cobrar y lo que queda.
            self::DaySummary => "Resumen del {fecha} en {consultorio}\nCobrado: {cobrado}\nArqueo: {arqueo} · cerró {cerro}\nPacientes atendidos: {atendidos} · Faltas: {faltas} · Citas sin cerrar: {sin_cerrar}\nDescuentos puestos antes de cobrar: {descuentos}\nPrecios cambiados y descuentos al cobrar: {precios}\nCitas del {siguiente} sin confirmar: {sin_confirmar}",
        };
    }
}
