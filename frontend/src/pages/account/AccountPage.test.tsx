import { screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import type { PlanCard } from '@/api/types';
import { mockApi, renderApp } from '@/test/app';
import { account, appointment, page } from '@/test/fixtures';

const ACCOUNT = '/api/v1/account';
const ACCESS = 'POST /api/v1/account/access';

const plan: PlanCard = {
  id: 4,
  title: 'Ortodoncia',
  state: 'En curso',
  tone: 'ok',
  who: 'Para Luis Quispe',
  included_in: null,
  progress: {
    percent: 40,
    total: 'S/ 2,000.00',
    paid: 'S/ 800.00',
    remaining: 'S/ 1,200.00',
    owed: { label: 'Cuota vencida', amount: 'S/ 200.00', late: true },
    next: { amount: 'S/ 200.00', day: '15 de noviembre', label: '(cuota 5 de 10)' },
  },
  payment: 'En 10 cuotas mensuales.',
  items_summary: 'Ver los 2 tratamientos del plan',
  items: [
    { id: 1, label: 'Brackets metálicos', state: 'Hecho', done: true },
    { id: 2, label: 'Retenedores', state: 'Pendiente', done: false },
  ],
};

describe('Mi cuenta from a phone that is not remembered', () => {
  it('asks for the link by WhatsApp and answers the same to everyone', async () => {
    const calls = mockApi({
      [ACCOUNT]: page({ guest: true }),
      [ACCESS]: { data: { phone: '987 654 321', whatsapp_message: 'Hola, pedí mi enlace para entrar a Mi cuenta.' } },
    });
    const { user } = renderApp('/mi-cuenta');

    await user.type(await screen.findByLabelText('Tu celular'), '987654321');
    await user.click(screen.getByRole('button', { name: 'Pedir mi enlace por WhatsApp' }));

    const requested = within(await screen.findByRole('status'));
    expect(requested.getByRole('heading', { name: 'Te enviaremos tu enlace por WhatsApp' })).toBeInTheDocument();
    expect(requested.getByText(/Si tu número está registrado, .* al 987 654 321 en su horario/)).toBeInTheDocument();
    expect(requested.getByRole('link', { name: 'Avisar al consultorio por WhatsApp' })).toHaveAttribute(
      'href',
      `https://wa.me/51912345678?text=${encodeURIComponent('Hola, pedí mi enlace para entrar a Mi cuenta.')}`,
    );
    expect(screen.queryByLabelText('Tu celular')).not.toBeInTheDocument();
    expect(calls.find((call) => call.method === 'POST')?.body).toEqual({ phone: '987654321' });
  });

  it('says next to the number what is wrong with it', async () => {
    mockApi({
      [ACCOUNT]: page({ guest: true }),
      [ACCESS]: { status: 422, body: { message: 'x', errors: { phone: ['Escribe un celular de 9 dígitos.'] } } },
    });
    const { user } = renderApp('/mi-cuenta');

    const phone = await screen.findByLabelText('Tu celular');
    await user.type(phone, '123');
    await user.click(screen.getByRole('button', { name: 'Pedir mi enlace por WhatsApp' }));

    expect(await screen.findByText('Escribe un celular de 9 dígitos.')).toBeInTheDocument();
    expect(phone).toHaveAttribute('aria-invalid', 'true');

    await user.type(phone, '4');
    expect(screen.queryByText('Escribe un celular de 9 dígitos.')).not.toBeInTheDocument();
  });

  it('asks to wait when the link was requested too many times', async () => {
    mockApi({
      [ACCOUNT]: page({ guest: true }),
      [ACCESS]: { status: 429, body: { message: 'Demasiados intentos. Espera unos minutos y vuelve a intentarlo.' } },
    });
    const { user } = renderApp('/mi-cuenta');

    await user.type(await screen.findByLabelText('Tu celular'), '987654321');
    await user.click(screen.getByRole('button', { name: 'Pedir mi enlace por WhatsApp' }));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Demasiados intentos. Espera unos minutos y vuelve a intentarlo.',
    );
    expect(screen.getByLabelText('Tu celular')).toHaveValue('987654321');
  });

  it('points whoever has never booked to their first appointment', async () => {
    mockApi({ [ACCOUNT]: page({ guest: true }) });
    renderApp('/mi-cuenta');

    expect(await screen.findByRole('link', { name: 'Reserva la primera' })).toHaveAttribute('href', '/reservar');
  });
});

