import { createBrowserRouter, type RouteObject } from 'react-router';
import { Root } from '@/app/Root';
import { CasesPage } from '@/pages/CasesPage';
import { ContactPage } from '@/pages/ContactPage';
import { ErrorPage } from '@/pages/ErrorPage';
import { FaqPage } from '@/pages/FaqPage';
import { HomePage } from '@/pages/HomePage';
import { PrivacyPage } from '@/pages/PrivacyPage';
import { PromotionPage } from '@/pages/PromotionPage';
import { PromotionsPage } from '@/pages/PromotionsPage';
import { ReviewsPage } from '@/pages/ReviewsPage';
import { TeamPage } from '@/pages/TeamPage';
import { TreatmentPage } from '@/pages/TreatmentPage';
import { TreatmentsPage } from '@/pages/TreatmentsPage';

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
  { path: '*', element: <ErrorPage status={404} /> },
];

export function routes(documentError: number | null = null): RouteObject[] {
  return [{ element: <Root documentError={documentError} />, children: pages }];
}

export function createAppRouter(documentError: number | null) {
  return createBrowserRouter(routes(documentError));
}
