import { useSearchParams } from 'react-router';
import { usePage } from '@/api/queries';
import type { CasesData } from '@/api/types';
import { SiteLayout } from '@/components/layout/SiteLayout';
import { Cases } from '@/components/site/Cases';
import { AppLink } from '@/components/ui/AppLink';
import { Loading } from '@/components/ui/Loading';
import { paths, withQuery } from '@/lib/paths';
import { ErrorPage } from '@/pages/ErrorPage';

/** Antes y después: los casos publicados, con filtro por tratamiento. */
export function CasesPage() {
  const [params] = useSearchParams();
  const { data, error } = usePage<CasesData>('cases', { tratamiento: params.get('tratamiento') });

  if (error) {
    return <ErrorPage error={error} />;
  }

  return (
    <SiteLayout page="casos" wide>
      {data ? <CasesBody data={data.data} /> : <Loading />}
    </SiteLayout>
  );
}

function CasesBody({ data: { cases, treatments, selected } }: { data: CasesData }) {
  return (
    <>
      {treatments.length > 1 ? (
        <nav className="chips" aria-label="Filtrar por tratamiento">
          <AppLink href={paths.cases} className="chip" aria-current={selected ? undefined : 'page'}>
            Todos
          </AppLink>
          {treatments.map((treatment) => (
            <AppLink
              key={treatment.slug}
              href={withQuery(paths.cases, { tratamiento: treatment.slug })}
              className="chip"
              aria-current={selected === treatment.slug ? 'page' : undefined}
            >
              {treatment.name}
            </AppLink>
          ))}
        </nav>
      ) : null}

      {cases.length ? (
        <Cases cases={cases} withDescription />
      ) : (
        <p className="panel">Pronto publicaremos casos de nuestros pacientes.</p>
      )}
    </>
  );
}
