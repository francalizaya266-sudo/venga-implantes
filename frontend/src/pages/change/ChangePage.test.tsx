import { screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import type { ChangeData } from '@/api/types';
import { mockApi, offline, renderApp } from '@/test/app';
import { account, appointment, changeData, page, slot } from '@/test/fixtures';

const PATH = '/mi-cuenta/citas/7/cambiar';
const CHANGE = '/api/v1/account/appointments/7/change';
const NOW_FULL = 'Ya elegiste tus 2 horarios. Quita uno para elegir otro.';

/** La API de cambiar una cita de Mi cuenta; cada prueba cambia lo que le importa. */
function changeApi(change: Partial<ChangeData> = {}, answers: Parameters<typeof mockApi>[0] = {}) {
  return mockApi({
    [CHANGE]: page(changeData(change)),
    '/api/v1/account': page(account()),
    ...answers,
  });
}

const time = (label: string) => screen.getByRole('button', { name: label });
const posted = (calls: ReturnType<typeof mockApi>) => calls.filter((call) => call.method === 'POST');

describe('changing the time of an appointment', () => {
  it('changes it at once to the time the patient touches', async () => {
    const calls = changeApi(
      {},
      { [`POST ${CHANGE}`]: { message: 'Listo, tu cita quedó para el jueves 5 de noviembre a las 11:30 a. m.' } },
    );
    const { user, router } = renderApp(PATH);

    expect(await screen.findByRole('heading', { level: 1, name: 'Cambiar el horario' })).toBeInTheDocument();
    expect(screen.getByText('Ahora: Miércoles 4 de noviembre, 10:00 a. m.')).toBeInTheDocument();
    expect(screen.getByText('Elige el nuevo día y hora. El cambio se hace al instante.')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Cambiar a este horario' })).not.toBeInTheDocument();

    // Al instante se elige un solo horario: el segundo reemplaza al primero.
    await user.click(time('9:00 a. m.'));
    await user.click(time('11:30 a. m.'));

    const chosen = within(screen.getByRole('heading', { level: 2, name: 'Nuevo horario' }).closest('.panel')!);
    expect(chosen.getByText('Jueves 5 de noviembre, 11:30 a. m.')).toBeInTheDocument();
    expect(chosen.queryByText('Jueves 5 de noviembre, 9:00 a. m.')).not.toBeInTheDocument();
    expect(chosen.queryByLabelText('Motivo (opcional)')).not.toBeInTheDocument();
    await user.click(chosen.getByRole('button', { name: 'Cambiar a este horario' }));

    expect(await screen.findByRole('status')).toHaveTextContent(
      'Listo, tu cita quedó para el jueves 5 de noviembre a las 11:30 a. m.',
    );
    expect(router.state.location.pathname).toBe('/mi-cuenta');
    expect(posted(calls)).toMatchObject([{ url: CHANGE, body: { chosen: ['2026-11-05 11:30'], reason: null } }]);
  });

  it('proposes a few times when the clinic has to approve the change', async () => {
    const calls = changeApi(
      { automatic: false, max_options: 2 },
      {
        [`${CHANGE}/slots?date=2026-11-06`]: {
          data: { date: '2026-11-06', day: 'Viernes 6 de noviembre', slots: [slot('10:00', '10:00 a. m.')] },
        },
        [`POST ${CHANGE}`]: { message: 'Recibimos tu pedido. Te avisaremos por WhatsApp cuál quedó.' },
      },
    );
    const { user } = renderApp(PATH);

    expect(
      await screen.findByText(
        'Falta poco para tu cita, así que el consultorio confirma el cambio. Elige hasta 2 horarios que te sirvan y te avisaremos cuál quedó.',
      ),
    ).toBeInTheDocument();

    await user.click(time('3:00 p. m.'));
    expect(screen.getByRole('status')).toHaveTextContent('Elegiste 1 de 2 horarios.');
    expect(screen.getByRole('button', { name: 'Enviar mi pedido' })).toBeInTheDocument();

    // Tocar de nuevo un horario elegido lo quita.
    await user.click(time('3:00 p. m.'));
    expect(screen.queryByRole('heading', { level: 2, name: 'Tus opciones' })).not.toBeInTheDocument();

    await user.click(time('3:00 p. m.'));
    await user.click(screen.getByRole('button', { name: 'Viernes 6 de noviembre' }));
    await user.click(await screen.findByRole('button', { name: '10:00 a. m.' }));
    expect(screen.getByRole('status')).toHaveTextContent(NOW_FULL);

    // Con las dos opciones puestas no se puede tocar otro horario hasta quitar una.
    await user.click(screen.getByRole('button', { name: 'Jueves 5 de noviembre' }));
    expect(time('9:00 a. m.')).toBeDisabled();
    expect(time('3:00 p. m.')).toBeEnabled();
    const options = within(screen.getByRole('heading', { level: 2, name: 'Tus opciones' }).closest('.panel')!);
    expect(options.getAllByRole('listitem').map((item) => item.querySelector('strong')?.textContent)).toEqual([
      'Jueves 5 de noviembre, 3:00 p. m.',
      'Viernes 6 de noviembre, 10:00 a. m.',
    ]);

    await user.click(options.getAllByRole('button', { name: 'Quitar' })[0]!);
    expect(screen.getByRole('status')).toHaveTextContent('Elegiste 1 de 2 horarios.');
    await user.click(time('9:00 a. m.'));
    await user.type(options.getByLabelText('Motivo (opcional)'), 'Salgo tarde del trabajo');
    await user.click(options.getByRole('button', { name: 'Enviar mis 2 opciones' }));

    expect(await screen.findByRole('status')).toHaveTextContent(
      'Recibimos tu pedido. Te avisaremos por WhatsApp cuál quedó.',
    );
    expect(posted(calls)).toMatchObject([
      { url: CHANGE, body: { chosen: ['2026-11-05 09:00', '2026-11-06 10:00'], reason: 'Salgo tarde del trabajo' } },
    ]);
  });

  it('starts again with the times that are left when the chosen one was just taken', async () => {
    const calls = changeApi(
      {},
      {
        [`POST ${CHANGE}`]: { status: 422, body: { message: 'Ese horario se acaba de ocupar. Por favor elige otro.' } },
      },
    );
    const { user, router } = renderApp(PATH);

    await user.click(await screen.findByRole('button', { name: '9:00 a. m.' }));
    await user.click(screen.getByRole('button', { name: 'Cambiar a este horario' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Ese horario se acaba de ocupar. Por favor elige otro.');
    expect(screen.queryByRole('heading', { level: 2, name: 'Nuevo horario' })).not.toBeInTheDocument();
    expect(time('9:00 a. m.')).toHaveAttribute('aria-pressed', 'false');
    expect(router.state.location.pathname).toBe(PATH);
    // Los horarios se piden de nuevo: el que se ocupó ya no debe ofrecerse.
    await waitFor(() => expect(calls.filter((call) => call.method === 'GET' && call.url === CHANGE)).toHaveLength(2));

    // Al elegir otro horario, el aviso ya no viene al caso.
    await user.click(time('11:30 a. m.'));
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  it('keeps the chosen time when the request does not arrive', async () => {
    changeApi({}, { [`POST ${CHANGE}`]: offline });
    const { user } = renderApp(PATH);

    await user.click(await screen.findByRole('button', { name: '9:00 a. m.' }));
    await user.click(screen.getByRole('button', { name: 'Cambiar a este horario' }));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'No pudimos conectarnos. Revisa tu conexión e inténtalo de nuevo.',
    );
    expect(time('9:00 a. m.')).toHaveAttribute('aria-pressed', 'true');
    expect(screen.getByRole('button', { name: 'Cambiar a este horario' })).toBeEnabled();
  });

  it('reminds the request already sent and why the clinic confirms a session with a set date', async () => {
    changeApi({
      automatic: false,
      fixed_date: true,
      pending_request: 'jueves 5 de noviembre a las 9:00 a. m. o viernes 6 de noviembre a las 3:00 p. m.',
    });
    renderApp(PATH);

    expect(
      await screen.findByText(/^Esta sesión tiene fecha coordinada con tu doctor, así que el consultorio confirma/),
    ).toBeInTheDocument();
    expect(
      screen.getByText(
        'Ya pediste cambiarla a: jueves 5 de noviembre a las 9:00 a. m. o viernes 6 de noviembre a las 3:00 p. m. Si envías otro pedido, reemplaza al anterior.',
      ),
    ).toBeInTheDocument();
  });

  it('sends to WhatsApp when the appointment can no longer be changed here', async () => {
    changeApi({ can_change: false });
    renderApp(PATH);

    expect(
      await screen.findByText('Esta cita ya no se puede cambiar desde aquí. Escríbenos por WhatsApp y te ayudamos.'),
    ).toBeInTheDocument();
    expect(screen.queryByRole('group', { name: 'Días con horarios libres' })).not.toBeInTheDocument();
  });

  it('says it when the doctor has no free times', async () => {
    changeApi({ days: [], date: null, day: null, slots: [] });
    renderApp(PATH);

    expect(
      await screen.findByText(/No encontramos horarios libres en las próximas semanas con este doctor\./),
    ).toBeInTheDocument();
  });

  it('from the WhatsApp link, changes the appointment and returns to it', async () => {
    const link = '/api/v1/links/abc/change';
    const calls = mockApi({
      [link]: page(changeData()),
      [`POST ${link}`]: { message: 'Listo, tu cita quedó para el jueves 5 de noviembre a las 9:00 a. m.' },
      '/api/v1/links/abc': page({ kind: 'appointment', title: 'Tu cita', appointment: appointment() }),
    });
    const { user, router } = renderApp('/c/abc/cambiar');

    expect(await screen.findByRole('link', { name: '← Volver a mi cita' })).toHaveAttribute('href', '/c/abc');
    // Aquí lo que importa son los horarios: sin la barra de acciones que los taparía.
    expect(screen.queryByRole('navigation', { name: 'Acciones rápidas' })).not.toBeInTheDocument();
    await user.click(time('9:00 a. m.'));
    await user.click(screen.getByRole('button', { name: 'Cambiar a este horario' }));

    expect(await screen.findByRole('status')).toHaveTextContent(
      'Listo, tu cita quedó para el jueves 5 de noviembre a las 9:00 a. m.',
    );
    expect(router.state.location.pathname).toBe('/c/abc');
    expect(posted(calls)).toMatchObject([{ url: link, body: { chosen: ['2026-11-05 09:00'], reason: null } }]);
  });
});
