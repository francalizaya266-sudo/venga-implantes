import type { BookingOptions, BookingTreatment, Clinic } from '@/api/types';
import { TreatmentArt } from '@/components/ui/Art';
import { whatsappUrl } from '@/lib/whatsapp';
import { InTreatment } from '@/pages/booking/InTreatment';

interface Props {
  options: BookingOptions;
  clinic: Clinic;
  onChoose: (treatment: BookingTreatment) => void;
}

/** Paso 1: qué necesita. */
export function StepService({ options, clinic, onChoose }: Props) {
  const { device } = options;
  // Con el celular recordado ya se sabe si está en tratamiento: se dice ahora, no al final.
  const current = device?.family.find((person) => person.id === device.patient_id && person.in_treatment);
  // Quien tiene dolor no espera un horario de la web: se le da una salida desde el primer paso.
  const urgent = whatsappUrl(clinic, 'Hola, tengo dolor y necesito una cita lo antes posible.');

  return (
    <section className="stack" aria-labelledby="paso-1">
      <h2 id="paso-1" className="step-title" tabIndex={-1}>
        ¿Qué necesitas?
      </h2>
      <p className="lead">
        Elige el motivo de tu cita. Si lo que buscas no está aquí, empieza con una evaluación: ahí el doctor te da el
        diagnóstico y el presupuesto.
      </p>

      {current ? <InTreatment person={current} clinic={clinic} others /> : null}

      <ul className="choice-list">
        {options.treatments.length ? (
          options.treatments.map((treatment) => (
            <li key={treatment.id}>
              {/* Cada opción con la imagen de su tratamiento: se elige de un vistazo. */}
              <button
                type="button"
                className={`choice with-art${treatment.is_evaluation ? ' featured' : ''}`}
                onClick={() => onChoose(treatment)}
              >
                <span className="art">
                  <TreatmentArt art={treatment.art} size={64} lazy={false} />
                </span>
                <strong>{treatment.label}</strong>
                {treatment.price ? (
                  <span className="price">
                    {treatment.price.was ? (
                      <>
                        <s>{treatment.price.was}</s>{' '}
                      </>
                    ) : null}
                    {treatment.price.now}
                  </span>
                ) : null}
                {treatment.summary ? <span className="meta">{treatment.summary}</span> : null}
                <span className="minutes">{treatment.duration_minutes} min aprox.</span>
              </button>
            </li>
          ))
        ) : (
          <li className="panel">
            Por ahora no hay horarios para reservar en la web. Escríbenos por WhatsApp y te ayudamos.
          </li>
        )}
        {urgent ? (
          <li>
            <a href={urgent} className="choice with-art urgent" rel="noopener">
              <span className="art" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="currentColor">
                  <path d="M12 3.5c-4.97 0-9 3.47-9 7.75 0 2.3 1.17 4.37 3.02 5.79-.1 1.13-.5 2.3-1.3 3.2-.2.23-.03.6.28.57 1.9-.2 3.4-.9 4.5-1.7.8.2 1.63.3 2.5.3 4.97 0 9-3.47 9-7.76 0-4.28-4.03-7.75-9-7.75Z" />
                </svg>
              </span>
              <strong>Tengo dolor o es urgente</strong>
              <span className="meta">Escríbenos por WhatsApp y te decimos el horario más cercano.</span>
            </a>
          </li>
        ) : null}
      </ul>

      <p className="muted small">
        ¿Ya estás en tratamiento? Tu doctor programa tus próximas sesiones; si necesitas un cambio, escríbenos.
      </p>
    </section>
  );
}
