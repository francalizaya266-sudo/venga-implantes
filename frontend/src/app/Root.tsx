import { Suspense, useCallback, useState } from 'react';
import { Outlet, ScrollRestoration, useLocation, type Location } from 'react-router';
import { SiteLayout } from '@/components/layout/SiteLayout';
import { Loading } from '@/components/ui/Loading';
import { FlashProvider } from '@/lib/flash';
import { firstEntryKey } from '@/lib/scroll';
import { ErrorPage } from '@/pages/ErrorPage';

interface Props {
  /** El estado con que Laravel respondió al abrir la web, si fue un error (403, 404, 429…). */
  documentError: number | null;
}

/**
 * Lo que envuelve a todas las páginas: los avisos y la posición de la página al ir y volver. Si Laravel abrió la
 * web con un error, se muestra ese error hasta que el paciente pasa a otra página.
 */
export function Root({ documentError }: Props) {
  const location = useLocation();
  const [opened] = useState(location.key);
  const failed = documentError !== null && location.key === opened;
  const [firstKey] = useState(firstEntryKey);
  const scrollKey = useCallback((entry: Location) => (entry.key === 'default' ? firstKey : entry.key), [firstKey]);

  return (
    <FlashProvider>
      <ScrollRestoration getKey={scrollKey} />
      {/* Mientras llega el código de una página abierta directamente: la cabecera, el pie y el hueco de la página. */}
      <Suspense
        fallback={
          <SiteLayout>
            <Loading />
          </SiteLayout>
        }
      >
        {failed ? <ErrorPage status={documentError} /> : <Outlet />}
      </Suspense>
    </FlashProvider>
  );
}
