import { usePage } from '@/api/queries';
import type { TreatmentsData } from '@/api/types';
import { SiteLayout } from '@/components/layout/SiteLayout';
import { PriceList } from '@/components/site/PriceList';
import { AppLink } from '@/components/ui/AppLink';
import { Loading } from '@/components/ui/Loading';
import { Section } from '@/components/ui/Section';
import { artUrl } from '@/lib/art';
import { paths } from '@/lib/paths';
import { ErrorPage } from '@/pages/ErrorPage';

/** Servicios: la lista con duración y precio "desde". */
export function TreatmentsPage() {
  const { data, error } = usePage<TreatmentsData>('treatments');

  if (error) {
    return <ErrorPage error={error} />;
  }

  return (
    <SiteLayout page="servicios" wide>
      {data ? <Treatments data={data.data} /> : <Loading />}
    </SiteLayout>
  );
}

function Treatments({ data: { treatments, evaluation } }: { data: TreatmentsData }) {
  return (
    <>
      {treatments.length ? (
        <PriceList treatments={treatments} />
      ) : (
        <p className="panel">Pronto publicaremos nuestros servicios. Mientras tanto, escríbenos por WhatsApp.</p>
      )}

      <Section className="cta-band with-art">
        <div>
          <h2>¿No sabes qué necesitas?</h2>
          <p>Empieza con una evaluación: el doctor revisa, te explica las opciones y te da el presupuesto.</p>
          {/* El botón dice lo que hace: con evaluación en la web va directo a ella; sin ella, a elegir. */}
          <AppLink href={paths.booking({ tratamiento: evaluation?.slug })} className="btn">
            {evaluation ? 'Reservar evaluación' : 'Reservar cita'}
          </AppLink>
        </div>
        <img src={artUrl('evaluacion')} alt="" width={640} height={640} loading="lazy" />
      </Section>
    </>
  );
}
