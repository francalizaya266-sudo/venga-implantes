import type { AnchorHTMLAttributes } from 'react';
import { Link } from 'react-router';

/** Lo que sirve Laravel directamente (archivos, el panel): se abre con una carga normal del navegador. */
const SERVER_PATHS = /^\/(panel|storage|api|livewire|reservar\/cita)\b/;

/** La ruta dentro de la web a la que lleva un enlace, o null si sale de ella. */
function internalPath(href: string): string | null {
  if (href.startsWith('#')) {
    return null;
  }

  const url = new URL(href, window.location.origin);

  if (url.origin !== window.location.origin || SERVER_PATHS.test(url.pathname)) {
    return null;
  }

  return url.pathname + url.search + url.hash;
}

type Props = AnchorHTMLAttributes<HTMLAnchorElement> & { href: string };

/**
 * Un enlace. Dentro de la web cambia de página sin recargar; hacia afuera (WhatsApp, Google Maps, un archivo)
 * es un enlace de siempre.
 */
export function AppLink({ href, children, ...rest }: Props) {
  const to = rest.target ? null : internalPath(href);

  if (to === null) {
    return (
      <a href={href} {...rest}>
        {children}
      </a>
    );
  }

  return (
    <Link to={to} {...rest}>
      {children}
    </Link>
  );
}
