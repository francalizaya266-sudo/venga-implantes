import { usePage, useSite } from '@/api/queries';
import type { ReviewsData } from '@/api/types';
import { SiteLayout } from '@/components/layout/SiteLayout';
import { Reviews } from '@/components/site/Reviews';
import { Loading } from '@/components/ui/Loading';
import { ErrorPage } from '@/pages/ErrorPage';

/** Opiniones de pacientes, con el paso al perfil de Google. */
export function ReviewsPage() {
  const site = useSite();
  const { data, error } = usePage<ReviewsData>('reviews');

  if (error) {
    return <ErrorPage error={error} />;
  }

  if (!site || !data) {
    return (
      <SiteLayout page="opiniones" wide>
        <Loading />
      </SiteLayout>
    );
  }

  const { reviews } = data.data;
  const reviewsUrl = site.clinic.reviews_url;

  return (
    <SiteLayout page="opiniones" wide>
      {reviewsUrl ? (
        <p className="page-intro">
          Puedes leerlas todas, y dejar la tuya, en{' '}
          <a href={reviewsUrl} rel="noopener" target="_blank">
            nuestro perfil de Google
          </a>
          .
        </p>
      ) : null}

      {reviews.length ? (
        <Reviews reviews={reviews} />
      ) : (
        <p className="panel">Pronto publicaremos opiniones de nuestros pacientes.</p>
      )}
    </SiteLayout>
  );
}
