import { useEffect, useRef } from 'react';
import { useLocation } from 'react-router';
import type { Site } from '@/api/types';
import { isAccount, isBooking, mainNav, moreNav, type NavItem } from '@/components/layout/navigation';
import { AppLink } from '@/components/ui/AppLink';
import { Icon, ToothIcon } from '@/components/ui/Icon';
import { paths } from '@/lib/paths';

function NavLink({ item, pathname, withIcon = false }: { item: NavItem; pathname: string; withIcon?: boolean }) {
  return (
    <AppLink href={item.href} aria-current={item.isCurrent(pathname) ? 'page' : undefined}>
      {withIcon && item.icon ? <Icon name={item.icon} /> : null}
      {item.label}
    </AppLink>
  );
}

interface Props {
  site: Site;
  /** Mientras se reserva, el encabezado queda solo con el nombre, que vuelve al inicio. */
  minimal: boolean;
}

export function Header({ site, minimal }: Props) {
  const { pathname } = useLocation();
  const header = useRef<HTMLElement>(null);
  const { clinic } = site;
  const nav = mainNav(site.sections);
  const more = moreNav(site.sections);
  const accountCurrent = isAccount(pathname) ? 'page' : undefined;

  // "Menú" y "Más" se cierran al tocar fuera, con Escape y al pasar de página.
  useEffect(() => {
    const close = (except: Node | null) => {
      header.current?.querySelectorAll<HTMLDetailsElement>('details[open]').forEach((details) => {
        if (!except || !details.contains(except)) {
          details.open = false;
        }
      });
    };
    const onClick = (event: MouseEvent) => close(event.target as Node);
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        close(null);
      }
    };

    close(null);
    document.addEventListener('click', onClick);
    document.addEventListener('keydown', onKey);

    return () => {
      document.removeEventListener('click', onClick);
      document.removeEventListener('keydown', onKey);
    };
  }, [pathname]);

  return (
    // Con una página extra a la vista el menú necesita más ancho (site.css): hasta entonces queda el botón "Menú".
    <header ref={header} className={`site-header${more.length ? ' many' : ''}`}>
      <div className="wrap">
        {clinic.logo_url ? (
          <AppLink href={paths.home} className="wordmark with-logo">
            <img src={clinic.logo_url} alt={clinic.name} className="logo" />
          </AppLink>
        ) : (
          <AppLink href={paths.home} className="wordmark">
            <span className="mark">
              <ToothIcon />
            </span>
            <span className="brand">
              {clinic.name}
              {clinic.tagline ? <small>{clinic.tagline}</small> : null}
            </span>
          </AppLink>
        )}
        {minimal ? null : (
          <>
            <nav className="site-nav" aria-label="Principal">
              {nav.map((item) => (
                <NavLink key={item.href} item={item} pathname={pathname} withIcon />
              ))}
              {more.length === 1 && more[0] ? (
                <NavLink item={more[0]} pathname={pathname} />
              ) : more.length > 1 ? (
                <details className="more-nav">
                  <summary aria-current={more.some((item) => item.isCurrent(pathname)) ? 'page' : undefined}>
                    Más
                  </summary>
                  <div className="more-panel">
                    {more.map((item) => (
                      <NavLink key={item.href} item={item} pathname={pathname} />
                    ))}
                  </div>
                </details>
              ) : null}
              <AppLink href={paths.account} aria-current={accountCurrent}>
                Mi cuenta
              </AppLink>
            </nav>
            {isBooking(pathname) ? null : (
              <AppLink href={paths.booking()} className="btn small">
                <Icon name="calendar" />
                Reservar<span className="long">&nbsp;cita</span>
              </AppLink>
            )}
            <details className="menu">
              <summary aria-label="Menú">Menú</summary>
              <nav className="menu-panel" aria-label="Menú">
                {[...nav, ...more].map((item) => (
                  <NavLink key={item.href} item={item} pathname={pathname} />
                ))}
                <AppLink href={paths.account} aria-current={accountCurrent}>
                  Mi cuenta
                </AppLink>
              </nav>
            </details>
          </>
        )}
      </div>
    </header>
  );
}
