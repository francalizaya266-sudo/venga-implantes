import { useEffect, useRef, useState, type FormEvent } from 'react';
import { useAction } from '@/api/actions';
import { apiUrl } from '@/api/client';
import type { BookingDevice, BookingTreatment } from '@/api/types';
import { paths } from '@/lib/paths';

/** A qué hora puede venir, con las palabras del panel (App\Enums\DayPeriod). */
const PERIODS = [
  { value: 'morning', label: 'En la mañana' },
  { value: 'afternoon', label: 'En la tarde' },
  { value: 'any', label: 'A cualquier hora' },
] as const;

/** Lo que se escribe para quedar en la lista de espera, con los nombres que espera la API. */
interface WaitlistDetails {
  patient_id: number | null;
  phone: string;
  first_name: string;
  last_name: string;
  preferred_period: (typeof PERIODS)[number]['value'];
  days: string;
  accepts_privacy: boolean;
}

interface Props {
  treatment: BookingTreatment;
  /** El doctor que eligió al ver los horarios; null: el primero disponible. */
  doctor: { id: number; name: string } | null;
  device: BookingDevice | null;
  /** El servicio no tiene ningún horario libre en las próximas semanas. */
  noTimes: boolean;
}

/**
 * "Avísame si se libera un horario", al pie del paso de horarios: quien no encuentra uno que le sirva deja su dato y
 * queda en la lista de espera del consultorio, en vez de irse sin nada. Recepción le escribe cuando se libere uno.
 */
