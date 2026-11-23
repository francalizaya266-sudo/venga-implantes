import { screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { mockApi, offline, renderApp } from '@/test/app';
import { meta, page } from '@/test/fixtures';

const robots = () => document.head.querySelector<HTMLMetaElement>('meta[name="robots"]')?.content;

describe('the pages of the web', () => {
  it('opens a page with what the API says and titles the document with it', async () => {
    mockApi({
      '/api/v1/pages/privacy': page(
        { clinic: 'Clínica Vega', address: 'Jr. Apurímac 648, Juliaca', whatsapp: '912 345 678' },
        { title: 'Política de privacidad · Clínica Vega', noindex: false },
      ),
    });
    renderApp('/privacidad');

    expect(await screen.findByRole('heading', { level: 1, name: 'Política de privacidad' })).toBeInTheDocument();
    expect(screen.getByText(/Clínica Vega, con dirección en Jr\. Apurímac 648, Juliaca, es el titular/)).toBeVisible();
    expect(document.title).toBe('Política de privacidad · Clínica Vega');
    expect(robots()).toBeUndefined();
  });

  it('moves between pages asking the API only for what the next one needs', async () => {
    const calls = mockApi({
      '/api/v1/pages/privacy': page({ clinic: 'Clínica Vega', address: null, whatsapp: null }),
      '/api/v1/team': page({ doctors: [] }, { title: 'Equipo · Clínica Vega' }),
    });
    const { user } = renderApp('/privacidad');

    await screen.findByRole('heading', { level: 1, name: 'Política de privacidad' });
    await user.click(
      within(screen.getByRole('navigation', { name: 'Principal' })).getByRole('link', { name: 'Equipo' }),
    );

    expect(await screen.findByText('Pronto presentaremos a nuestro equipo.')).toBeInTheDocument();
    expect(document.title).toBe('Equipo · Clínica Vega');
    // Los datos del consultorio se pidieron una sola vez para las dos páginas.
    expect(calls.map((call) => call.url)).toEqual(['/api/v1/site', '/api/v1/pages/privacy', '/api/v1/team']);
  });

  it('says it when the address does not exist, with a way to keep going', async () => {
    mockApi();
    renderApp('/no-existe');

    expect(await screen.findByRole('heading', { level: 1, name: 'No encontramos esta página' })).toBeInTheDocument();
    const main = within(screen.getByRole('main'));
    expect(main.getByRole('link', { name: 'Ir al inicio' })).toHaveAttribute('href', '/');
    expect(main.getByRole('link', { name: 'Reservar cita' })).toHaveAttribute('href', '/reservar');
    expect(document.title).toBe('No encontramos esta página · Clínica Vega');
    expect(robots()).toBe('noindex');
  });

  it('shows the not found page when the API does not find what the address asks for', async () => {
    mockApi({
      '/api/v1/treatments/no-existe': {
        status: 404,
        body: {
          message: 'No encontramos esta página',
          meta: meta({ title: 'No encontramos esta página', noindex: true }),
        },
      },
    });
    renderApp('/tratamientos/no-existe');

    expect(await screen.findByRole('heading', { level: 1, name: 'No encontramos esta página' })).toBeInTheDocument();
    expect(robots()).toBe('noindex');
  });

  it('says the page is not for this phone when the API forbids it', async () => {
    mockApi({ '/api/v1/account/appointments/7/change': { status: 403, body: { message: 'Forbidden' } } });
    renderApp('/mi-cuenta/citas/7/cambiar');

    expect(await screen.findByRole('heading', { level: 1, name: 'No puedes ver esta página' })).toBeInTheDocument();
  });

  it('offers to try again when the server fails', async () => {
    mockApi({ '/api/v1/team': { status: 500, body: { message: 'Server Error' } } });
    renderApp('/equipo');

    expect(await screen.findByRole('heading', { level: 1, name: 'Algo falló de nuestro lado' })).toBeInTheDocument();
    const main = within(screen.getByRole('main'));
    expect(main.getByRole('button', { name: 'Volver a intentar' })).toBeInTheDocument();
    expect(main.queryByRole('link', { name: 'Ir al inicio' })).not.toBeInTheDocument();
  });

  it('asks to check the connection when the data of the page does not arrive', async () => {
    mockApi({ '/api/v1/team': offline });
    renderApp('/equipo');

    expect(await screen.findByRole('heading', { level: 1, name: 'No pudimos conectarnos' })).toBeInTheDocument();
    const main = within(screen.getByRole('main'));
    expect(main.getByText('Revisa tu conexión e inténtalo de nuevo.')).toBeInTheDocument();
    expect(main.getByRole('button', { name: 'Volver a intentar' })).toBeInTheDocument();
  });

  it('shows the error Laravel opened the web with until the patient moves to another page', async () => {
    const calls = mockApi({ '/api/v1/team': page({ doctors: [] }) });
    const { user } = renderApp('/mi-cuenta', 429);

    expect(await screen.findByRole('heading', { level: 1, name: 'Demasiados intentos seguidos' })).toBeInTheDocument();
    // La página que no se pudo abrir no se vuelve a pedir por su cuenta.
    expect(calls.map((call) => call.url)).toEqual(['/api/v1/site']);

    await user.click(
      within(screen.getByRole('navigation', { name: 'Principal' })).getByRole('link', { name: 'Equipo' }),
    );

    expect(await screen.findByText('Pronto presentaremos a nuestro equipo.')).toBeInTheDocument();
    expect(screen.queryByText('Demasiados intentos seguidos')).not.toBeInTheDocument();
  });
});
