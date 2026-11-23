import type { Treatment } from '@/api/types';
import { AppLink } from '@/components/ui/AppLink';
import { TreatmentArt } from '@/components/ui/Art';
import { paths } from '@/lib/paths';
import { keepAmountTogether } from '@/lib/text';

/** Los tratamientos del inicio: su imagen arriba, el nombre, el precio "desde" en su pastilla celeste y para qué sirve. */
export function ServiceCards({ treatments }: { treatments: Treatment[] }) {
  return (
    <ul className="services-grid">
      {treatments.map((treatment) => (
        <li key={treatment.id}>
          <AppLink href={paths.treatment(treatment.slug)}>
            <span className="art">
              <TreatmentArt art={treatment.art} size={128} />
            </span>
            <span className="name">{treatment.name}</span>
            {treatment.price ? (
              <span className="price">{keepAmountTogether(treatment.price)}</span>
            ) : (
              <span className="price ask">Precio en la evaluación</span>
            )}
            {treatment.summary ? <span className="desc">{treatment.summary}</span> : null}
          </AppLink>
        </li>
      ))}
    </ul>
  );
}
