import type { Site } from '@/api/types';
import { paths } from '@/lib/paths';

export interface NavItem {
  label: string;
  href: string;
  /** Si la página abierta es esta (o una de las suyas). */
  isCurrent: (pathname: string) => boolean;
  icon?: string;
}

const exact = (path: string) => (pathname: string) => pathname === path;
const under = (path: string) => (pathname: string) => pathname === path || pathname.startsWith(`${path}/`);

/** El menú principal: las páginas que siempre están y Promociones cuando hay alguna vigente. */
export function mainNav(sections: Site['sections']): NavItem[] {
  return [
    { label: 'Inicio', href: paths.home, isCurrent: exact(paths.home), icon: 'home' },
    { label: 'Servicios', href: paths.treatments, isCurrent: under(paths.treatments) },
    { label: 'Equipo', href: paths.team, isCurrent: exact(paths.team) },
    ...(sections.promotions
      ? [{ label: 'Promociones', href: paths.promotions, isCurrent: under(paths.promotions) }]
      : []),
    { label: 'Contacto', href: paths.contact, isCurrent: exact(paths.contact) },
  ];
}

/** Las demás páginas con contenido: una sola va a la vista; varias, bajo "Más". */
export function moreNav(sections: Site['sections']): NavItem[] {
  return [
    ...(sections.cases ? [{ label: 'Antes y después', href: paths.cases, isCurrent: exact(paths.cases) }] : []),
    ...(sections.reviews ? [{ label: 'Opiniones', href: paths.reviews, isCurrent: exact(paths.reviews) }] : []),
    ...(sections.faq ? [{ label: 'Preguntas frecuentes', href: paths.faq, isCurrent: exact(paths.faq) }] : []),
  ];
}

export const isAccount = under(paths.account);
export const isBooking = exact('/reservar');
