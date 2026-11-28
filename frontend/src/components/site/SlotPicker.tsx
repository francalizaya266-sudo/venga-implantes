import { useEffect, useRef, useState } from 'react';
import type { PickerDay, Slot } from '@/api/types';
import { withArticle } from '@/lib/text';

interface Props {
  /** Los días con horarios libres. */
  days: PickerDay[];
  /** El día elegido (Y-m-d) y sus horarios libres. */
  date: string | null;
  slots: Slot[];
  /** Los horarios ya elegidos: "Y-m-d H:i". */
  selected?: string[];
  onDate: (date: string) => void;
  onTime: (slot: Slot) => void;
  /** Aviso junto a los horarios (cuántos lleva elegidos al proponer un cambio). */
  note?: string | null;
  /** Ya eligió todos los que puede; los demás horarios no se pueden tocar hasta quitar uno. */
  full?: boolean;
  /** El nivel de los dos títulos ("Día" y el día elegido). */
  headingLevel?: 'h2' | 'h3';
  /** Se buscó con un doctor en particular: su nombre, para decir con quién no hay horarios. */
  doctor?: string | null;
  /** Trae los días libres que siguen ("Ver más días"); sin él, la tira se queda con los que hay. */
  onMoreDays?: () => void;
  /** Hay más días libres después de los que se ven. */
  more?: boolean;
  /** Hasta qué día reserva la web, para decirlo cuando ya no hay más días. */
  until?: string | null;
}

/** Días con horarios libres y los horarios del día elegido, primero los que no dejan huecos en la agenda. */
export function SlotPicker({
  days,
  date,
  slots,
  selected = [],
  onDate,
  onTime,
  note = null,
  full = false,
  headingLevel: Heading = 'h2',
  doctor = null,
  onMoreDays,
  more = false,
  until = null,
}: Props) {
  const strip = useRef<HTMLDivElement>(null);
  // Al llegar los días que siguen, el foco pasa al primero de ellos: el botón puede desaparecer al no haber más.
  const firstNew = useRef<number | null>(null);
  useEffect(() => {
    if (firstNew.current !== null && days.length > firstNew.current) {
      strip.current?.querySelectorAll<HTMLButtonElement>('.day')[firstNew.current]?.focus();
      firstNew.current = null;
    }
  }, [days.length]);

  if (!days.length) {
    return (
      <div className="panel">
        <p>
          No encontramos horarios libres en las próximas semanas{doctor ? ` con ${withArticle(doctor)}` : ''}.
          Escríbenos por WhatsApp y te buscamos un espacio.
        </p>
      </div>
    );
  }

  return (
    <>
      <div className="stack">
        <Heading>Día</Heading>
        <div ref={strip} className="days" role="group" aria-label="Días con horarios libres">
          {days.map((option) => (
            <button
              key={option.date}
              type="button"
              className="day"
              aria-pressed={date === option.date}
              aria-label={option.label}
              onClick={() => onDate(option.date)}
            >
              <span>{option.weekday}</span>
              <strong>{option.number}</strong>
              <span>{option.month}</span>
            </button>
          ))}
        </div>
        {/* Antes la tira se quedaba en los 12 primeros días libres, aunque la web reservaba más adelante. */}
        {onMoreDays && more ? (
          <button
            type="button"
            className="link-btn"
            onClick={() => {
              firstNew.current = days.length;
              onMoreDays();
            }}
          >
            Ver más días
          </button>
        ) : onMoreDays && until ? (
          <p className="muted small">Por ahora la web reserva hasta el {until}.</p>
        ) : null}
      </div>

      {/* Al cambiar de día, "Ver más horarios" vuelve a empezar. */}
      <Times
        key={date}
        heading={<Heading>{days.find((option) => option.date === date)?.label}</Heading>}
        date={date}
        slots={slots}
        selected={selected}
        onTime={onTime}
        note={note}
        full={full}
      />
    </>
  );
}

interface TimesProps extends Pick<Props, 'date' | 'slots' | 'onTime' | 'note' | 'full'> {
  heading: React.ReactNode;
  selected: string[];
}

function Times({ heading, date, slots, selected, onTime, note, full }: TimesProps) {
  const [showAll, setShowAll] = useState(false);
  const visible = showAll ? slots : slots.filter((slot) => slot.suggested);
  const groups = [
    { period: 'Por la mañana', slots: visible.filter((slot) => slot.time < '12:00') },
    { period: 'Por la tarde', slots: visible.filter((slot) => slot.time >= '12:00') },
  ].filter((group) => group.slots.length);

  return (
    <div className="stack">
      {heading}
      {note ? (
        <p className="times-note" role="status">
          {note}
        </p>
      ) : null}
      {groups.map((group) => (
        <div key={group.period} className="stack">
          <p className="muted small">{group.period}</p>
          <div className="times">
            {group.slots.map((slot) => {
              const chosen = selected.includes(`${date} ${slot.time}`);

              return (
                <button
                  key={slot.time}
                  type="button"
                  className="time"
                  aria-pressed={chosen}
                  disabled={full && !chosen}
                  onClick={() => onTime(slot)}
                >
                  {slot.label}
                </button>
              );
            })}
          </div>
        </div>
      ))}

      {!showAll && visible.length < slots.length ? (
        <button type="button" className="link-btn" onClick={() => setShowAll(true)}>
          Ver más horarios
        </button>
      ) : null}
    </div>
  );
}
