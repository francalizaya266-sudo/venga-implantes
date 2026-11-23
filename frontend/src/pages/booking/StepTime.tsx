import type { BookingDays, BookingTreatment, Slot } from '@/api/types';
import { SlotPicker } from '@/components/site/SlotPicker';

interface Props {
  treatment: BookingTreatment;
  days: BookingDays;
  date: string | null;
  slots: Slot[];
  onBack: () => void;
  onDoctor: (doctorId: number | null) => void;
  onDate: (date: string) => void;
  onTime: (slot: Slot) => void;
}

/** Paso 2: día y hora. En pantallas anchas, los días a un lado y los horarios al otro. */
export function StepTime({ treatment, days, date, slots, onBack, onDoctor, onDate, onTime }: Props) {
  return (
    <section className="stack" aria-labelledby="paso-2">
      <button type="button" className="link-btn" onClick={onBack}>
        ← Cambiar servicio
      </button>
      <h2 id="paso-2" className="step-title" tabIndex={-1}>
        Elige día y hora
      </h2>
      <p className="lead">
        {treatment.name} · {treatment.duration_minutes} min aprox.
      </p>

      <div className="pick">
        {days.doctors.length > 1 ? (
          <div className="stack with-whom">
            <h3>Con quién</h3>
            <div className="chips wrap" role="group" aria-label="Doctor">
              <button
                type="button"
                className="chip"
                aria-pressed={days.doctor_id === null}
                onClick={() => onDoctor(null)}
              >
                El primero disponible
              </button>
              {days.doctors.map((doctor) => (
                <button
                  key={doctor.id}
                  type="button"
                  className="chip"
                  aria-pressed={days.doctor_id === doctor.id}
                  onClick={() => onDoctor(doctor.id)}
                >
                  {doctor.name}
                </button>
              ))}
            </div>
          </div>
        ) : null}

        <SlotPicker
          days={days.days}
          date={date}
          slots={slots}
          onDate={onDate}
          onTime={onTime}
          headingLevel="h3"
          doctor={days.doctors.find((doctor) => doctor.id === days.doctor_id)?.name ?? null}
        />
      </div>
    </section>
  );
}