export function WaitlistAsk({ treatment, doctor, device, noTimes }: Props) {
  const { run, busy, errors, fix } = useAction();
  const [open, setOpen] = useState(false);
  const [done, setDone] = useState<string | null>(null);
  const [problem, setProblem] = useState<string | null>(null);
  const [details, setDetails] = useState<WaitlistDetails>(() => ({
    patient_id: device?.patient_id ?? null,
    phone: '',
    first_name: '',
    last_name: '',
    preferred_period: 'any',
    days: '',
    accepts_privacy: false,
  }));
  const [rejected, setRejected] = useState(0);
  const form = useRef<HTMLFormElement>(null);

  // Al abrirlo, el primer dato por llenar queda listo para escribir.
  useEffect(() => {
    if (open) {
      form.current?.querySelector<HTMLElement>('input, button')?.focus();
    }
  }, [open]);

  // Si algo no se aceptó, se lleva al paciente al primer campo marcado (el lector de pantalla le dice qué revisar).
  useEffect(() => {
    if (rejected) {
      form.current?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus();
    }
  }, [rejected]);

  const newPerson = details.patient_id === null;

  function change<K extends keyof WaitlistDetails>(field: K, value: WaitlistDetails[K]) {
    setDetails((before) => ({ ...before, [field]: value }));
    fix(field);
  }

  function submit(event: FormEvent) {
    event.preventDefault();
    setProblem(null);

    void run(
      apiUrl('booking/waitlist'),
      {
        treatment_id: treatment.id,
        doctor_id: doctor?.id ?? null,
        patient_id: details.patient_id,
        phone: device ? null : details.phone,
        first_name: newPerson ? details.first_name : null,
        last_name: newPerson ? details.last_name : null,
        preferred_period: details.preferred_period,
        days: details.days,
        accepts_privacy: details.accepts_privacy,
      },
      { onDone: (message) => setDone(message), onRejected: setProblem },
    ).then((accepted) => {
      if (!accepted) {
        setRejected((times) => times + 1);
      }
    });
  }

  /** El mensaje de un campo que no se aceptó, para leerlo junto con él. */
  const describedBy = (field: keyof WaitlistDetails) => (errors[field] ? `avisame-${field}-error` : undefined);

  return (
    <section className="panel stack waitlist-ask" aria-labelledby="avisame">
      <h3 id="avisame">{noTimes ? '¿Te avisamos cuando haya uno?' : '¿Ningún horario te sirve?'}</h3>

      {done ? (
        <div className="flash ok" role="status">
          <p>{done}</p>
        </div>
      ) : !open ? (
        <>
          <p>
            Te anotamos en nuestra lista de espera y te escribimos por WhatsApp si se libera un horario
            {doctor ? ` con ${doctor.name}` : ''}.
          </p>
          <button type="button" className="btn quiet" onClick={() => setOpen(true)}>
            Avísame si se libera un horario
          </button>
        </>
      ) : (
        <form ref={form} className="stack" noValidate onSubmit={submit}>
          {device ? (
            <div className="field">
              <span className="label" id="avisame-quien">
                ¿Para quién es?
              </span>
              <div className="chips wrap" role="group" aria-labelledby="avisame-quien">
                {device.family.map((person) => (
                  <button
                    key={person.id}
                    type="button"
                    className="chip"
                    aria-pressed={details.patient_id === person.id}
                    onClick={() => change('patient_id', person.id)}
                  >
                    {person.name}
                  </button>
                ))}
                <button
                  type="button"
                  className="chip"
                  aria-pressed={newPerson}
                  onClick={() => change('patient_id', null)}
                >
                  Otra persona
                </button>
              </div>
              <span className="hint">Te escribimos al {device.phone}.</span>
            </div>
          ) : (
            <div className="field">
              <label htmlFor="avisame-celular">Tu celular</label>
              <div className="prefix">
                <span>+51</span>
                <input
                  id="avisame-celular"
                  type="tel"
                  inputMode="tel"
                  autoComplete="tel-national"
                  className="input"
                  placeholder="987 654 321"
                  value={details.phone}
                  onChange={(event) => change('phone', event.target.value)}
                  aria-invalid={errors.phone ? true : undefined}
                  aria-describedby={describedBy('phone')}
                />
              </div>
              {errors.phone ? (
                <span className="error" id="avisame-phone-error">
                  {errors.phone}
                </span>
              ) : null}
            </div>
          )}

          {newPerson ? (
            <div className="grid-2">
              <div className="field">
                <label htmlFor="avisame-nombres">Nombres</label>
                <input
                  id="avisame-nombres"
                  className="input"
                  autoComplete="given-name"
                  maxLength={80}
                  value={details.first_name}
                  onChange={(event) => change('first_name', event.target.value)}
                  aria-invalid={errors.first_name ? true : undefined}
                  aria-describedby={describedBy('first_name')}
                />
                {errors.first_name ? (
                  <span className="error" id="avisame-first_name-error">
                    {errors.first_name}
                  </span>
                ) : null}
              </div>
              <div className="field">
                <label htmlFor="avisame-apellidos">Apellidos</label>
                <input
                  id="avisame-apellidos"
                  className="input"
                  autoComplete="family-name"
                  maxLength={80}
                  value={details.last_name}
                  onChange={(event) => change('last_name', event.target.value)}
                  aria-invalid={errors.last_name ? true : undefined}
                  aria-describedby={describedBy('last_name')}
                />
                {errors.last_name ? (
                  <span className="error" id="avisame-last_name-error">
                    {errors.last_name}
                  </span>
                ) : null}
              </div>
            </div>
          ) : null}

          <div className="field">
            <span className="label" id="avisame-hora">
              ¿A qué hora puedes venir?
            </span>
            <div className="chips wrap" role="group" aria-labelledby="avisame-hora">
              {PERIODS.map((period) => (
                <button
                  key={period.value}
                  type="button"
                  className="chip"
                  aria-pressed={details.preferred_period === period.value}
                  onClick={() => change('preferred_period', period.value)}
                >
                  {period.label}
                </button>
              ))}
            </div>
          </div>

          <div className="field">
            <label htmlFor="avisame-dias">¿Qué días te sirven? (opcional)</label>
            <input
              id="avisame-dias"
              className="input"
              maxLength={120}
              placeholder="Por ejemplo: lunes o sábados"
              value={details.days}
              onChange={(event) => change('days', event.target.value)}
              aria-invalid={errors.days ? true : undefined}
              aria-describedby={describedBy('days')}
            />
            {errors.days ? (
              <span className="error" id="avisame-days-error">
                {errors.days}
              </span>
            ) : null}
          </div>

          {device?.privacy_accepted ? null : (
            <div className="field">
              <label className="check">
                <input
                  type="checkbox"
                  checked={details.accepts_privacy}
                  onChange={(event) => change('accepts_privacy', event.target.checked)}
                  aria-invalid={errors.accepts_privacy ? true : undefined}
                  aria-describedby={describedBy('accepts_privacy')}
                />
                <span>
                  Acepto la{' '}
                  <a href={paths.privacy} target="_blank">
                    política de privacidad
                  </a>
                  : mis datos se usan solo para atenderme.
                </span>
              </label>
              {errors.accepts_privacy ? (
                <span className="error" id="avisame-accepts_privacy-error">
                  {errors.accepts_privacy}
                </span>
              ) : null}
            </div>
          )}

          {problem ? (
            <p className="form-problems" role="alert">
              {problem}
            </p>
          ) : null}

          <button type="submit" className="btn" disabled={busy}>
            {busy ? 'Anotando…' : 'Avísame'}
          </button>
        </form>
      )}
    </section>
  );
}
