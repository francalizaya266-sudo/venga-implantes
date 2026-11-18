import type { ReactNode } from 'react';
import { useSite } from '@/api/queries';
import type { PageKey } from '@/api/types';
import { Footer } from '@/components/layout/Footer';
import { Header } from '@/components/layout/Header';
import { PageBand } from '@/components/layout/PageBand';
import { AppLink } from '@/components/ui/AppLink';
import { Icon } from '@/components/ui/Icon';
import { Flash, useFlash } from '@/lib/flash';
import { paths } from '@/lib/paths';
import { whatsappUrl } from '@/lib/whatsapp';

interface Props {
  /**
   * La página ("servicios", "equipo"…) cuyo encabezado y cierre se editan en el panel. Con ella, la página abre
   * con su franja celeste a todo el ancho. Sin ella, la página pone su propio título y cierra con la frase general.
   */
  page?: PageKey;
  /** Páginas de contenido (inicio, tratamientos…); sin wide, columna angosta para formularios. */
  wide?: boolean;
  /** false donde el botón flotante (y, en el celular, la barra de abajo) taparía los horarios. */
  whatsappFloat?: boolean;
  /** false donde la página ya muestra la dirección, el horario y el WhatsApp, para no repetirlos en el pie. */
  footerContact?: boolean;
  /** Pie de una línea y encabezado solo con el nombre mientras se reserva, para que nada distraiga. */
  minimalFooter?: boolean;
  /** Franja de portada a todo el ancho de la pantalla, antes de la columna del contenido (el inicio). */
  hero?: ReactNode;
  children: ReactNode;
}

/** El marco de todas las páginas: encabezado, la franja de la página, el contenido, el pie y los accesos fijos. */
export function SiteLayout({
  page,
  wide = false,
  whatsappFloat = true,
  footerContact = true,
  minimalFooter = false,
  hero,
  children,
}: Props) {
  const site = useSite();
  const { message } = useFlash();

  if (!site) {
    return <main id="contenido" className="page-loading" aria-busy="true" />;
  }

  const { clinic } = site;
  const texts = page ? site.pages[page] : null;
  const whatsapp = whatsappUrl(clinic);
  const flashOnTop = hero !== undefined || texts !== null;

  return (
    <>
      <a href="#contenido" className="skip">
        Saltar al contenido
      </a>
      <Header site={site} minimal={minimalFooter} />

      <main id="contenido" className={hero !== undefined ? 'with-hero' : texts ? 'with-band' : undefined}>
        {/* Los avisos van antes de la portada: debajo de ella quedarían fuera de la pantalla. */}
        {flashOnTop && message ? (
          <div className="wrap wide">
            <Flash />
          </div>
        ) : null}
        {hero !== undefined ? hero : texts ? <PageBand texts={texts} brief={minimalFooter} /> : null}
        <div className={`wrap${wide ? ' wide' : ''}`}>
          {flashOnTop ? null : <Flash />}
          {children}
        </div>
      </main>

      <Footer
        site={site}
        closing={texts ? texts.closing : site.closing}
        contact={footerContact}
        minimal={minimalFooter}
      />

      {/* En el celular: barra fija abajo con lo que más se busca. En pantallas grandes queda el botón de WhatsApp. */}
      {whatsappFloat ? (
        <nav className="action-bar" aria-label="Acciones rápidas">
          <AppLink href={paths.booking()} className="btn">
            Reservar<span className="long">&nbsp;cita</span>
          </AppLink>
          {whatsapp ? (
            <a href={whatsapp} className="btn whatsapp" rel="noopener">
              WhatsApp
            </a>
          ) : null}
          {clinic.call_href ? (
            <a href={clinic.call_href} className="btn quiet">
              Llamar
            </a>
          ) : null}
        </nav>
      ) : null}
      {whatsapp && whatsappFloat ? (
        <a href={whatsapp} className="wa-float" rel="noopener" aria-label="Escríbenos por WhatsApp">
          <Icon name="whatsapp" />
        </a>
      ) : null}
    </>
  );
}
