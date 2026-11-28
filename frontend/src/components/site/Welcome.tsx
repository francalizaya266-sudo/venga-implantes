import { useAction } from '@/api/actions';
import { apiUrl } from '@/api/client';
import type { Clinic, Welcome as WelcomeData } from '@/api/types';
import { AppointmentCard } from '@/components/site/AppointmentCard';
import { CheckupCard, SessionCard } from '@/components/site/FollowUpCards';
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
              <SessionCard key={session.patient_id} session={session} />
            ))}

            {welcome.checkups.map((checkup) => (
              <CheckupCard key={checkup.patient_id} checkup={checkup} />
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
