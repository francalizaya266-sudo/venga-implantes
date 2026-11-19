import { screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import type { Site } from '@/api/types';
import { SiteLayout } from '@/components/layout/SiteLayout';
import { mockApi, renderPiece } from '@/test/app';
import { site } from '@/test/fixtures';

const WHATSAPP = 'https://wa.me/51912345678?text=';
/** "Reservar cita" con "cita" en su propio elemento: en pantallas angostas el botón dice solo "Reservar". */
const BOOK = /^Reservar\s?cita$/;
/** Igual "Volver al inicio": en pantallas angostas dice solo "Volver". */
const BACK = /^Volver\s?al inicio$/;

function withSite(changes: Partial<Site>): void {
  mockApi({ '/api/v1/site': { data: site(changes) } });
}

const mainNav = () => within(screen.getByRole('navigation', { name: 'Principal' }));
const mainLinks = () =>
  mainNav()
    .getAllByRole('link')
    .map((link) => link.textContent);

describe('SiteLayout', () => {
  it('frames the page with the header, the footer and the quick actions', async () => {
    mockApi();
    renderPiece(
      <SiteLayout>
        <p>El contenido</p>
      </SiteLayout>,
    );

    expect(await screen.findByText('El contenido')).toBeInTheDocument();
    expect(mainLinks()).toEqual(['Inicio', 'Servicios', 'Equipo', 'Contacto', 'Mi cuenta']);

    const actions = within(screen.getByRole('navigation', { name: 'Acciones rápidas' }));
    expect(actions.getByRole('link', { name: BOOK })).toHaveAttribute('href', '/reservar');
    expect(within(screen.getByRole('banner')).getByRole('link', { name: BOOK })).toHaveAttribute('href', '/reservar');
    expect(actions.getByRole('link', { name: 'WhatsApp' })).toHaveAttribute(
      'href',
      WHATSAPP + encodeURIComponent('Hola, quisiera información.'),
    );
    expect(actions.getByRole('link', { name: 'Llamar' })).toHaveAttribute('href', 'tel:+51912345678');
    expect(screen.getByRole('link', { name: 'Escríbenos por WhatsApp' })).toBeInTheDocument();

    const footer = within(screen.getByRole('contentinfo'));
    expect(footer.getByText('Tu salud bucal, en buenas manos')).toBeInTheDocument();
    expect(footer.getByText('Jr. Apurímac 648, Juliaca', { exact: false })).toBeInTheDocument();
    expect(footer.getByText('Abierto ahora')).toBeInTheDocument();
    expect(footer.getByText('© 2026 Clínica Vega')).toBeInTheDocument();
  });

  it('leaves out the quick actions where they would cover the times', async () => {
    mockApi();
    renderPiece(
      <SiteLayout whatsappFloat={false}>
        <p>Los horarios</p>
      </SiteLayout>,
    );

    expect(await screen.findByText('Los horarios')).toBeInTheDocument();
    expect(screen.queryByRole('navigation', { name: 'Acciones rápidas' })).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Escríbenos por WhatsApp' })).not.toBeInTheDocument();
  });

  it('offers neither WhatsApp nor a call when the clinic has not set them', async () => {
    withSite({ clinic: { ...site().clinic, whatsapp: null, call_href: null } });
    renderPiece(
      <SiteLayout>
        <p>El contenido</p>
      </SiteLayout>,
    );

    const actions = within(await screen.findByRole('navigation', { name: 'Acciones rápidas' }));
    expect(actions.getAllByRole('link').map((link) => link.textContent)).toEqual(['Reservar cita']);
    expect(screen.queryByRole('link', { name: 'Escríbenos por WhatsApp' })).not.toBeInTheDocument();
  });

  it('opens with the heading and closes with the phrase of the page, as edited in the panel', async () => {
    const pages = site().pages;
    withSite({
      pages: {
        ...pages,
        servicios: {
          ...pages.servicios,
          kicker: 'Tu sonrisa, nuestra prioridad',
          title: ['Nuestros ', 'servicios', ''],
          lead: 'Elige el tuyo y reserva.',
          closing: { title: '¿Empezamos?', text: 'Reserva en un minuto.', whatsapp: false },
        },
      },
    });
    renderPiece(
      <SiteLayout page="servicios" wide>
        <p>Los tratamientos</p>
      </SiteLayout>,
    );

    expect(await screen.findByRole('heading', { level: 1, name: 'Nuestros servicios' })).toBeInTheDocument();
    expect(screen.getByText('Tu sonrisa, nuestra prioridad')).toBeInTheDocument();
    expect(screen.getByText('Elige el tuyo y reserva.')).toBeInTheDocument();

    const footer = within(screen.getByRole('contentinfo'));
    expect(footer.getByText('¿Empezamos?')).toBeInTheDocument();
    expect(footer.queryByText('Tu salud bucal, en buenas manos')).not.toBeInTheDocument();
    expect(footer.getByRole('link', { name: 'Reservar cita' })).toHaveAttribute('href', '/reservar');
  });

  it('closes with the WhatsApp button where the panel says so', async () => {
    withSite({ closing: { title: '¿No encuentras tu duda?', text: null, whatsapp: true } });
    renderPiece(
      <SiteLayout>
        <p>El contenido</p>
      </SiteLayout>,
    );

    await screen.findByText('El contenido');
    expect(
      within(screen.getByRole('contentinfo')).getByRole('link', { name: 'Escribir por WhatsApp' }),
    ).toHaveAttribute('href', WHATSAPP + encodeURIComponent('Hola, tengo una pregunta.'));
  });

  it('adds Promociones to the menu only while there is one', async () => {
    withSite({ sections: { promotions: true, cases: false, reviews: false, faq: false } });
    renderPiece(
      <SiteLayout>
        <p>El contenido</p>
      </SiteLayout>,
    );

    await screen.findByText('El contenido');
    expect(mainLinks()).toEqual(['Inicio', 'Servicios', 'Equipo', 'Promociones', 'Contacto', 'Mi cuenta']);
  });

  it('puts a single extra page in sight', async () => {
    withSite({ sections: { promotions: false, cases: false, reviews: true, faq: false } });
    renderPiece(
      <SiteLayout>
        <p>El contenido</p>
      </SiteLayout>,
    );

    await screen.findByText('El contenido');
    expect(mainLinks()).toEqual(['Inicio', 'Servicios', 'Equipo', 'Contacto', 'Opiniones', 'Mi cuenta']);
    expect(mainNav().queryByText('Más')).not.toBeInTheDocument();
  });

  it('gathers several extra pages under "Más"', async () => {
    withSite({ sections: { promotions: false, cases: true, reviews: true, faq: true } });
    renderPiece(
      <SiteLayout>
        <p>El contenido</p>
      </SiteLayout>,
      '/opiniones',
    );

    await screen.findByText('El contenido');
    const more = mainNav().getByText('Más');
    // La página abierta es una de las de "Más": el menú lo marca.
    expect(more).toHaveAttribute('aria-current', 'page');
    expect(
      within(more.closest('details')!)
        .getAllByRole('link', { hidden: true })
        .map((link) => link.textContent),
    ).toEqual(['Antes y después', 'Opiniones', 'Preguntas frecuentes']);
    expect(
      within(screen.getByRole('navigation', { name: 'Más páginas' }))
        .getAllByRole('link')
        .map((link) => link.textContent),
    ).toEqual([
      'Inicio',
      'Servicios',
      'Equipo',
      'Contacto',
      'Antes y después',
      'Opiniones',
      'Preguntas frecuentes',
      'Mi cuenta',
    ]);
  });

  it.each([
    ['/tratamientos/limpieza-dental', 'Servicios'],
    ['/equipo', 'Equipo'],
    ['/mi-cuenta/citas/7/cambiar', 'Mi cuenta'],
    ['/', 'Inicio'],
  ])('marks in the menu the page that is open (%s)', async (path, current) => {
    mockApi();
    renderPiece(
      <SiteLayout>
        <p>El contenido</p>
      </SiteLayout>,
      path,
    );

    await screen.findByText('El contenido');
    expect(
      mainNav()
        .getAllByRole('link')
        .filter((link) => link.getAttribute('aria-current') === 'page')
        .map((link) => link.textContent),
    ).toEqual([current]);
  });

  it('keeps only the name and one line of footer while booking, so nothing distracts', async () => {
    mockApi();
    renderPiece(
      <SiteLayout whatsappFloat={false} minimalFooter>
        <p>Los pasos</p>
      </SiteLayout>,
      '/reservar',
    );

    expect(await screen.findByText('Los pasos')).toBeInTheDocument();
    expect(screen.queryByRole('navigation', { name: 'Principal' })).not.toBeInTheDocument();
    // El nombre y una salida a la vista: casi nadie sabe que el nombre también vuelve al inicio.
    const banner = within(screen.getByRole('banner'));
    expect(banner.getAllByRole('link')).toHaveLength(2);
    expect(banner.getByRole('link', { name: BACK })).toHaveAttribute('href', '/');

    const footer = within(screen.getByRole('contentinfo'));
    expect(footer.getByRole('link', { name: 'Escríbenos por WhatsApp' })).toHaveAttribute(
      'href',
      WHATSAPP + encodeURIComponent('Hola, tengo una duda con mi reserva.'),
    );
    expect(footer.getByRole('link', { name: 'Política de privacidad' })).toHaveAttribute('href', '/privacidad');
    expect(footer.queryByText('Tu salud bucal, en buenas manos')).not.toBeInTheDocument();
  });

  it('leaves only the name in the header of the other short pages, which have their own way back', async () => {
    mockApi();
    renderPiece(
      <SiteLayout whatsappFloat={false} minimalFooter>
        <p>Antes de tu cita</p>
      </SiteLayout>,
      '/mi-cuenta/pacientes/7/salud',
    );

    await screen.findByText('Antes de tu cita');
    expect(within(screen.getByRole('banner')).getAllByRole('link')).toHaveLength(1);
  });

  it('does not repeat the booking button in the header of the booking page', async () => {
    mockApi();
    renderPiece(
      <SiteLayout>
        <p>El contenido</p>
      </SiteLayout>,
      '/reservar',
    );

    await screen.findByText('El contenido');
    expect(within(screen.getByRole('banner')).queryByRole('link', { name: BOOK })).not.toBeInTheDocument();
  });

  it('leaves the address and the opening hours out of the footer where the page already shows them', async () => {
    mockApi();
    renderPiece(
      <SiteLayout footerContact={false}>
        <p>El mapa y el horario</p>
      </SiteLayout>,
    );

    await screen.findByText('El mapa y el horario');
    const footer = within(screen.getByRole('contentinfo'));
    expect(footer.queryByText('Abierto ahora')).not.toBeInTheDocument();
    expect(footer.getByRole('navigation', { name: 'Más páginas' })).toBeInTheDocument();
  });
});
