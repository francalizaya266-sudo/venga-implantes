import type { Closing as ClosingTexts, Site } from '@/api/types';
import { Closing } from '@/components/layout/Closing';
import { mainNav, moreNav } from '@/components/layout/navigation';
import { AppLink } from '@/components/ui/AppLink';
import { Icon, ToothIcon } from '@/components/ui/Icon';
import { paths } from '@/lib/paths';
import { whatsappUrl } from '@/lib/whatsapp';

interface Props {
  site: Site;
  closing: ClosingTexts;
  /** false donde la página ya muestra la dirección, el horario y el WhatsApp: ahí queda solo la lista de páginas. */
  contact: boolean;
  /** Pie de una línea mientras se reserva, para que nada distraiga hasta terminar. */
  minimal: boolean;
}

export function Footer({ site, closing, contact, minimal }: Props) {
  const { clinic } = site;
  const whatsapp = whatsappUrl(clinic);

  if (minimal) {
    const help = whatsappUrl(clinic, 'Hola, tengo una duda con mi reserva.');

    // Mientras reserva, la franja de cierre ofrece ayuda por WhatsApp; sin ella, la ayuda va en una línea del pie.
    return (
      <footer className={`site-footer minimal${closing.whatsapp ? ' with-cta' : ''}`}>
        <div className="wrap wide">
          {closing.whatsapp ? (
            <Closing clinic={clinic} closing={closing} message="Hola, tengo una duda con mi reserva." />
          ) : null}
          <p className="name">{clinic.name}</p>
          {!closing.whatsapp && help ? (
            <p>
              ¿Alguna duda?{' '}
              <a href={help} rel="noopener">
                Escríbenos por WhatsApp
              </a>
            </p>
          ) : null}
          <p>
            <AppLink href={paths.privacy}>Política de privacidad</AppLink>
          </p>
        </div>
      </footer>
    );
  }

  return (
    <footer className={`site-footer${contact ? '' : ' short'}`}>
      <div className="wrap wide">
        {/* El cierre de cada página, en una franja que monta sobre el borde del pie: su frase y su botón. */}
        <Closing clinic={clinic} closing={closing} message="Hola, tengo una pregunta." />
        <div className="footer-top">
          <p className="name">
            <span className="mark">
              <ToothIcon />
            </span>
            <span className="brand">
              {clinic.name}
              {clinic.tagline ? <small>{clinic.tagline}</small> : null}
            </span>
          </p>
        </div>
        <div className="footer-cols">
          {/* Lo justo para ubicarse: dónde queda, si está abierto y cómo escribir. El horario completo y el mapa están en Contacto. */}
          {contact ? (
            <div className="footer-contact">
              <h2>Contacto</h2>
              {clinic.address ? (
                <p>
                  {clinic.address}
                  {clinic.directions_url ? (
                    <>
                      {' · '}
                      <a href={clinic.directions_url} rel="noopener" target="_blank">
                        Cómo llegar
                      </a>
                    </>
                  ) : null}
                </p>
              ) : null}
              {clinic.hours.lines.length ? (
                <p>
                  {clinic.hours.status ? (
                    <>
                      <span className={`status${clinic.hours.is_open ? ' open' : ''}`}>{clinic.hours.status}</span>
                      {' · '}
                    </>
                  ) : null}
                  <AppLink href={paths.contact}>Ver el horario</AppLink>
                </p>
              ) : null}
              {whatsapp && clinic.whatsapp ? (
                <p>
                  WhatsApp:{' '}
                  <a href={whatsapp} rel="noopener">
                    {clinic.whatsapp.display}
                  </a>
                </p>
              ) : null}
              {clinic.phone ? (
                <p>
                  Teléfono: <a href={clinic.phone.href}>{clinic.phone.display}</a>
                </p>
              ) : null}
              {clinic.email ? (
                <p>
                  <a href={`mailto:${clinic.email}`}>{clinic.email}</a>
                </p>
              ) : null}
            </div>
          ) : null}
          <nav aria-label="Más páginas">
            <h2>El consultorio</h2>
            <ul>
              {[...mainNav(site.sections), ...moreNav(site.sections)].map((item) => (
                <li key={item.href}>
                  <AppLink href={item.href}>{item.label}</AppLink>
                </li>
              ))}
              <li>
                <AppLink href={paths.account}>Mi cuenta</AppLink>
              </li>
            </ul>
            {clinic.socials.length ? (
              <p className="socials">
                {clinic.socials.map(({ network, url }) => (
                  <a key={network} href={url} rel="noopener" target="_blank" aria-label={network} title={network}>
                    <Icon name={network.toLowerCase()} />
                  </a>
                ))}
              </p>
            ) : null}
          </nav>
        </div>
        <div className="footer-bottom">
          <p>
            © {site.year} {clinic.name}
          </p>
          <p>
            <AppLink href={paths.privacy}>Política de privacidad</AppLink>
          </p>
          {/* El doctor, la administración y la recepción entran al panel desde aquí; los buscadores no lo siguen. */}
          <p>
            <a href={paths.panel} rel="nofollow">
              Acceso del personal
            </a>
          </p>
        </div>
      </div>
    </footer>
  );
}
