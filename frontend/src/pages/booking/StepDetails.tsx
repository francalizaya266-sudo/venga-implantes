import { useState, type FormEvent } from 'react';
import type { BookingOptions, BookingTreatment, Clinic, Slot } from '@/api/types';
import { AppLink } from '@/components/ui/AppLink';
import { paths } from '@/lib/paths';
import { withArticle } from '@/lib/text';
import { InTreatment } from '@/pages/booking/InTreatment';

const MONTHS = [
  'Enero',
  'Febrero',
  'Marzo',
  'Abril',
  'Mayo',
  'Junio',
  'Julio',
  'Agosto',
  'Septiembre',
  'Octubre',
  'Noviembre',
  'Diciembre',
];

/** Lo que falta corregir, en palabras, para decirlo junto al botón de reservar. */
const FIELD_NAMES: [field: string, name: string][] = [
  ['phone', 'tu celular'],
  ['first_name', 'los nombres'],
  ['last_name', 'los apellidos'],
  ['birth_date', 'la fecha de nacimiento'],
  ['promo_code', 'el código de promoción'],
  ['accepts_privacy', 'la política de privacidad'],
];

/** Lo que el paciente escribe en el último paso, con los nombres que espera la API. */
export interface BookingDetails {
  patient_id: number | null;
  phone: string;
  who: 'me' | 'family';
  relationship: string;
  first_name: string;
  last_name: string;
  birth_day: string;
  birth_month: string;
  birth_year: string;
  note: string;
  promo_code: string;
  accepts_privacy: boolean;
  accepts_promotions: boolean;
}

interface Props {
  options: BookingOptions;
  clinic: Clinic;
  treatment: BookingTreatment;
  day: string | null;
  slot: Slot;
  doctor: string | null;
  howToPay: string;
  /** Los datos que la API no aceptó, por campo. */
  errors: Record<string, string>;
  busy: boolean;
  onBack: () => void;
  /** El código de promoción cambió: se pregunta si existe y con qué promoción va. */
  onPromoCode: (code: string) => void;
  onFix: (field: string) => void;
  onSubmit: (details: BookingDetails) => void;
}

function join(names: string[]): string {
  return names.length > 1 ? `${names.slice(0, -1).join(', ')} y ${names[names.length - 1]}` : (names[0] ?? '');
}

/**
 * Paso 3: sus datos. Desde un celular recordado no se pide el número y se elige al familiar; a quien ya está
 * registrado no se le vuelven a pedir sus datos.
 */
