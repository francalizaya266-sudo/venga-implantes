import { useAction } from '@/api/actions';
import { apiUrl } from '@/api/client';
import type { Welcome } from '@/api/types';
import { AppLink } from '@/components/ui/AppLink';

type Session = Welcome['sessions'][number];
type Checkup = Welcome['checkups'][number];

/**
 * "Pedir mi próxima sesión": quien sigue en tratamiento no reserva solo (cada sesión la programa su doctor), así que
 * avisa a recepción con un toque. Una vez pedida, lo dice en lugar del botón. El mismo botón en el inicio, en las
 * citas de Mi cuenta y dentro del plan.
 */
export function SessionRequest({ session }: { session: Session }) {
  const { run, busy } = useAction();

  return session.requested ? (
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
  );
}

/** A quién le faltan sesiones de su plan y no tiene cita: cuántas lleva y el botón para pedir la próxima. */
export function SessionCard({ session }: { session: Session }) {
  return (
    <article className="welcome-card" data-welcome="sessions">
      <span className="welcome-kicker">{session.kicker}</span>
      <strong className="welcome-when">{session.title}</strong>
      <div className="meter" role="img" aria-label={`${session.done} de ${session.total} sesiones hechas`}>
        <span style={{ width: `${Math.round((session.done / session.total) * 100)}%` }} />
      </div>
      <p className="muted">{session.note}</p>
      <div className="welcome-actions">
        <SessionRequest session={session} />
      </div>
    </article>
  );
}

/** A quién ya le toca su control, con el enlace para reservarlo a su nombre. */
export function CheckupCard({ checkup }: { checkup: Checkup }) {
  return (
    <article className="welcome-card" data-welcome="checkup">
      <span className="welcome-kicker">{checkup.kicker}</span>
      <strong className="welcome-when">{checkup.title}</strong>
      <p className="muted">{checkup.note}</p>
      <div className="welcome-actions">
        <AppLink href={checkup.url} className="btn small">
          {checkup.button}
        </AppLink>
      </div>
    </article>
  );
}
