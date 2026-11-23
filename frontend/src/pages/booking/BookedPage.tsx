import { useEffect, useRef } from 'react';
import { useLocation, useParams, useSearchParams } from 'react-router';
import { ApiError } from '@/api/client';
import { usePage, useSite } from '@/api/queries';
import type { BookedAppointment } from '@/api/types';
import { SiteLayout } from '@/components/layout/SiteLayout';
import { Loading } from '@/components/ui/Loading';
import { Booked } from '@/pages/booking/Booked';
import { ErrorPage } from '@/pages/ErrorPage';

/** Lo que la reserva deja en el historial al abrir esta página: la cita tal como la respondió la API. */
interface BookedState {
  booked?: BookedAppointment;
}

/**
 * "¡Tu cita quedó reservada!" con su propia dirección (/reservar/cita/…?signature=…): recargar la página o volver a
 * ella no la pierde. La dirección va firmada, como la del calendario: solo la tiene quien reservó.
 */
export function BookedPage() {
  const { id = '' } = useParams();
  const [params] = useSearchParams();
  const location = useLocation();
  const site = useSite();
  const root = useRef<HTMLDivElement>(null);
  // Recién reservada, la cita llega con la página y se ve al instante; al recargar se pide a la API.
  const justBooked = (location.state as BookedState | null)?.booked;
  const { data, error } = usePage<BookedAppointment>(`booking/appointments/${id}`, {
    signature: params.get('signature'),
  });
  const appointment = data?.data ?? justBooked;

  // El lector de pantalla anuncia que quedó reservada.
  const shown = appointment !== undefined && site !== undefined;
  useEffect(() => {
    if (shown) {
      root.current?.querySelector<HTMLElement>('.step-title')?.focus();
    }
  }, [shown]);

  // Lo que responde la API manda (por ejemplo, si la cita ya se canceló); sin señal, sigue a la vista la recién hecha.
  if (error && (error instanceof ApiError || !justBooked)) {
    return <ErrorPage error={error} />;
  }

  return (
    <SiteLayout page="reservar" wide whatsappFloat={false} minimalFooter>
      {site && appointment ? (
        <div ref={root} className="stack-lg booking is-done">
          <Booked appointment={appointment} clinic={site.clinic} howToPay={site.how_to_pay} />
        </div>
      ) : (
        <Loading />
      )}
    </SiteLayout>
  );
}
