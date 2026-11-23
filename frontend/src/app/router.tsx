import { lazy, type ComponentType } from 'react';
import { createBrowserRouter, type RouteObject } from 'react-router';
import { Root } from '@/app/Root';
import { ErrorPage } from '@/pages/ErrorPage';
import { HomePage } from '@/pages/HomePage';

/**
 * Una página cuyo código se descarga recién al abrirla. El inicio, que es por donde entra casi todo el mundo, va
 * en el archivo principal; la reserva, Mi cuenta y las demás páginas, cada una en el suyo. Al pasar de una página
 * a otra se queda la de antes hasta que llega la nueva; al abrir una directamente, Root muestra la página vacía.
 */
function onDemand<Name extends string, Module extends Record<Name, ComponentType>>(
  name: Name,
  load: () => Promise<Module>,
) {
  return lazy(async () => ({ default: (await load())[name] }));
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

export function routes(documentError: number | null = null): RouteObject[] {
  return [{ element: <Root documentError={documentError} />, children: pages }];
}

export function createAppRouter(documentError: number | null) {
  return createBrowserRouter(routes(documentError));
}
