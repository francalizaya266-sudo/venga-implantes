import { useState, type FormEvent } from 'react';
import { useAction } from '@/api/actions';
import type { AppointmentCard as Appointment, Clinic } from '@/api/types';
import { HealthAsk } from '@/components/site/HealthAsk';
import { AppLink } from '@/components/ui/AppLink';
import { paths } from '@/lib/paths';
import { whatsappUrl } from '@/lib/whatsapp';

interface Props {
  appointment: Appointment;
  clinic: Clinic;
  /** A dónde van los botones: con el enlace de la cita o con el celular recordado. */
  confirmUrl: string;
  changeHref: string;
  /** Sin dirección no se ofrece cancelar (el inicio, que solo lleva lo que sigue). */
  cancelUrl?: string;
  /** La cita es lo único de la página (el enlace de WhatsApp): botones a todo el ancho. */
  solo?: boolean;
}

/**
 * La tarjeta de una cita. Es la misma en el enlace de WhatsApp, en Mi cuenta y en el inicio: mismo orden y
 * mismas palabras ("Confirmar asistencia", "Cambiar horario", "Cancelar cita").
 */
export function AppointmentCard({ appointment, clinic, confirmUrl, changeHref, cancelUrl, solo = false }: Props) {
  const { run, busy } = useAction();
  const [reason, setReason] = useState('');
  const cancelByWhatsapp = whatsappUrl(clinic, appointment.cancel_message);

  function cancel(event: FormEvent) {
    event.preventDefault();

    if (cancelUrl) {
      void run(cancelUrl, { reason });
    }
  }

  return (
    <article className={`appointment${solo ? ' solo' : ''}`} data-appointment>
      <div className="spread">
        <span className="kicker">{appointment.kicker}</span>
        <span className={`badge${appointment.tone ? ` ${appointment.tone}` : ''}`}>{appointment.status}</span>
      </div>
      <strong className="when">{appointment.when}</strong>
      <p>{appointment.what}</p>

      {appointment.guest_notice ? (
        <p className="note guest">
          <strong>Reservaron esta cita con tu número.</strong> Si es de tu familia, confirma la asistencia. Si no la
          reconoces,{' '}
          {cancelUrl ? (
            'cancela la cita.'
          ) : (
            <>
              cancélala en <AppLink href={paths.account}>Mi cuenta</AppLink>.
            </>
          )}
        </p>
      ) : appointment.ask_health ? (
        <HealthAsk patientId={appointment.ask_health.patient_id} text={appointment.ask_health.text} />
      ) : null}

      {appointment.pending_request ? (
        <p className="note">
          {/* La última hora ya termina en punto ("3:00 p. m."): no se le pone otro. */}
          Pediste cambiar esta cita a: {appointment.pending_request}
          {appointment.pending_request.endsWith('.') ? '' : '.'} Te avisaremos por WhatsApp cuál quedó.
        </p>
      ) : null}

      {appointment.can_change ? (
        <>
          <div className="actions">
            {appointment.can_confirm ? (
              <form
                onSubmit={(event) => {
                  event.preventDefault();
                  void run(confirmUrl);
                }}
              >
                <button type="submit" className="btn small" disabled={busy}>
                  Confirmar asistencia
                </button>
              </form>
            ) : null}
            <AppLink href={changeHref} className="btn small quiet">
              Cambiar horario
            </AppLink>
          </div>

          {cancelUrl && appointment.can_cancel ? (
            <details className="more">
              <summary>Cancelar cita</summary>
              <form className="stack" onSubmit={cancel}>
                <div className="field">
                  <label htmlFor={`reason-${appointment.id}`}>Motivo (opcional)</label>
                  <textarea
                    id={`reason-${appointment.id}`}
                    name="reason"
                    className="input"
                    rows={2}
                    maxLength={250}
                    value={reason}
                    onChange={(event) => setReason(event.target.value)}
                  />
                </div>
                <button type="submit" className="btn small danger" disabled={busy}>
                  Sí, cancelar la cita
                </button>
              </form>
            </details>
          ) : cancelUrl ? (
            <p className="muted small">
              Faltan menos de {appointment.cancel_min_hours} horas para la cita, así que ya no se cancela desde aquí.{' '}
              {cancelByWhatsapp ? (
                <>
                  <a href={cancelByWhatsapp} rel="noopener">
                    Escríbenos por WhatsApp
                  </a>{' '}
                  y te ayudamos.
                </>
              ) : (
                'Escríbenos por WhatsApp y te ayudamos.'
              )}
            </p>
          ) : null}
        </>
      ) : null}
    </article>
  );
}
