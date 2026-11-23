import { isValidElement, lazy, type ComponentType } from 'react';
import { createBrowserRouter, matchRoutes, useRouteError, type RouteObject } from 'react-router';
import { Root } from '@/app/Root';
import { ErrorPage } from '@/pages/ErrorPage';
import { HomePage } from '@/pages/HomePage';

/** Cómo se baja el código de cada página que no viene en el archivo principal (loadPage y prefetchPages). */
const pageLoaders = new Map<unknown, () => Promise<unknown>>();

/**
 * Una página cuyo código se descarga recién al abrirla. El inicio, que es por donde entra casi todo el mundo, va
 * en el archivo principal; la reserva, Mi cuenta y las demás páginas, cada una en el suyo. Al pasar de una página
 * a otra se queda la de antes hasta que llega la nueva. Con su código ya en el navegador (lo bajaron loadPage o
 * prefetchPages), la página sale de una vez, sin pasar por el hueco de Root.
 */
function onDemand<Name extends string, Module extends Record<Name, ComponentType>>(
  name: Name,
  load: () => Promise<Module>,
) {
  let Page: ComponentType | undefined;
  const fetchPage = async () => (Page ??= (await load())[name]);
  const Lazy = lazy(async () => ({ default: await fetchPage() }));

  function OnDemand() {
    return Page ? <Page /> : <Lazy />;
  }

  pageLoaders.set(OnDemand, fetchPage);

  return OnDemand;
}

/**
 * Baja el código de la página de una dirección, si viene aparte. main.tsx lo espera antes de pintar la página con
 * que se abrió la web (el documento ya lo pidió junto con el archivo principal): así sale completa de una vez, sin
 * mostrar antes el hueco de la página. Si no llega (sin señal), se pinta igual y la página lo vuelve a pedir.
 */
export function loadPage(pathname: string): Promise<unknown> {
  const element = matchRoutes(pages, pathname)?.at(-1)?.route.element;
  const load = isValidElement(element) ? pageLoaders.get(element.type) : undefined;

  return load ? load().catch(() => undefined) : Promise.resolve();
}

/**
 * Con la primera página ya en pantalla, el navegador baja el código de las demás en un rato libre: así, al tocar
 * "Reservar cita" o el menú, la página sale de una vez y no espera su archivo. Con el ahorro de datos activado no
 * se adelanta nada: cada página se baja al abrirla.
 */
export function prefetchPages(): void {
  const connection = (navigator as Navigator & { connection?: { saveData?: boolean } }).connection;

  if (connection?.saveData) {
    return;
  }

  const prefetch = () => {
    for (const load of pageLoaders.values()) {
      // Es solo un adelanto: si no llega (sin señal), no hay nada que avisar.
      load().catch(() => undefined);
    }
  };

  if (typeof window.requestIdleCallback === 'function') {
    window.requestIdleCallback(prefetch, { timeout: 5000 });
  } else {
    window.setTimeout(prefetch, 2000);
  }
}

const AccountPage = onDemand('AccountPage', () => import('@/pages/account/AccountPage'));
const HealthPage = onDemand('HealthPage', () => import('@/pages/account/HealthPage'));
const BookingPage = onDemand('BookingPage', () => import('@/pages/booking/BookingPage'));
const CasesPage = onDemand('CasesPage', () => import('@/pages/CasesPage'));
const AccountChangePage = onDemand('AccountChangePage', () => import('@/pages/change/ChangePage'));
const LinkChangePage = onDemand('LinkChangePage', () => import('@/pages/change/ChangePage'));
const ContactPage = onDemand('ContactPage', () => import('@/pages/ContactPage'));
const FaqPage = onDemand('FaqPage', () => import('@/pages/FaqPage'));
const LinkPage = onDemand('LinkPage', () => import('@/pages/link/LinkPage'));
const PrivacyPage = onDemand('PrivacyPage', () => import('@/pages/PrivacyPage'));
const PromotionPage = onDemand('PromotionPage', () => import('@/pages/PromotionPage'));
const PromotionsPage = onDemand('PromotionsPage', () => import('@/pages/PromotionsPage'));
const ReviewsPage = onDemand('ReviewsPage', () => import('@/pages/ReviewsPage'));
const TeamPage = onDemand('TeamPage', () => import('@/pages/TeamPage'));
const TreatmentPage = onDemand('TreatmentPage', () => import('@/pages/TreatmentPage'));
const TreatmentsPage = onDemand('TreatmentsPage', () => import('@/pages/TreatmentsPage'));

/**
 * Las páginas de la web. Las direcciones son las mismas rutas con nombre de Laravel (backend/routes/web.php):
 * al abrir cualquiera, Laravel entrega el documento con sus datos y de ahí en adelante se navega sin recargar.
 */
export const pages: RouteObject[] = [
  { path: '/', element: <HomePage /> },
  { path: '/tratamientos', element: <TreatmentsPage /> },
  { path: '/tratamientos/:slug', element: <TreatmentPage /> },
  { path: '/promociones', element: <PromotionsPage /> },
  { path: '/promociones/:slug', element: <PromotionPage /> },
  { path: '/equipo', element: <TeamPage /> },
  { path: '/antes-y-despues', element: <CasesPage /> },
  { path: '/opiniones', element: <ReviewsPage /> },
  { path: '/preguntas-frecuentes', element: <FaqPage /> },
  { path: '/contacto', element: <ContactPage /> },
  { path: '/privacidad', element: <PrivacyPage /> },
  { path: '/reservar', element: <BookingPage /> },
  { path: '/mi-cuenta', element: <AccountPage /> },
  { path: '/mi-cuenta/citas/:id/cambiar', element: <AccountChangePage /> },
  { path: '/mi-cuenta/pacientes/:id/salud', element: <HealthPage /> },
  { path: '/c/:token', element: <LinkPage /> },
  { path: '/c/:token/cambiar', element: <LinkChangePage /> },
  { path: '*', element: <ErrorPage status={404} /> },
];

/**
 * Si una página no se pudo pintar (por ejemplo, su código no llegó porque se fue la señal): la página del error,
 * con la cabecera, el pie y "Volver a intentar", en vez del aviso en inglés de React Router.
 */
function PageError() {
  return <ErrorPage error={useRouteError()} />;
}

export function routes(documentError: number | null = null): RouteObject[] {
  return [
    {
      element: <Root documentError={documentError} />,
      children: [{ errorElement: <PageError />, children: pages }],
    },
  ];
}

export function createAppRouter(documentError: number | null) {
  return createBrowserRouter(routes(documentError));
}
