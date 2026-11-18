import { keepPreviousData, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useRef, useState } from 'react';
import { useLocation, useNavigate, useSearchParams } from 'react-router';
import { problemOf } from '@/api/actions';
import { ApiError, apiUrl, cacheKey, get, post } from '@/api/client';
import { useDaySlots } from '@/api/slots';
import type {
  BookedAppointment,
  BookingDays,
  BookingOptions,
  BookingTreatment,
  DataResponse,
  Site,
  Slot,
} from '@/api/types';
import { withQuery } from '@/lib/paths';
import { Booked } from '@/pages/booking/Booked';
import { BookingSummary } from '@/pages/booking/BookingSummary';
import { StepDetails, type BookingDetails } from '@/pages/booking/StepDetails';
import { StepService } from '@/pages/booking/StepService';
import { StepTime } from '@/pages/booking/StepTime';

const STEPS = ['Servicio', 'Día y hora', 'Tus datos'];

/** Lo que el paso guarda en el historial del navegador. */
interface StepState {
  /** El paso anterior quedó justo detrás en el historial: "Cambiar…" vuelve con el botón atrás. */
  follows?: boolean;
  /** La cita recién reservada. */
  booked?: BookedAppointment;
}

interface Props {
  site: Site;
  options: BookingOptions;
  /** El código de promoción cambió: se piden de nuevo las opciones con él. */
  onPromoCode: (code: string) => void;
}

/**
 * Reserva en la web en pocos pasos: qué necesita, día y hora, sus datos y listo.
 *
 * El paso queda en la dirección de la página (?tratamiento=…&fecha=…&hora=…): el botón "atrás" vuelve al paso
 * anterior en vez de sacar de la reserva, y recargar no pierde el tratamiento ni el horario.
 */