describe('Mi cuenta from the remembered phone', () => {
  it('shows what comes next and confirms an appointment without leaving the page', async () => {
    let confirmed = false;
    const calls = mockApi({
      [ACCOUNT]: () => ({
        body: page(
          account({
            several: true,
            upcoming: [
              appointment(confirmed ? { status: 'Confirmada', tone: 'ok', can_confirm: false } : {}),
              appointment({ id: 8, when: 'Viernes 6 de noviembre, 4:00 p. m.', what: 'Control · Dra. Vega' }),
            ],
          }),
        ),
      }),
      'POST /api/v1/account/appointments/7/confirm': () => {
        confirmed = true;

        return { body: { message: 'Listo, tu asistencia quedó confirmada.' } };
      },
    });
    const { user } = renderApp('/mi-cuenta');

    expect(await screen.findByRole('heading', { level: 2, name: 'Hola, Rosa' })).toBeInTheDocument();
    expect(screen.getByText(/Citas de tu familia/)).toBeInTheDocument();
    const upcoming = within(screen.getByRole('region', { name: 'Próximas citas' }));
    expect(upcoming.getByRole('link', { name: 'Reservar otra cita' })).toHaveAttribute('href', '/reservar');
    expect(upcoming.getAllByRole('link', { name: 'Cambiar horario' }).map((link) => link.getAttribute('href'))).toEqual(
      ['/mi-cuenta/citas/7/cambiar', '/mi-cuenta/citas/8/cambiar'],
    );

    await user.click(upcoming.getAllByRole('button', { name: 'Confirmar asistencia' })[0]!);

    expect(await screen.findByRole('status')).toHaveTextContent('Listo, tu asistencia quedó confirmada.');
    // La página se pide de nuevo: la cita ya dice cómo quedó.
    expect(upcoming.getByText('Confirmada')).toBeInTheDocument();
    expect(upcoming.getAllByRole('button', { name: 'Confirmar asistencia' })).toHaveLength(1);
    expect(calls.filter((call) => call.url === ACCOUNT)).toHaveLength(2);
  });

  it('keeps the amounts and the personal data behind a verification by WhatsApp', async () => {
    const calls = mockApi({
      [ACCOUNT]: page(account()),
      'POST /api/v1/account/verification': { message: 'Te enviaremos el enlace por WhatsApp al 987 654 321.' },
    });
    const { user } = renderApp('/mi-cuenta');

    const upcoming = within(await screen.findByRole('region', { name: 'Próximas citas' }));
    expect(upcoming.getByText('No tienes citas próximas.')).toBeInTheDocument();
    expect(upcoming.getByRole('link', { name: 'Reservar cita' })).toHaveAttribute('href', '/reservar');
    expect(screen.queryByRole('region', { name: 'Planes y pagos' })).not.toBeInTheDocument();
    const private_ = within(screen.getByRole('region', { name: 'Planes, pagos, citas anteriores y datos' }));
    await user.click(private_.getByRole('button', { name: 'Enviarme el enlace' }));

    expect(await screen.findByRole('status')).toHaveTextContent('Te enviaremos el enlace por WhatsApp al 987 654 321.');
    expect(calls.filter((call) => call.method === 'POST')).toMatchObject([{ url: '/api/v1/account/verification' }]);
  });

  it('after the verification shows the plans, what is owed, the past appointments and the family', async () => {
    mockApi({
      [ACCOUNT]: page(
        account({
          elevated: true,
          plans: [plan, { ...plan, id: 5, title: 'Limpieza', progress: null, included_in: 'Rosa Quispe', items: [] }],
          balances: [{ name: 'Rosa Quispe', text: 'Debe S/ 50.00', owed: true }],
          past: [{ id: 3, date: '12 oct 2026', name: 'Rosa', treatment: 'Evaluación', status: 'Atendida' }],
          family: [
            {
              id: 21,
              name: 'Rosa Quispe',
              relationship: 'Titular',
              document: 'DNI 44556677',
              birth_date: null,
              email: null,
            },
          ],
        }),
      ),
    });
    renderApp('/mi-cuenta');

    const plans = within(await screen.findByRole('region', { name: 'Planes y pagos' }));
    expect(plans.getByRole('img', { name: 'Pagado S/ 800.00 de S/ 2,000.00' })).toBeInTheDocument();
    expect(plans.getByText('Cuota vencida')).toBeInTheDocument();
    expect(plans.getByText('S/ 200.00 · 15 de noviembre')).toBeInTheDocument();
    expect(plans.getByText('Va incluido en el 2x1 de Rosa Quispe: no tiene costo.')).toBeInTheDocument();
    expect(plans.getByText('Saldo de Rosa Quispe')).toBeInTheDocument();
    expect(plans.getByText('Debe S/ 50.00')).toHaveClass('owed');
    expect(plans.getByText('Pagas en el consultorio con Yape, Plin, tarjeta o efectivo.')).toBeInTheDocument();

    const past = within(screen.getByRole('region', { name: 'Citas anteriores y datos personales' }));
    expect(past.getByText('Atendida')).toBeInTheDocument();
    expect(past.getByText('DNI 44556677')).toBeInTheDocument();
    expect(past.queryByRole('button', { name: 'Enviarme el enlace' })).not.toBeInTheDocument();
  });

  it('forgets this phone when the patient leaves', async () => {
    let remembered = true;
    mockApi({
      [ACCOUNT]: () => ({ body: page(remembered ? account() : { guest: true }) }),
      'POST /api/v1/account/logout': () => {
        remembered = false;

        return { body: { message: 'Este celular ya no está recordado.' } };
      },
    });
    const { user } = renderApp('/mi-cuenta');

    await user.click(await screen.findByRole('button', { name: 'Salir en este celular' }));

    expect(await screen.findByRole('heading', { level: 2, name: 'Pedir mi enlace' })).toBeInTheDocument();
    expect(screen.getByRole('status')).toHaveTextContent('Este celular ya no está recordado.');
    expect(screen.queryByText('Hola, Rosa')).not.toBeInTheDocument();
  });
});
