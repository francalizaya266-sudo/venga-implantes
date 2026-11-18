import type { NextFree as NextFreeData } from '@/api/types';
import { AppLink } from '@/components/ui/AppLink';
import { paths } from '@/lib/paths';

/** Los próximos horarios libres de verdad, sacados de la agenda: un toque y queda elegido. */
export function NextFree({
  nextFree,
  headingLevel: Heading = 'h2',
}: {
  nextFree: NextFreeData;
  headingLevel?: 'h2' | 'h3';
}) {
  const { treatment, promo } = nextFree;

  return (
    <div className="next-free" aria-labelledby="proximos">
      <div className="next-free-head">
        <Heading id="proximos">{nextFree.title}</Heading>
        <p className="when">{nextFree.day}</p>
        <p className="for">
          {treatment.name} · {treatment.duration_minutes} min
        </p>
      </div>
      <ul className="free-times">
        {nextFree.times.map(({ time, label }) => (
          <li key={time}>
            <AppLink
              href={paths.booking({
                tratamiento: treatment.slug,
                fecha: nextFree.date,
                hora: time,
                promo,
              })}
            >
              {label}
            </AppLink>
          </li>
        ))}
      </ul>
      <AppLink href={paths.booking({ tratamiento: treatment.slug, promo })} className="more">
        Ver otros días y horarios
      </AppLink>
    </div>
  );
}
