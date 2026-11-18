import { useAction } from '@/api/actions';
import { apiUrl } from '@/api/client';
import type { Clinic, Welcome as WelcomeData } from '@/api/types';
import { AppointmentCard } from '@/components/site/AppointmentCard';
import { AppLink } from '@/components/ui/AppLink';
import { useLeave } from '@/lib/leave';
import { paths } from '@/lib/paths';

/**
 * Lo primero para quien vuelve desde su celular recordado: lo suyo, con el botón de lo que sigue.
 * Sin montos ni pagos: eso sigue en Mi cuenta, después de verificar por WhatsApp.
 */
export function Welcome({ welcome, clinic }: { welcome: WelcomeData; clinic: Clinic }) {
  const { run, busy } = useAction();
  const leave = useLeave();
  const nothing = !welcome.appointments.length && !welcome.sessions.length && !welcome.checkups.length;

  return (
    <section className="welcome" aria-labelledby="bienvenida">
      <div className="welcome-head">
        <h2 id="bienvenida">Hola{welcome.name ? `, ${welcome.name}` : ''}</h2>
        <AppLink href={paths.account} className="arrow">
          Mi cuenta
        </AppLink>
      </div>

      {nothing ? (
        <p className="welcome-empty">No tienes citas por delante. Cuando quieras, reserva aquí abajo.</p>
      ) : (
        <>
          <div className="welcome-cards">
            {welcome.appointments.map((appointment) => (
              <AppointmentCard
                key={appointment.id}
                appointment={appointment}
                clinic={clinic}
                confirmUrl={apiUrl(`account/appointments/${appointment.id}/confirm`)}
                changeHref={paths.accountChange(appointment.id)}
              />
            ))}

            {welcome.sessions.map((session) => (
              <article key={session.patient_id} className="welcome-card" data-welcome="sessions">
                <span className="welcome-kicker">{session.kicker}</span>
                <strong className="welcome-when">{session.title}</strong>
                <div className="meter" role="img" aria-label={`${session.done} de ${session.total} sesiones hechas`}>
                  <span style={{ width: `${Math.round((session.done / session.total) * 100)}%` }} />
                </div>
                <p className="muted">{session.note}</p>
                <div className="welcome-actions">
                  {session.requested ? (
                    <p className="welcome-done">Ya la pediste. Te escribiremos por WhatsApp con el horario.</p>
                  ) : (
                    <button
                      type="button"
                      className="btn small"
                      disabled={busy}
                      onClick={() => void run(apiUrl(`account/patients/${session.patient_id}/next-session`))}
                    >
                      {session.button}
                    </button>
                  )}
                </div>
              </article>
            ))}

            {welcome.checkups.map((checkup) => (
              <article key={checkup.patient_id} className="welcome-card" data-welcome="checkup">
                <span className="welcome-kicker">{checkup.kicker}</span>
                <strong className="welcome-when">{checkup.title}</strong>
                <p className="muted">{checkup.note}</p>
                <div className="welcome-actions">
                  <AppLink href={checkup.url} className="btn small">
                    {checkup.button}
                  </AppLink>
                </div>
              </article>
            ))}
          </div>
          {welcome.more > 0 ? (
            <p className="welcome-more">
              <AppLink href={paths.account} className="arrow">
                {welcome.more === 1 ? 'Ver 1 cita más' : `Ver ${welcome.more} citas más`}
              </AppLink>
            </p>
          ) : null}
        </>
      )}

      {welcome.name ? (
        <form
          className="welcome-not-you"
          onSubmit={(event) => {
            event.preventDefault();
            void run(apiUrl('account/logout'), {}, { onDone: (message) => leave(paths.account, message) });
          }}
        >
          ¿No eres {welcome.name}?{' '}
          <button type="submit" className="link-btn" disabled={busy}>
            Salir en este celular
          </button>
        </form>
      ) : null}
    </section>
  );
}