export function BookingWizard({ site, options, onPromoCode }: Props) {
  const location = useLocation();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [params] = useSearchParams();
  const root = useRef<HTMLDivElement>(null);
  const state = (location.state ?? {}) as StepState;

  // Al abrir la página puede venir ya elegido el servicio (el de la promoción, o la evaluación con ella).
  const [opened] = useState(location.key);
  const treatment =
    options.treatments.find((option) => option.slug === params.get('tratamiento')) ??
    (location.key === opened
      ? options.treatments.find((option) => option.id === options.initial_treatment_id)
      : null) ??
    null;

  // Null: el primero que esté libre. Sin elegir, el doctor desde cuya ficha llegó, si atiende el servicio.
  const [doctorChoice, setDoctorChoice] = useState<number | null | undefined>(undefined);
  const [pickedDate, setPickedDate] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);

  const daysUrl = treatment
    ? apiUrl('booking/days', {
        treatment: treatment.id,
        doctor: doctorChoice === undefined ? options.preferred_doctor_id : doctorChoice,
      })
    : null;
  const daysQuery = useQuery<DataResponse<BookingDays>>({
    queryKey: [daysUrl ? cacheKey(daysUrl) : 'sin-servicio'],
    queryFn: () => get(daysUrl ?? ''),
    enabled: daysUrl !== null,
    placeholderData: keepPreviousData,
  });
  const days = treatment && daysQuery.data ? daysQuery.data.data : undefined;

  // El día que se mira: el de la dirección (ya con hora elegida), el que tocó o el primero con horarios.
  const wantedDate = params.get('fecha') ?? pickedDate;
  const date = days?.days.some((day) => day.date === wantedDate) ? wantedDate : (days?.date ?? null);
  const daySlots = useDaySlots(days, date, (day) =>
    apiUrl('booking/slots', { treatment: treatment?.id, doctor: days?.doctor_id, date: day }),
  );
  const dayLabel = days?.days.find((day) => day.date === date)?.label ?? null;
  // La hora de la dirección vale mientras siga libre: si ese día ya no se ofrece, la hora tampoco.
  const slot =
    date !== null && date === params.get('fecha')
      ? (daySlots.slots.find((option) => option.time === params.get('hora')) ?? null)
      : null;

  const booked = state.booked ?? null;
  const step = booked ? 4 : !treatment ? 1 : slot ? 3 : 2;
  const loading = step === 2 && (!days || daysQuery.isFetching || daySlots.loading);

  const doctors = days?.doctors ?? [];
  const doctorOf = (id: number | null | undefined) => doctors.find((doctor) => doctor.id === id)?.name ?? null;
  const chosenDoctor =
    step === 3
      ? doctorOf(slot?.doctor_id)
      : step === 2
        ? (doctorOf(days?.doctor_id) ?? (doctors.length === 1 ? doctorOf(doctors[0]?.id) : null))
        : null;

  /** La dirección de un paso: conserva con qué se llegó (la promoción, el doctor) y cambia lo elegido. */
  function addressOf(next: {
    treatment?: BookingTreatment | null;
    date?: string | null;
    time?: string | null;
  }): string {
    const kept = Object.fromEntries(
      [...params.entries()].filter(([key]) => !['tratamiento', 'fecha', 'hora'].includes(key)),
    );

    return withQuery(location.pathname, {
      ...kept,
      tratamiento: next.treatment?.slug,
      fecha: next.date,
      hora: next.time,
    });
  }

  function go(address: string, stepState: StepState, replace = false) {
    setError(null);
    setErrors({});
    void navigate(address, { state: stepState, replace, preventScrollReset: true });
  }

  /** Vuelve al paso anterior: con el botón atrás si quedó justo detrás en el historial. */
  function back(address: string) {
    if (state.follows) {
      setError(null);
      setErrors({});
      void navigate(-1);
    } else {
      go(address, {}, true);
    }
  }

  // En el celular cada paso empieza arriba, en los pasos numerados, y el lector de pantalla anuncia su título.
  // El paso 2 se pinta cuando llegan sus días: hasta entonces no hay título que enfocar.
  const shown = useRef(step);
  useEffect(() => {
    const title = root.current?.querySelector<HTMLElement>('.step-title');

    if (shown.current !== step && title) {
      shown.current = step;
      root.current?.scrollIntoView();
      title.focus({ preventScroll: true });
    }
  }, [step, days]);

  // Si la API devuelve datos por corregir, la pantalla va al primero: en el celular queda arriba, fuera de la vista.
  const [rejected, setRejected] = useState(0);
  useEffect(() => {
    const first = rejected ? root.current?.querySelector<HTMLElement>('[aria-invalid="true"]') : null;

    if (first) {
      (first.closest('.field') ?? first).scrollIntoView({ block: 'center' });
      first.focus({ preventScroll: true });
    }
  }, [rejected]);

  async function book(details: BookingDetails) {
    if (!treatment || !slot || date === null) {
      return;
    }

    setBusy(true);
    setError(null);

    try {
      const { data } = await post<DataResponse<BookedAppointment>>(apiUrl('booking/appointments'), {
        ...details,
        treatment_id: treatment.id,
        doctor_id: days?.doctor_id ?? null,
        date,
        time: slot.time,
        origin: options.origin,
        campaign_slug: options.campaign_slug,
      });

      // La cita nueva ya cuenta en el inicio, en Mi cuenta y en los horarios libres.
      void queryClient.invalidateQueries();
      // Con la cita ya reservada no se vuelve al formulario: retroceder ahí la reservaría dos veces.
      go(addressOf({}), { booked: data }, true);
    } catch (problem) {
      if (problem instanceof ApiError && Object.keys(problem.fieldErrors).length) {
        setErrors(problem.fieldErrors);
        setRejected((times) => times + 1);
      } else if (problem instanceof ApiError && problem.code === 'slot_taken') {
        // El horario se acaba de ocupar: de vuelta a elegir otro, con los horarios al día.
        void queryClient.invalidateQueries();
        go(addressOf({ treatment }), {}, true);
        setError(problem.message);
      } else {
        setError(problemOf(problem));
      }
    } finally {
      setBusy(false);
    }
  }

  return (
    <div ref={root} className={`stack-lg booking${step === 4 ? ' is-done' : ''}`}>
      {step < 4 ? (
        <ol className="steps" aria-label={`Paso ${step} de 3`}>
          {STEPS.map((name, index) => (
            <li
              key={name}
              className={index < step ? 'done' : undefined}
              aria-current={index + 1 === step ? 'step' : undefined}
            >
              {name}
            </li>
          ))}
        </ol>
      ) : null}

      {loading || busy ? <div className="loading-line" aria-hidden="true" /> : null}

      {error ? (
        <div className="flash bad" role="alert">
          {error}
        </div>
      ) : null}

      {/* La promoción con que llegó se ve en todos los pasos, para que sepa que va con ella. */}
      {step < 4 && options.campaign ? (
        <div className="flash info">
          <p>
            Reservas con la promoción {options.campaign.name}
            {options.campaign.discount ? ` · ${options.campaign.discount}` : ''}.
          </p>
          {options.campaign.treatment && treatment && treatment.id !== options.campaign.treatment.id ? (
            <p className="muted small">
              Se aplica a {options.campaign.treatment.name}: el doctor lo revisa en esta cita y lo programa contigo.
            </p>
          ) : null}
        </div>
      ) : null}

      {step === 1 ? (
        <StepService
          options={options}
          clinic={site.clinic}
          onChoose={(chosen) => {
            setDoctorChoice(undefined);
            setPickedDate(null);
            go(addressOf({ treatment: chosen }), { follows: true });
          }}
        />
      ) : null}

      {step === 2 && treatment && days ? (
        <StepTime
          treatment={treatment}
          days={days}
          date={date}
          slots={daySlots.slots}
          onBack={() => back(addressOf({}))}
          onDoctor={(doctorId) => {
            setError(null);
            setDoctorChoice(doctorId);
          }}
          onDate={(day) => {
            setError(null);
            setPickedDate(day);
          }}
          onTime={(chosen: Slot) => {
            setPickedDate(date);
            go(addressOf({ treatment, date, time: chosen.time }), { follows: true });
          }}
        />
      ) : null}

      {step === 3 && treatment && slot ? (
        <StepDetails
          options={options}
          clinic={site.clinic}
          treatment={treatment}
          day={dayLabel}
          slot={slot}
          doctor={chosenDoctor}
          howToPay={site.how_to_pay}
          errors={errors}
          busy={busy}
          onBack={() => back(addressOf({ treatment }))}
          onPromoCode={onPromoCode}
          onFix={(field) =>
            setErrors((before) =>
              field in before ? Object.fromEntries(Object.entries(before).filter(([key]) => key !== field)) : before,
            )
          }
          onSubmit={(details) => void book(details)}
        />
      ) : null}

      {step === 4 && booked ? <Booked appointment={booked} clinic={site.clinic} howToPay={site.how_to_pay} /> : null}

      {step < 4 ? (
        <BookingSummary
          treatment={treatment}
          day={step >= 2 ? dayLabel : null}
          time={step === 3 && slot ? slot.label : null}
          doctor={chosenDoctor}
        />
      ) : null}
    </div>
  );
}
