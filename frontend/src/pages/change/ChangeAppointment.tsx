import { useEffect, useRef, useState } from 'react';
import { useAction } from '@/api/actions';
import { apiUrl } from '@/api/client';
import { useDaySlots } from '@/api/slots';
import type { ChangeData, Slot } from '@/api/types';
import { SlotPicker } from '@/components/site/SlotPicker';
import { AppLink } from '@/components/ui/AppLink';
import { useLeave } from '@/lib/leave';

interface Props {
  change: ChangeData;
  /** El recurso de la API de este cambio: el de Mi cuenta o el del enlace de WhatsApp. */
  resource: string;
  /** A dónde se vuelve: Mi cuenta o la cita del enlace. */
  back: string;
}

interface Chosen {
  /** "Y-m-d H:i". */
  key: string;
  /** "Lunes 3 de noviembre, 9:00 a. m." */
  label: string;
}

/**
 * Cambiar el horario de una cita desde el enlace o desde Mi cuenta. Si el cambio es al instante, el paciente
 * elige otro horario y se cambia solo. Si lo aprueba el consultorio, propone unos cuantos y el consultorio
 * elige uno.
 */
export function ChangeAppointment({ change, resource, back }: Props) {
  const { run, busy } = useAction();
  const leave = useLeave();
  const [pickedDate, setPickedDate] = useState<string | null>(null);
  const [chosen, setChosen] = useState<Chosen[]>([]);
  const [reason, setReason] = useState('');
  const [problem, setProblem] = useState<string | null>(null);
  const alert = useRef<HTMLDivElement>(null);

  const { automatic, max_options: max } = change;
  // El día que se mira: el que tocó, mientras siga teniendo horarios, o el primero.
  const wanted = change.days.some((day) => day.date === pickedDate) ? pickedDate : change.date;
  const day = useDaySlots(change, wanted, (date) => apiUrl(`${resource}/slots`, { date }));
  const dayLabel = change.days.find((option) => option.date === day.date)?.label ?? '';
  const full = !automatic && chosen.length >= max;
  // Cuántos horarios lleva de los que puede proponer: se dice junto a los horarios, que es donde está mirando.
  const note =
    automatic || !chosen.length
      ? null
      : full
        ? `Ya elegiste tus ${max} horarios. Quita uno para elegir otro.`
        : `Elegiste ${chosen.length} de ${max} horarios.`;

  // El aviso de que el horario ya no está puede quedar arriba, fuera de la pantalla.
  useEffect(() => {
    if (problem) {
      alert.current?.scrollIntoView({ block: 'nearest' });
    }
  }, [problem]);

  /** Al instante: un horario. Con aprobación: varios, que se marcan y desmarcan. */
  function pick(slot: Slot) {
    const option = { key: `${day.date} ${slot.time}`, label: `${dayLabel}, ${slot.label}` };

    setProblem(null);
    setChosen((current) => {
      if (automatic) {
        return [option];
      }

      if (current.some((other) => other.key === option.key)) {
        return current.filter((other) => other.key !== option.key);
      }

      return current.length < max ? [...current, option].sort((a, b) => a.key.localeCompare(b.key)) : current;
    });
  }

  function save() {
    void run(
      apiUrl(resource),
      { chosen: chosen.map((option) => option.key), reason: automatic ? null : reason },
      {
        onDone: (message) => leave(back, message, { replace: true }),
        // El horario ya no está: se empieza de nuevo con los que quedan.
        onRejected: (text) => {
          setChosen([]);
          setProblem(text);
        },
      },
    );
  }

  return (
    <div className="stack-lg">
      <AppLink href={back} className="link-btn" style={{ textDecoration: 'none' }}>
        ← Volver a mi cita
      </AppLink>

      <section className="stack" aria-labelledby="cambiar">
        <h1 id="cambiar">Cambiar el horario</h1>
        <div className="summary">
          <p className="what">{change.appointment.what}</p>
          <p style={{ margin: '4px 0 0' }}>Ahora: {change.appointment.now}</p>
          <p className="muted" style={{ margin: '2px 0 0' }}>
            con {change.appointment.doctor}
          </p>
        </div>

        {change.can_change ? (
          <>
            {automatic ? (
              <p className="lead">Elige el nuevo día y hora. El cambio se hace al instante.</p>
            ) : (
              <>
                <p className="lead">
                  {change.fixed_date ? 'Esta sesión tiene fecha coordinada con tu doctor' : 'Falta poco para tu cita'},
                  así que el consultorio confirma el cambio. Elige hasta {max} horarios que te sirvan y te avisaremos
                  cuál quedó.
                </p>
                {change.pending_request ? (
                  <div className="flash info">
                    Ya pediste cambiarla a: {change.pending_request} Si envías otro pedido, reemplaza al anterior.
                  </div>
                ) : null}
              </>
            )}

            {day.loading || busy ? <div className="loading-line" aria-hidden="true" /> : null}

            {problem ? (
              <div ref={alert} className="flash bad" role="alert">
                {problem}
              </div>
            ) : null}

            <SlotPicker
              days={change.days}
              date={day.date}
              slots={day.slots}
              selected={chosen.map((option) => option.key)}
              onDate={setPickedDate}
              onTime={pick}
              note={note}
              full={full}
              doctor={change.appointment.doctor}
            />

            {chosen.length ? (
              <div className="panel stack">
                <h2>{automatic ? 'Nuevo horario' : 'Tus opciones'}</h2>
                <ul className="choice-list">
                  {chosen.map((option) => (
                    <li key={option.key} className="spread">
                      <strong>{option.label}</strong>
                      {automatic ? null : (
                        <button
                          type="button"
                          className="link-btn"
                          onClick={() => setChosen((current) => current.filter((other) => other.key !== option.key))}
                        >
                          Quitar
                        </button>
                      )}
                    </li>
                  ))}
                </ul>

                {automatic ? null : (
                  <div className="field">
                    <label htmlFor="reason">Motivo (opcional)</label>
                    <textarea
                      id="reason"
                      className="input"
                      rows={2}
                      maxLength={250}
                      value={reason}
                      onChange={(event) => setReason(event.target.value)}
                    />
                  </div>
                )}

                <button type="button" className="btn block" disabled={busy} onClick={save}>
                  {automatic
                    ? 'Cambiar a este horario'
                    : chosen.length === 1
                      ? 'Enviar mi pedido'
                      : `Enviar mis ${chosen.length} opciones`}
                </button>
              </div>
            ) : null}
          </>
        ) : (
          <div className="flash info">
            Esta cita ya no se puede cambiar desde aquí. Escríbenos por WhatsApp y te ayudamos.
          </div>
        )}
      </section>
    </div>
  );
}
