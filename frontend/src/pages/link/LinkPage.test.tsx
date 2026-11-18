import { screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { mockApi, renderApp } from '@/test/app';
import { account, appointment, page } from '@/test/fixtures';

const LINK = '/api/v1/links/abc';

describe('the personal link that arrives by WhatsApp', () => {
  it('shows the appointment without changing anything: only the buttons act', async () => {
    let confirmed = false;
    const calls = mockApi({
      [LINK]: () => ({
        body: page({
          kind: 'appointment',
          title: 'Tu cita en Clínica Vega',
          appointment: appointment(confirmed ? { status: 'Confirmada', tone: 'ok', can_confirm: false } : {}),
        }),
      }),
      [`POST ${LINK}/confirm`]: () => {
        confirmed = true;

        return { body: { message: 'Listo, tu asistencia quedó confirmada.' } };
      },
    });
    const { user } = renderApp('/c/abc');

    expect(await screen.findByRole('heading', { level: 1, name: 'Tu cita en Clínica Vega' })).toBeInTheDocument();
    // La vista previa de WhatsApp también abre el enlace: abrirlo no confirma nada.
    expect(calls.every((call) => call.method === 'GET')).toBe(true);
    const main = within(screen.getByRole('main'));
    expect(main.getByRole('link', { name: 'Cambiar horario' })).toHaveAttribute('href', '/c/abc/cambiar');
    expect(main.getByRole('link', { name: 'Mi cuenta' })).toHaveAttribute('href', '/mi-cuenta');
    // Aquí los botones son los de la cita: sin la barra de acciones.
    expect(screen.queryByRole('navigation', { name: 'Acciones rápidas' })).not.toBeInTheDocument();

    await user.click(main.getByRole('button', { name: 'Confirmar asistencia' }));

    expect(await screen.findByRole('status')).toHaveTextContent('Listo, tu asistencia quedó confirmada.');
    expect(main.getByText('Confirmada')).toBeInTheDocument();
    expect(main.queryByRole('button', { name: 'Confirmar asistencia' })).not.toBeInTheDocument();
  });

  it('cancels the appointment from the link', async () => {
    const calls = mockApi({
      [LINK]: page({ kind: 'appointment', title: 'Tu cita en Clínica Vega', appointment: appointment() }),
      [`POST ${LINK}/cancel`]: { message: 'Tu cita quedó cancelada.' },
    });
    const { user } = renderApp('/c/abc');

    await user.click(await screen.findByText('Cancelar cita'));
    await user.click(screen.getByRole('button', { name: 'Sí, cancelar la cita' }));

    expect(await screen.findByRole('status')).toHaveTextContent('Tu cita quedó cancelada.');
    expect(calls.find((call) => call.method === 'POST')).toMatchObject({ url: `${LINK}/cancel`, body: { reason: '' } });
  });

  it('remembers this phone and enters Mi cuenta only when the patient touches the button', async () => {
    const calls = mockApi({
      [LINK]: page({ kind: 'access' }),
      [`POST ${LINK}/enter`]: { message: 'Listo, este celular quedó recordado.' },
      '/api/v1/account': page(account()),
    });
    const { user, router } = renderApp('/c/abc');

    await user.click(await screen.findByRole('button', { name: 'Entrar a Mi cuenta' }));

    expect(await screen.findByRole('heading', { level: 2, name: 'Hola, Rosa' })).toBeInTheDocument();
    expect(screen.getByRole('status')).toHaveTextContent('Listo, este celular quedó recordado.');
    expect(router.state.location.pathname).toBe('/mi-cuenta');
    expect(calls.filter((call) => call.method === 'POST')).toMatchObject([{ url: `${LINK}/enter` }]);
  });

  it('says it when the link is no longer active', async () => {
    mockApi({ [LINK]: page({ kind: 'expired' }) });
    renderApp('/c/abc');

    expect(await screen.findByRole('heading', { level: 1, name: 'Este enlace ya no está activo' })).toBeInTheDocument();
    expect(within(screen.getByRole('main')).getByRole('link', { name: 'Mi cuenta' })).toHaveAttribute(
      'href',
      '/mi-cuenta',
    );
  });

  it('shows the not found page for a link that never existed', async () => {
    mockApi({ [LINK]: { status: 404, body: { message: 'No encontramos esta página' } } });
    renderApp('/c/abc');

    expect(await screen.findByRole('heading', { level: 1, name: 'No encontramos esta página' })).toBeInTheDocument();
  });
});
