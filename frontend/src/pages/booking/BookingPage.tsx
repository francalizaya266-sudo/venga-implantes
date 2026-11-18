import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { useSearchParams } from 'react-router';
import { apiUrl, cacheKey, get } from '@/api/client';
import { pageError, usePageMeta, useSite } from '@/api/queries';
import type { BookingOptions, PageResponse } from '@/api/types';
import { SiteLayout } from '@/components/layout/SiteLayout';
import { Loading } from '@/components/ui/Loading';
import { BookingWizard } from '@/pages/booking/BookingWizard';
import { ErrorPage } from '@/pages/ErrorPage';

/** Con qué se llega a reservar: el código o la promoción, de dónde viene, con qué doctor, qué servicio y para quién. */
const ENTRY = ['codigo', 'promo', 'origen', 'doctor', 'tratamiento', 'para'];

/**
 * Reservar cita. Mientras se reserva nada distrae: sin botón flotante de WhatsApp (taparía los horarios), el
 * encabezado solo con el nombre y el pie de una línea.
 */
export function BookingPage() {
  const site = useSite();
  const [params] = useSearchParams();
  // Las opciones se piden con lo que traía la dirección al abrir la página: los pasos la cambian después.
  const [entry] = useState(() => Object.fromEntries(ENTRY.map((key) => [key, params.get(key)])));
  const [promoCode, setPromoCode] = useState<string | null>(null);
  const url = apiUrl('booking/options', promoCode === null ? entry : { ...entry, codigo: promoCode });

  const { data, error: failure } = useQuery<PageResponse<BookingOptions>, Error>({
    queryKey: [cacheKey(url)],
    queryFn: () => get(url),
    // Al escribir un código de promoción, el formulario sigue en pantalla mientras se consulta.
    placeholderData: keepPreviousData,
  });
  // Y si esa consulta no llega (sin señal), sigue con las opciones que ya tenía: no se pierde lo escrito.
  const [options, setOptions] = useState(data);

  if (data && data !== options) {
    setOptions(data);
  }

  const error = pageError(options, failure);

  usePageMeta(options?.meta);

  if (error) {
    return <ErrorPage error={error} />;
  }

  return (
    <SiteLayout page="reservar" wide whatsappFloat={false} minimalFooter>
      {site && options ? (
        <BookingWizard
          site={site}
          options={options.data}
          onPromoCode={(code) => setPromoCode(code.trim().toUpperCase())}
        />
      ) : (
        <Loading />
      )}
    </SiteLayout>
  );
}
