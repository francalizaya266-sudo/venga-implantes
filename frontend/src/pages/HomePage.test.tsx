import { screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import type { HomeData } from '@/api/types';
import { mockApi, renderApp } from '@/test/app';
import { appointment, homeData, page, treatment } from '@/test/fixtures';

const HOME = '/api/v1/home';

function openHome(home: Partial<HomeData> = {}, answers: Parameters<typeof mockApi>[0] = {}) {
  const calls = mockApi({ [HOME]: page(homeData(home)), ...answers });

  return { calls, ...renderApp('/') };
}

const title = () => screen.findByRole('heading', { level: 1, name: 'Atención dental sin esperas' });

describe('HomePage', () => {
  it('opens with the phrase of the clinic, what calms before booking and the services', async () => {
    openHome({
      treatments: [treatment(), treatment({ id: 4, slug: 'ortodoncia', name: 'Ortodoncia', price: null })],
      treatments_count: 12,
    });

    await title();
    expect(screen.getByText('Horarios libres de verdad')).toBeInTheDocument();
    expect(screen.getByText('Precios a la vista')).toBeInTheDocument();

    const services = within(screen.getByRole('region', { name: 'Nuestros servicios' }));
    expect(services.getByRole('link', { name: /Limpieza dental.*Desde S\/ 120/ })).toHaveAttribute(
      'href',
      '/tratamientos/limpieza-dental',
    );
    expect(services.getByRole('link', { name: /Ortodoncia.*Precio en la evaluación/ })).toBeInTheDocument();
    expect(services.getByRole('link', { name: 'Ver los 12 servicios' })).toHaveAttribute('href', '/tratamientos');
    // El inicio ya muestra dónde queda y el horario: el pie no los repite.
    expect(within(screen.getByRole('contentinfo')).queryByText('Abierto ahora')).not.toBeInTheDocument();
  });

  it('promises nothing about booking when the clinic does not take bookings on the web', async () => {
    openHome({ can_book: false, shows_prices: false });

    await title();
    expect(screen.queryByText('Horarios libres de verdad')).not.toBeInTheDocument();
    expect(screen.queryByText('Precios a la vista')).not.toBeInTheDocument();
  });

  it('offers the next free times one touch away from the last step of the booking', async () => {
    openHome({
      next_free: {
        title: 'Próximos horarios libres',
        date: '2026-11-04',
        day: 'Miércoles 4 de noviembre',
        times: [
          { time: '09:00', label: '9:00 a. m.' },
          { time: '15:00', label: '3:00 p. m.' },
        ],
        treatment: { slug: 'evaluacion', name: 'Evaluación', duration_minutes: 30 },
        promo: 'sonrisa-de-verano',
      },
    });

    expect(await screen.findByRole('link', { name: '3:00 p. m.' })).toHaveAttribute(
      'href',
      '/reservar?tratamiento=evaluacion&fecha=2026-11-04&hora=15%3A00&promo=sonrisa-de-verano',
    );
    expect(screen.getByRole('link', { name: 'Ver otros días y horarios' })).toHaveAttribute(
      'href',
      '/reservar?tratamiento=evaluacion&promo=sonrisa-de-verano',
    );
  });

  it('shows first what is theirs to whoever returns from the remembered phone', async () => {
    const { calls, user } = openHome(
      {
        welcome: {
          name: 'Rosa',
          appointments: [appointment()],
          more: 2,
          sessions: [
            {
              patient_id: 22,
              kicker: 'Ortodoncia de Luis',
              title: 'Va en la sesión 3 de 10',
              done: 3,
              total: 10,
              note: 'Toca la siguiente este mes.',
              requested: false,
              button: 'Pedir la próxima sesión',
            },
          ],
          checkups: [
            {
              patient_id: 21,
              kicker: 'Control de Rosa',
              title: 'Ya toca tu limpieza',
              note: 'Han pasado 6 meses.',
              url: '/reservar?tratamiento=limpieza-dental&para=21',
              button: 'Reservar mi control',
            },
          ],
        },
      },
      { 'POST /api/v1/account/patients/22/next-session': { message: 'Listo. Te escribiremos con el horario.' } },
    );

    const welcome = within(await screen.findByRole('region', { name: 'Hola, Rosa' }));
    expect(welcome.getByText('Miércoles 4 de noviembre, 10:00 a. m.')).toBeInTheDocument();
    expect(welcome.getByRole('link', { name: 'Cambiar horario' })).toHaveAttribute(
      'href',
      '/mi-cuenta/citas/7/cambiar',
    );
    // Cancelar queda en Mi cuenta: el inicio solo lleva lo que sigue.
    expect(welcome.queryByText('Cancelar cita')).not.toBeInTheDocument();
    expect(welcome.getByRole('img', { name: '3 de 10 sesiones hechas' })).toBeInTheDocument();
    expect(welcome.getByRole('link', { name: 'Reservar mi control' })).toHaveAttribute(
      'href',
      '/reservar?tratamiento=limpieza-dental&para=21',
    );
    expect(welcome.getByRole('link', { name: 'Ver 2 citas más' })).toHaveAttribute('href', '/mi-cuenta');

    await user.click(welcome.getByRole('button', { name: 'Pedir la próxima sesión' }));

    expect(await screen.findByRole('status')).toHaveTextContent('Listo. Te escribiremos con el horario.');
    expect(calls.filter((call) => call.method === 'POST')).toMatchObject([
      { url: '/api/v1/account/patients/22/next-session' },
    ]);
  });

  it('lets someone else using the phone leave, and takes them to ask for their own link', async () => {
    const { user, router } = openHome(
      { welcome: { name: 'Rosa', appointments: [], more: 0, sessions: [], checkups: [] } },
      {
        'POST /api/v1/account/logout': { message: 'Este celular ya no está recordado.' },
        '/api/v1/account': page({ guest: true }),
      },
    );

    const welcome = within(await screen.findByRole('region', { name: 'Hola, Rosa' }));
    expect(welcome.getByText('No tienes citas por delante. Cuando quieras, reserva aquí abajo.')).toBeInTheDocument();
    await user.click(welcome.getByRole('button', { name: 'Salir en este celular' }));

    expect(await screen.findByRole('status')).toHaveTextContent('Este celular ya no está recordado.');
    expect(router.state.location.pathname).toBe('/mi-cuenta');
    expect(await screen.findByRole('heading', { level: 2, name: 'Pedir mi enlace' })).toBeInTheDocument();
  });

  it('adds the sections the clinic has content for', async () => {
    openHome({
      campaigns: [
        {
          slug: 'sonrisa-de-verano',
          name: 'Sonrisa de verano',
          summary: 'Blanqueamiento con 20% de descuento.',
          image_url: null,
          until: 'Hasta el 30 de noviembre',
          discount: '20% de descuento',
          discount_beside_name: '20% de descuento',
        },
      ],
      specialty: treatment({ id: 9, slug: 'implantes', name: 'Implantes dentales', art: 'implante' }),
      faqs: [{ id: 1, question: '¿Atienden los sábados?', answer_html: '<p>Sí, hasta la 1:00 p. m.</p>' }],
    });

    await title();
    expect(screen.getByRole('link', { name: 'Conocer el tratamiento' })).toHaveAttribute(
      'href',
      '/tratamientos/implantes',
    );
    expect(screen.getByText('¿Atienden los sábados?')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Ver todas las preguntas' })).toHaveAttribute(
      'href',
      '/preguntas-frecuentes',
    );
    expect(screen.getByText('Sonrisa de verano')).toBeInTheDocument();
  });
});
