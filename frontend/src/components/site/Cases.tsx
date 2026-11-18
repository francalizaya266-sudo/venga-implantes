import type { SmileCase } from '@/api/types';
import { AppLink } from '@/components/ui/AppLink';
import { Html } from '@/components/ui/Html';
import { paths } from '@/lib/paths';

export function Cases({ cases, withDescription = false }: { cases: SmileCase[]; withDescription?: boolean }) {
  return (
    <div className="cases">
      {cases.map((smileCase) => (
        <figure key={smileCase.id} className="case">
          <div className="pair">
            <div>
              <img src={smileCase.before_url} alt={`Antes: ${smileCase.title}`} loading="lazy" />
              <span className="tag">Antes</span>
            </div>
            <div>
              <img src={smileCase.after_url} alt={`Después: ${smileCase.title}`} loading="lazy" />
              <span className="tag">Después</span>
            </div>
          </div>
          <figcaption>
            <strong>{smileCase.title}</strong>
            {smileCase.treatment ? (
              <>
                {' '}
                <AppLink href={paths.treatment(smileCase.treatment.slug)}>{smileCase.treatment.name}</AppLink>
              </>
            ) : null}
            {withDescription && smileCase.description_html ? (
              <Html className="prose" html={smileCase.description_html} />
            ) : null}
          </figcaption>
        </figure>
      ))}
    </div>
  );
}
