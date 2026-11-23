import type { Treatment } from '@/api/types';
import { AppLink } from '@/components/ui/AppLink';
import { TreatmentArt } from '@/components/ui/Art';
import { Icon } from '@/components/ui/Icon';
import { paths } from '@/lib/paths';
import { keepAmountTogether } from '@/lib/text';

/** Una tarjeta por servicio: su imagen, el nombre, el precio "desde", para qué sirve, cuánto dura y el paso a su página. */
export function PriceList({ treatments }: { treatments: Treatment[] }) {
  return (
    <ul className="price-list">
      {treatments.map((treatment) => (
        <li key={treatment.id}>
          <AppLink href={paths.treatment(treatment.slug)}>
            <span className="icon">
              <TreatmentArt art={treatment.art} size={120} />
            </span>
            <span className="name">{treatment.name}</span>
            {treatment.price ? (
              <span className="price">{keepAmountTogether(treatment.price)}</span>
            ) : (
              <span className="price ask">Precio en la evaluación</span>
            )}
            {treatment.summary ? <span className="desc">{treatment.summary}</span> : null}
            <span className="meta">
              {treatment.duration_minutes} min aprox.
              {treatment.sessions_text ? ` · ${treatment.sessions_text}` : ''}
            </span>
            <span className="more">
              Saber más
              <Icon name="arrow-right" />
            </span>
          </AppLink>
        </li>
      ))}
    </ul>
  );
}
