import type { PastAppointment, VisitNotes } from '@/api/types';
import { listOf } from '@/lib/text';

interface Props {
  past: PastAppointment[];
  /** Con varias personas en la cuenta, cada cita dice de quién fue. */
  several: boolean;
}

/** Las citas que se muestran sin abrir "ver todas". */
const RECENT = 4;

/**
 * Las citas que ya pasaron, la más reciente arriba. La que se atendió guarda lo que el paciente se llevó a casa: lo
 * que se le hizo, las indicaciones, la receta y los cuidados, para volver a leerlos sin buscar el WhatsApp.
 */
export function AccountHistory({ past, several }: Props) {
  if (!past.length) {
    return <p className="muted">Todavía no hay citas anteriores.</p>;
  }

  const older = past.slice(RECENT);

  return (
    <>
      <ul className="past">
        {past.slice(0, RECENT).map((appointment) => (
          <Past key={appointment.id} appointment={appointment} several={several} />
        ))}
      </ul>
      {older.length ? (
        <details className="more">
          <summary>{older.length === 1 ? 'Ver 1 cita más' : `Ver ${older.length} citas más`}</summary>
          <ul className="past">
            {older.map((appointment) => (
              <Past key={appointment.id} appointment={appointment} several={several} />
            ))}
          </ul>
        </details>
      ) : null}
    </>
  );
}

function Past({ appointment, several }: { appointment: PastAppointment; several: boolean }) {
  const { visit } = appointment;

  return (
    <li>
      <div className="spread">
        <span className="what">
          <strong>{appointment.treatment}</strong>
          <span className="muted small">
            {appointment.date} · {several ? `${appointment.name} · ` : ''}
            {appointment.doctor}
          </span>
        </span>
        <span className={`badge ${appointment.tone}`}>{appointment.status}</span>
      </div>

      {visit ? (
        <details className="more">
          <summary>Ver {listOf(partsOf(visit))}</summary>
          <div className="visit">
            {visit.done.length ? (
              <div>
                <h4>Lo que se hizo</h4>
                <ul>
                  {visit.done.map((work, index) => (
                    <li key={`${index}-${work}`}>{work}</li>
                  ))}
                </ul>
              </div>
            ) : null}
            {visit.indications ? (
              <div>
                <h4>Indicaciones del doctor</h4>
                <p className="lines">{visit.indications}</p>
              </div>
            ) : null}
            {visit.medicines.length ? (
              <div>
                <h4>Receta</h4>
                <ul>
                  {visit.medicines.map((medicine, index) => (
                    <li key={`${index}-${medicine.name}`}>
                      <strong>{medicine.name}</strong>
                      {medicine.how ? `: ${medicine.how}` : ''}
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}
            {visit.aftercare ? (
              <div>
                <h4>Cuidados</h4>
                <p className="lines">{visit.aftercare}</p>
              </div>
            ) : null}
          </div>
        </details>
      ) : null}
    </li>
  );
}

/** Qué guarda la cita, para decirlo en el botón: "lo que se hizo, las indicaciones y la receta". */
function partsOf(visit: VisitNotes): string[] {
  return [
    visit.done.length ? 'lo que se hizo' : null,
    visit.indications ? 'las indicaciones' : null,
    visit.medicines.length ? 'la receta' : null,
    visit.aftercare ? 'los cuidados' : null,
  ].filter((part): part is string => part !== null);
}