export function StepDetails({
  options,
  clinic,
  treatment,
  day,
  slot,
  doctor,
  howToPay,
  errors,
  busy,
  onBack,
  onPromoCode,
  onFix,
  onSubmit,
}: Props) {
  const { device, campaign } = options;
  const [details, setDetails] = useState<BookingDetails>(() => ({
    patient_id: device?.patient_id ?? null,
    phone: '',
    who: 'me',
    relationship: 'self',
    first_name: '',
    last_name: '',
    birth_day: '',
    birth_month: '',
    birth_year: '',
    note: '',
    promo_code: options.promo_code,
    accepts_privacy: false,
    accepts_promotions: false,
  }));
  // Con un código o un comentario ya puestos, el bloque sale abierto.
  const [extrasOpen] = useState(() => options.promo_code !== '');

  const self = details.relationship === 'self';
  const newPerson = details.patient_id === null;
  const current = device?.family.find((person) => person.id === details.patient_id && person.in_treatment);
  const promoError = errors.promo_code ?? null;
  // Un código que no es de ninguna promoción vigente no impide reservar: se avisa por si quiere corregirlo.
  const promoHint = !promoError && details.promo_code.trim() !== '' ? options.promo_problem : null;
  const toReview = FIELD_NAMES.filter(([field]) => errors[field]).map(([, name]) => name);

  /** Lo que ya corrigió deja de marcarse en rojo y de nombrarse junto al botón; al reservar se revisa de nuevo. */
  function change<K extends keyof BookingDetails>(field: K, value: BookingDetails[K]) {
    setDetails((before) => ({ ...before, [field]: value }));
    onFix(field.startsWith('birth_') ? 'birth_date' : field);
  }

  function chooseWho(who: 'me' | 'family') {
    setDetails((before) => ({ ...before, who, relationship: who === 'family' ? 'child' : 'self' }));
  }

  /** "Otra persona": con titular en el celular, es un familiar. */
  function forSomeoneElse() {
    chooseWho(device?.has_holder ? 'family' : 'me');
    setDetails((before) => ({ ...before, patient_id: null }));
  }

  function submit(event: FormEvent) {
    event.preventDefault();
    onSubmit(details);
  }

  return (
    <section className="stack" aria-labelledby="paso-3">
      <button type="button" className="link-btn" onClick={onBack}>
        ← Cambiar horario
      </button>
      <h2 id="paso-3" className="step-title" tabIndex={-1}>
        Tus datos
      </h2>

      <div className="summary">
        <p className="what">{treatment.name}</p>
        <p style={{ margin: '4px 0 0' }}>
          {day}, {slot.label}
        </p>
        {doctor ? (
          <p className="muted" style={{ margin: '2px 0 0' }}>
            con {withArticle(doctor)}
          </p>
        ) : null}
        {treatment.price_line ? <p className="price-line">{treatment.price_line}</p> : null}
      </div>

      <form className="stack" noValidate onSubmit={submit}>
        {device ? (
          <>
            <div className="field">
              <span className="label" id="para-quien">
                ¿Para quién es la cita?
              </span>
              <div className="chips wrap" role="group" aria-labelledby="para-quien">
                {device.family.map((person) => (
                  <button
                    key={person.id}
                    type="button"
                    className="chip"
                    aria-pressed={details.patient_id === person.id}
                    onClick={() => setDetails((before) => ({ ...before, patient_id: person.id }))}
                  >
                    {person.name}
                    {person.in_treatment ? ' · en tratamiento' : ''}
                  </button>
                ))}
                <button type="button" className="chip" aria-pressed={newPerson} onClick={forSomeoneElse}>
                  Otra persona
                </button>
              </div>
              <span className="hint">
                Celular {device.phone}. ¿No eres tú? <AppLink href={paths.account}>Cambiar</AppLink>
              </span>
            </div>
            {current ? <InTreatment person={current} clinic={clinic} others={false} /> : null}
          </>
        ) : (
          <div className="field">
            <label htmlFor="phone">Tu celular</label>
            <div className="prefix">
              <span>+51</span>
              <input
                id="phone"
                type="tel"
                inputMode="tel"
                autoComplete="tel-national"
                className="input"
                placeholder="987 654 321"
                value={details.phone}
                onChange={(event) => change('phone', event.target.value)}
                aria-invalid={errors.phone ? true : undefined}
                aria-describedby={errors.phone ? 'phone-error' : undefined}
              />
            </div>
            <span className="hint">Por aquí te escribimos por WhatsApp para confirmar.</span>
            {errors.phone ? (
              <span className="error" id="phone-error">
                {errors.phone}
              </span>
            ) : null}
          </div>
        )}

        {newPerson ? (
          <>
            {device?.has_holder ? null : (
              <div className="field">
                <span className="label" id="es-para-ti">
                  ¿La cita es para ti?
                </span>
                <div className="segmented" role="radiogroup" aria-labelledby="es-para-ti">
                  <label>
                    <input type="radio" name="who" checked={details.who === 'me'} onChange={() => chooseWho('me')} />
                    <span>Sí, para mí</span>
                  </label>
                  <label>
                    <input
                      type="radio"
                      name="who"
                      checked={details.who === 'family'}
                      onChange={() => chooseWho('family')}
                    />
                    <span>Para un familiar</span>
                  </label>
                </div>
              </div>
            )}

            {self ? null : (
              <div className="field">
                <label htmlFor="relationship">El paciente es tu…</label>
                <select
                  id="relationship"
                  className="input"
                  value={details.relationship}
                  onChange={(event) => change('relationship', event.target.value)}
                >
                  {options.relationships.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </div>
            )}

            <div className="grid-2">
              <div className="field">
                <label htmlFor="firstName">{self ? 'Tus nombres' : 'Nombres del paciente'}</label>
                <input
                  id="firstName"
                  className="input"
                  autoComplete={self ? 'given-name' : 'off'}
                  value={details.first_name}
                  onChange={(event) => change('first_name', event.target.value)}
                  aria-invalid={errors.first_name ? true : undefined}
                />
                {errors.first_name ? <span className="error">{errors.first_name}</span> : null}
              </div>
              <div className="field">
                <label htmlFor="lastName">{self ? 'Tus apellidos' : 'Apellidos del paciente'}</label>
                <input
                  id="lastName"
                  className="input"
                  autoComplete={self ? 'family-name' : 'off'}
                  value={details.last_name}
                  onChange={(event) => change('last_name', event.target.value)}
                  aria-invalid={errors.last_name ? true : undefined}
                />
                {errors.last_name ? <span className="error">{errors.last_name}</span> : null}
              </div>
            </div>

            {/* En tres campos: el calendario del celular abre en el mes actual y llegar a 1985 toma decenas de toques. */}
            <fieldset className="field">
              <legend className="label">Fecha de nacimiento {self ? '(opcional)' : ''}</legend>
              <div className="date-parts">
                <label>
                  <span className="sr-only">Día</span>
                  <input
                    id="birthDay"
                    className="input"
                    inputMode="numeric"
                    pattern="[0-9]*"
                    maxLength={2}
                    placeholder="Día"
                    autoComplete={self ? 'bday-day' : 'off'}
                    value={details.birth_day}
                    onChange={(event) => change('birth_day', event.target.value)}
                    aria-invalid={errors.birth_date ? true : undefined}
                  />
                </label>
                <label>
                  <span className="sr-only">Mes</span>
                  <select
                    id="birthMonth"
                    className="input"
                    autoComplete={self ? 'bday-month' : 'off'}
                    value={details.birth_month}
                    onChange={(event) => change('birth_month', event.target.value)}
                    aria-invalid={errors.birth_date ? true : undefined}
                  >
                    <option value="">Mes</option>
                    {MONTHS.map((month, index) => (
                      <option key={month} value={index + 1}>
                        {month}
                      </option>
                    ))}
                  </select>
                </label>
                <label>
                  <span className="sr-only">Año</span>
                  <input
                    id="birthYear"
                    className="input"
                    inputMode="numeric"
                    pattern="[0-9]*"
                    maxLength={4}
                    placeholder="Año"
                    autoComplete={self ? 'bday-year' : 'off'}
                    value={details.birth_year}
                    onChange={(event) => change('birth_year', event.target.value)}
                    aria-invalid={errors.birth_date ? true : undefined}
                  />
                </label>
              </div>
              {self ? null : <span className="hint">Nos ayuda a preparar la atención, sobre todo si es un niño.</span>}
              {errors.birth_date ? <span className="error">{errors.birth_date}</span> : null}
            </fieldset>
          </>
        ) : null}

        <details className="more" open={extrasOpen || undefined}>
          <summary>Agregar un comentario o código de promoción</summary>
          <div className="stack" style={{ marginTop: 12 }}>
            <div className="field">
              <label htmlFor="note">Comentario para el doctor (opcional)</label>
              <textarea
                id="note"
                className="input"
                rows={2}
                maxLength={300}
                placeholder="Por ejemplo: me duele una muela del lado derecho"
                value={details.note}
                onChange={(event) => change('note', event.target.value)}
              />
            </div>
            <div className="field">
              <label htmlFor="promoCode">Código de promoción</label>
              <input
                id="promoCode"
                className="input"
                style={{ textTransform: 'uppercase' }}
                maxLength={30}
                value={details.promo_code}
                onChange={(event) => change('promo_code', event.target.value)}
                onBlur={() => onPromoCode(details.promo_code)}
                aria-describedby="promoCode-status"
                aria-invalid={promoError ? true : undefined}
              />
              <span id="promoCode-status" role="status">
                {promoError ? (
                  <span className="error">{promoError}</span>
                ) : promoHint ? (
                  <span className="hint">{promoHint}</span>
                ) : details.promo_code.trim() !== '' && campaign ? (
                  <span className="hint ok">Código aplicado: {campaign.name}.</span>
                ) : null}
              </span>
            </div>
          </div>
        </details>

        {device?.privacy_accepted ? null : (
          <div className="field">
            <label className="check">
              <input
                type="checkbox"
                checked={details.accepts_privacy}
                onChange={(event) => change('accepts_privacy', event.target.checked)}
                aria-invalid={errors.accepts_privacy ? true : undefined}
              />
              <span>
                Acepto la{' '}
                <a href={paths.privacy} target="_blank">
                  política de privacidad
                </a>
                : mis datos se usan solo para atenderme.
              </span>
            </label>
            {errors.accepts_privacy ? <span className="error">{errors.accepts_privacy}</span> : null}
          </div>
        )}
        {/* Quien ya aceptó recibir promociones no vuelve a ver la casilla en cada reserva. */}
        {device?.promotions_accepted ? null : (
          <label className="check">
            <input
              type="checkbox"
              checked={details.accepts_promotions}
              onChange={(event) => change('accepts_promotions', event.target.checked)}
            />
            <span>Quiero recibir promociones por WhatsApp (puedo darme de baja cuando quiera).</span>
          </label>
        )}

        {Object.keys(errors).length ? (
          <p className="form-problems" role="alert">
            Para reservar, revisa {toReview.length ? join(toReview) : 'los datos marcados'}.
          </p>
        ) : null}

        <button type="submit" className="btn block" disabled={busy || current !== undefined}>
          {busy ? 'Reservando…' : 'Reservar cita'}
        </button>
        <p className="muted small" style={{ marginTop: 8 }}>
          No se cobra nada ahora. {howToPay}
        </p>
      </form>
    </section>
  );
}
