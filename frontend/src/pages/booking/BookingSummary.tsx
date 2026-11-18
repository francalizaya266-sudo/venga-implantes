import type { BookingTreatment } from '@/api/types';
import { TreatmentArt } from '@/components/ui/Art';
import { Icon } from '@/components/ui/Icon';
import { artUrl } from '@/lib/art';

interface Props {
  /** Lo que lleva elegido. De vuelta en el primer paso, el servicio está otra vez por elegir. */
  treatment: BookingTreatment | null;
  day: string | null;
  time: string | null;
  doctor: string | null;
}

/** El resumen de lo que lleva elegido, a la derecha en pantallas anchas. Lo que falta dice "Por elegir". */
export function BookingSummary({ treatment, day, time, doctor }: Props) {
  return (
    <aside className="booking-summary" aria-labelledby="resumen">
      <h2 id="resumen">
        <Icon name="calendar-check" />
        Resumen de tu cita
      </h2>
      <div className="chosen">
        <span className="art">
          {treatment ? (
            <TreatmentArt art={treatment.art} size={72} lazy={false} />
          ) : (
            <img src={artUrl('diente-192')} width={72} height={72} alt="" decoding="async" />
          )}
        </span>
        <p>
          <span>Servicio</span>
          <strong className={treatment ? undefined : 'pending'}>{treatment?.name ?? 'Por elegir'}</strong>
          {treatment ? <small>{treatment.duration_minutes} min aprox.</small> : null}
        </p>
      </div>
      <dl>
        <div>
          <dt>
            <Icon name="calendar" />
            Día
          </dt>
          <dd className={treatment && day ? undefined : 'pending'}>{treatment && day ? day : 'Por elegir'}</dd>
        </div>
        <div>
          <dt>
            <Icon name="clock" />
            Hora
          </dt>
          <dd className={time ? undefined : 'pending'}>{time ?? 'Por elegir'}</dd>
        </div>
        {doctor ? (
          <div>
            <dt>
              <Icon name="user" />
              Doctor
            </dt>
            <dd>{doctor}</dd>
          </div>
        ) : null}
      </dl>
      {treatment?.price_line ? <p className="price-line">{treatment.price_line}</p> : null}
      {/* Lo que pasa al reservar, tal como lo hace el sistema: nada de esto es una promesa aparte. */}
      <ul className="assurances">
        <li>
          <Icon name="check" />
          No se cobra nada ahora
        </li>
        <li>
          <Icon name="check" />
          Te escribimos por WhatsApp para confirmar
        </li>
        <li>
          <Icon name="check" />
          Puedes cambiar el horario o cancelar
        </li>
      </ul>
    </aside>
  );
}
