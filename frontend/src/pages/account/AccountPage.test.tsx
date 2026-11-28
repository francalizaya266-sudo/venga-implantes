import { screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import type { AccountMember, PlanCard } from '@/api/types';
import { mockApi, renderApp } from '@/test/app';
import { account, appointment, page } from '@/test/fixtures';

const ACCOUNT = '/api/v1/account';
const ACCESS = 'POST /api/v1/account/access';

const plan: PlanCard = {
  id: 4,
  patient_id: 22,
  title: 'Ortodoncia',
  state: 'Atrasado',
  tone: 'bad',
  who: 'Luis · Dra. Vega',
  finished: false,
  included_in: null,
  steps: { done: 1, started: 1, total: 3, text: '1 de 3 hechos · 1 en curso' },
  next_session: null,
  progress: {
    percent: 40,
    total: 'S/ 2,000.00',
    paid: 'S/ 800.00',
    remaining: 'S/ 1,200.00',
    owed: { label: 'Vencido', amount: 'S/ 200.00', late: true },
    next: { amount: 'S/ 200.00', day: 'Domingo 15 de noviembre', date: '2026-11-15', label: 'Cuota 3 de 6' },
  },
  payment: 'En cuotas. Inicial de S/ 800.00 y 6 cuotas de S/ 200.00.',
  installments: [
    {
      id: 1,
      label: 'Inicial',
      day: 'Martes 15 de septiembre',
      amount: 'S/ 800.00',
      state: 'paid',
      state_label: 'Pagado',
      left: null,
    },
    {
      id: 2,
      label: 'Cuota 1 de 6',
      day: 'Jueves 15 de octubre',
      amount: 'S/ 200.00',
      state: 'late',
      state_label: 'Vencido',
      left: 'S/ 150.00',
    },
    {
      id: 3,
      label: 'Cuota 2 de 6',
      day: 'Domingo 15 de noviembre',
      amount: 'S/ 200.00',
      state: 'upcoming',
      state_label: 'Por vencer',
      left: null,
    },
  ],
  items: [
    { id: 1, label: 'Colocación de brackets', detail: null, state: 'Hecho', status: 'done', sessions: null },
    {
      id: 2,
      label: 'Control de ortodoncia',
      detail: null,
      state: '3 de 10 sesiones',
      status: 'in_progress',
      sessions: { done: 3, planned: 10 },
    },
    {
      id: 3,
      label: 'Curación con resina',
      detail: 'Primer molar inferior izquierdo · cara oclusal',
      state: 'Por hacer',
      status: 'pending',
      sessions: null,
    },
  ],
};

/** Rosa (titular) y su hijo Luis, ya verificados: lo que pide cada prueba se cambia encima. */
const rosa: AccountMember = {
  id: 21,
  name: 'Rosa Quispe',
  short_name: 'Rosa',
  initials: 'RQ',
  is_self: true,
  relationship: 'Titular',
  document: 'DNI 44556677',
  birth_date: null,
  email: null,
  ask_health: null,
};
const luis: AccountMember = {
  ...rosa,
  id: 22,
  name: 'Luis Quispe',
  short_name: 'Luis',
  initials: 'LQ',
  is_self: false,
  relationship: 'Hijo(a)',
  document: null,
  ask_health: 'Cuéntanos de la salud de Luis en dos minutos.',
};

const session = {
  patient_id: 22,
  kicker: 'Tratamiento de Luis',
  title: 'A Luis le faltan 8 de 12 sesiones',
  done: 4,
  total: 12,
  note: 'Cada sesión la programa su doctor: pídela y te escribimos con el horario.',
  requested: false,
  button: 'Pedir su próxima sesión',
};

function verified(changes: Parameters<typeof account>[0] = {}) {
  return account({
    elevated: true,
    several: true,
    plans: [],
    balances: [],
    payments: [],
    past: [],
    family: [rosa, luis],
    ...changes,
  });
}

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

  it('points whoever has never booked to their first appointment, which needs no link', async () => {
    mockApi({ [ACCOUNT]: page({ guest: true }) });
    renderApp('/mi-cuenta');

    const first = await screen.findByRole('link', { name: 'Reserva la primera' });
    expect(first).toHaveAttribute('href', '/reservar');
    // Con su primera reserva, el celular queda recordado: no hay enlace que esperar.
    expect(first.closest('p')).toHaveTextContent('entras a Mi cuenta al instante, sin esperar ningún enlace');
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
    expect(screen.queryByRole('region', { name: 'Tratamientos' })).not.toBeInTheDocument();
    expect(screen.queryByRole('region', { name: 'Pagos' })).not.toBeInTheDocument();
    // El resumen no dice montos: lleva a confirmar que es el titular.
    const summary = within(screen.getByRole('region', { name: 'Resumen' }));
    expect(summary.getByRole('link', { name: /No tienes citas por delante/ })).toHaveAttribute('href', '/reservar');
    expect(summary.getByRole('link', { name: /Tus tratamientos, pagos e historial/ })).toHaveAttribute(
      'href',
      '#privado',
    );
    const private_ = within(screen.getByRole('region', { name: 'Tratamientos, pagos, citas anteriores y datos' }));
    await user.click(private_.getByRole('button', { name: 'Enviarme el enlace' }));

    // El aviso sale junto al botón, que ya dice que está hecho, y no arriba de la página.
    expect(await private_.findByRole('status')).toHaveTextContent(
      'Te enviaremos el enlace por WhatsApp al 987 654 321.',
    );
    expect(private_.getByRole('button', { name: 'Listo, te lo enviamos' })).toBeDisabled();
    expect(screen.getAllByRole('status')).toHaveLength(1);
    expect(calls.filter((call) => call.method === 'POST')).toMatchObject([{ url: '/api/v1/account/verification' }]);
  });

  it('says next to the button why the link could not be sent, and lets it be asked again', async () => {
    mockApi({
      [ACCOUNT]: page(account()),
      'POST /api/v1/account/verification': {
        status: 429,
        body: { message: 'Demasiados intentos. Espera unos minutos y vuelve a intentarlo.' },
      },
    });
    const { user } = renderApp('/mi-cuenta');

    const private_ = within(
      await screen.findByRole('region', { name: 'Tratamientos, pagos, citas anteriores y datos' }),
    );
    await user.click(private_.getByRole('button', { name: 'Enviarme el enlace' }));

    expect(await private_.findByRole('alert')).toHaveTextContent(
      'Demasiados intentos. Espera unos minutos y vuelve a intentarlo.',
    );
    expect(private_.getByRole('button', { name: 'Enviarme el enlace' })).toBeEnabled();
  });

  it('after the verification shows each treatment with its route, its payment and every cuota', async () => {
    mockApi({
      [ACCOUNT]: page(
        verified({
          plans: [
            plan,
            {
              ...plan,
              id: 5,
              patient_id: 21,
              title: 'Limpieza',
              state: 'Incluido en el 2x1',
              tone: 'ok',
              who: 'Rosa · Dra. Vega',
              steps: { done: 0, started: 0, total: 0, text: '0 de 0 hechos' },
              progress: null,
              payment: null,
              included_in: 'Luis',
              installments: [],
              items: [],
            },
          ],
          balances: [{ patient_id: 22, name: 'Luis Quispe', text: 'S/ 200.00 por pagar', owed: true }],
          owed_total: 'S/ 200.00',
        }),
      ),
    });
    renderApp('/mi-cuenta');

    const plans = within(await screen.findByRole('region', { name: 'Tratamientos' }));
    const [orthodontics, cleaning] = plans.getAllByRole('article').map((card) => within(card));
    // La ruta del plan: las palabras del doctor y cada tratamiento con cómo va y, si se sabe, en qué pieza.
    expect(
      orthodontics!.getByRole('img', { name: 'Avance del tratamiento: 1 de 3 hechos · 1 en curso' }),
    ).toBeVisible();
    expect(
      orthodontics!
        .getAllByRole('listitem')
        .slice(0, 3)
        .map((item) => item.textContent),
    ).toEqual([
      'Colocación de bracketsHecho',
      'Control de ortodoncia3 de 10 sesiones',
      'Curación con resinaPrimer molar inferior izquierdo · cara oclusalPor hacer',
    ]);
    // El pago: cuánto va, lo vencido en rojo, el próximo y cada cuota. Con algo vencido, las cuotas ya vienen abiertas.
    expect(orthodontics!.getByRole('img', { name: 'Pagado S/ 800.00 de S/ 2,000.00' })).toBeInTheDocument();
    const late = orthodontics!.getByText('Vencido', { selector: 'dt' }).nextElementSibling;
    expect(late).toHaveTextContent('S/ 200.00');
    expect(late).toHaveClass('owed');
    expect(orthodontics!.getByText('Domingo 15 de noviembre · Cuota 3 de 6')).toBeInTheDocument();
    expect(orthodontics!.getByText('Ver los 3 pagos').closest('details')).toHaveAttribute('open');
    // De la cuota vencida ya abonó una parte: se dice cuánto falta.
    expect(orthodontics!.getByText('Falta S/ 150.00').closest('li')).toHaveTextContent(/Vencido/);
    expect(orthodontics!.getByText('En cuotas. Inicial de S/ 800.00 y 6 cuotas de S/ 200.00.')).toBeInTheDocument();
    expect(cleaning!.getByText('Va incluido en el 2x1 de Luis: no tiene costo.')).toBeInTheDocument();

    const money = within(screen.getByRole('region', { name: 'Pagos' }));
    expect(money.getByText('Saldo de Luis Quispe')).toBeInTheDocument();
    expect(money.getByText('S/ 200.00 por pagar')).toHaveClass('owed');
    expect(money.getByText('Pagas en el consultorio con Yape, Plin, tarjeta o efectivo.')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Enviarme el enlace' })).not.toBeInTheDocument();
  });

  it('says first, in one line each, when the next appointment is, how the treatment goes and what is owed', async () => {
    mockApi({
      [ACCOUNT]: page(
        verified({
          upcoming: [
            appointment({ patient_id: 22, kicker: 'Cita de Luis', countdown: 'Mañana' }),
            appointment({ id: 8, status: 'Confirmada', tone: 'ok', can_confirm: false }),
          ],
          plans: [plan],
          balances: [
            { patient_id: 22, name: 'Luis Quispe', text: 'S/ 200.00 por pagar', owed: true },
            { patient_id: 21, name: 'Rosa Quispe', text: 'S/ 50.00 por pagar', owed: true },
          ],
          owed_total: 'S/ 250.00',
        }),
      ),
    });
    renderApp('/mi-cuenta');

    const summary = within(await screen.findByRole('region', { name: 'Resumen' }));
    const lines = summary.getAllByRole('link');
    expect(lines.map((line) => line.getAttribute('href'))).toEqual(['#proximas', '#planes', '#pagos']);
    expect(lines[0]).toHaveTextContent('La cita de Luis es mañana');
    expect(lines[0]).toHaveTextContent('Miércoles 4 de noviembre, 10:00 a. m. Falta confirmarla. Y hay 1 cita más.');
    expect(lines[1]).toHaveTextContent('Ortodoncia: 1 de 3 hechos · 1 en curso');
    // Lo que debe la familia entre todos, y en rojo porque un pago ya venció.
    expect(lines[2]).toHaveTextContent('S/ 250.00 por pagar, con un pago vencido');
    expect(lines[2]).toHaveTextContent('Próximo pago: S/ 200.00, domingo 15 de noviembre.');
    expect(lines[2]).toHaveClass('bad');
  });

  it('says that payments are up to date when nobody owes', async () => {
    mockApi({
      [ACCOUNT]: page(
        verified({
          family: [rosa],
          several: false,
          plans: [
            { ...plan, patient_id: 21, state: 'Al día', tone: 'ok', progress: { ...plan.progress!, owed: null } },
          ],
          payments: [{ id: 1, patient_id: 21, date: '15/09/2026', name: 'Rosa', amount: 'S/ 800.00', method: 'Yape' }],
        }),
      ),
    });
    renderApp('/mi-cuenta');

    const money = within(await screen.findByRole('region', { name: 'Resumen' })).getByRole('link', {
      name: /Pagos al día/,
    });
    expect(money).toHaveTextContent('Próximo pago: S/ 200.00, domingo 15 de noviembre.');
    expect(money).toHaveClass('ok');
    // Una sola persona en la cuenta: no hay de quién elegir y cada pago no repite su nombre.
    expect(screen.queryByRole('group', { name: 'De quién ver' })).not.toBeInTheDocument();
    const paid = within(screen.getByRole('region', { name: 'Pagos' }));
    expect(paid.getByText('No hay saldos por pagar.')).toBeInTheDocument();
    expect(paid.getByRole('listitem')).toHaveTextContent('15/09/2026YapeS/ 800.00');
  });

  it('lets a family choose whose appointments, treatments, payments and data to see', async () => {
    mockApi({
      [ACCOUNT]: page(
        verified({
          upcoming: [appointment(), appointment({ id: 8, patient_id: 22, kicker: 'Cita de Luis' })],
          plans: [plan],
          balances: [{ patient_id: 22, name: 'Luis Quispe', text: 'S/ 200.00 por pagar', owed: true }],
          owed_total: 'S/ 200.00',
          payments: [
            { id: 1, patient_id: 22, date: '15/09/2026', name: 'Luis', amount: 'S/ 800.00', method: 'Yape' },
            { id: 2, patient_id: 21, date: '01/09/2026', name: 'Rosa', amount: 'S/ 50.00', method: 'Efectivo' },
          ],
          past: [
            {
              id: 3,
              patient_id: 21,
              date: '12/10/2026',
              name: 'Rosa',
              treatment: 'Evaluación',
              doctor: 'Dra. Vega',
              status: 'Atendida',
              tone: 'ok',
              visit: null,
            },
          ],
        }),
      ),
    });
    const { user } = renderApp('/mi-cuenta');

    const who = within(await screen.findByRole('group', { name: 'De quién ver' }));
    expect(who.getByRole('button', { name: 'Toda la familia' })).toHaveAttribute('aria-pressed', 'true');
    expect(screen.getAllByRole('button', { name: 'Confirmar asistencia' })).toHaveLength(2);

    await user.click(who.getByRole('button', { name: 'Rosa' }));

    // De Rosa: su cita, su pago y sus datos. El plan y la deuda son de Luis.
    const upcoming = within(screen.getByRole('region', { name: 'Próximas citas' }));
    expect(upcoming.getAllByRole('button', { name: 'Confirmar asistencia' })).toHaveLength(1);
    expect(upcoming.queryByText('Cita de Luis')).not.toBeInTheDocument();
    expect(upcoming.getByRole('link', { name: 'Reservar otra cita' })).toHaveAttribute('href', '/reservar?para=21');
    expect(screen.getByRole('region', { name: 'Tratamientos' })).toHaveTextContent(
      'Rosa no tiene tratamientos en esta cuenta.',
    );
    const money = within(screen.getByRole('region', { name: 'Pagos' }));
    expect(money.getByText('No hay saldos por pagar.')).toBeInTheDocument();
    expect(money.getAllByRole('listitem')).toHaveLength(1);
    expect(
      within(screen.getByRole('region', { name: 'Resumen' })).getByRole('link', { name: /Pagos al día/ }),
    ).toBeVisible();
    const family = within(screen.getByRole('region', { name: 'Mi familia' }));
    expect(family.getByRole('heading', { name: 'Rosa Quispe' })).toBeInTheDocument();
    expect(family.queryByRole('heading', { name: 'Luis Quispe' })).not.toBeInTheDocument();

    await user.click(who.getByRole('button', { name: 'Luis' }));

    expect(
      within(screen.getByRole('region', { name: 'Tratamientos' })).getByRole('heading', { name: 'Ortodoncia' }),
    ).toBeVisible();
    expect(screen.getByRole('region', { name: 'Citas anteriores' })).toHaveTextContent(
      'Todavía no hay citas anteriores.',
    );
  });

  it('shows when the next session of a treatment is, or asks reception for it with one tap', async () => {
    let requested = false;
    const calls = mockApi({
      [ACCOUNT]: () => ({
        body: page(
          verified({
            sessions: [{ ...session, requested }],
            plans: [plan, { ...plan, id: 6, patient_id: 21, next_session: 'Lunes 9 de noviembre, 4:00 p. m.' }],
          }),
        ),
      }),
      'POST /api/v1/account/patients/22/next-session': () => {
        requested = true;

        return { body: { message: 'Listo: recepción ya sabe que Luis quiere su próxima sesión.' } };
      },
    });
    const { user } = renderApp('/mi-cuenta');

    const plans = within(await screen.findByRole('region', { name: 'Tratamientos' }));
    const [ofLuis, ofRosa] = plans.getAllByRole('article').map((card) => within(card));
    expect(ofRosa!.getByText('Lunes 9 de noviembre, 4:00 p. m.')).toBeInTheDocument();
    expect(ofRosa!.queryByRole('button', { name: 'Pedir su próxima sesión' })).not.toBeInTheDocument();
    expect(ofLuis!.getByText('Todavía no tiene fecha la próxima sesión.')).toBeInTheDocument();
    // Lo que falta agendar también va entre las citas, como en el inicio.
    const upcoming = within(screen.getByRole('region', { name: 'Próximas citas' }));
    expect(upcoming.getByText('A Luis le faltan 8 de 12 sesiones')).toBeInTheDocument();
    expect(upcoming.queryByText('No tienes citas próximas.')).not.toBeInTheDocument();

    await user.click(ofLuis!.getByRole('button', { name: 'Pedir su próxima sesión' }));

    expect(await ofLuis!.findByText('Ya la pediste. Te escribiremos por WhatsApp con el horario.')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Pedir su próxima sesión' })).not.toBeInTheDocument();
    expect(calls.filter((call) => call.method === 'POST')).toMatchObject([
      { url: '/api/v1/account/patients/22/next-session' },
    ]);
  });

  it('keeps the finished treatments folded at the end', async () => {
    mockApi({
      [ACCOUNT]: page(
        verified({
          plans: [plan, { ...plan, id: 7, title: 'Sellantes', state: 'Terminado', tone: 'ok', finished: true }],
        }),
      ),
    });
    const { user } = renderApp('/mi-cuenta');

    const plans = within(await screen.findByRole('region', { name: 'Tratamientos' }));
    const folded = plans.getByText('Ver 1 tratamiento terminado').closest('details')!;
    expect(folded).not.toHaveAttribute('open');
    expect(within(folded).getByRole('heading', { name: 'Sellantes' })).not.toBeVisible();
    // El resumen solo cuenta lo que sigue en curso.
    expect(
      within(screen.getByRole('region', { name: 'Resumen' })).getByRole('link', { name: /Ortodoncia: 1 de 3/ }),
    ).toBeVisible();

    await user.click(plans.getByText('Ver 1 tratamiento terminado'));

    expect(within(folded).getByRole('heading', { name: 'Sellantes' })).toBeVisible();
  });

  it('keeps what the patient took home from each past appointment', async () => {
    mockApi({
      [ACCOUNT]: page(
        verified({
          past: [
            {
              id: 3,
              patient_id: 21,
              date: '12/10/2026',
              name: 'Rosa',
              treatment: 'Endodoncia',
              doctor: 'Dra. Vega',
              status: 'Atendida',
              tone: 'ok',
              visit: {
                done: ['Endodoncia · Primer molar inferior izquierdo'],
                indications: 'No mastiques de ese lado por dos días.',
                medicines: [{ name: 'Ibuprofeno 400 mg', how: '1 tableta cada 8 horas por 3 días' }],
                aftercare: null,
              },
            },
            {
              id: 2,
              patient_id: 22,
              date: '05/10/2026',
              name: 'Luis',
              treatment: 'Control',
              doctor: 'Dra. Vega',
              status: 'No asistió',
              tone: 'bad',
              visit: null,
            },
          ],
        }),
      ),
    });
    const { user } = renderApp('/mi-cuenta');

    const past = within(await screen.findByRole('region', { name: 'Citas anteriores' }));
    const attended = within(past.getByText('Endodoncia', { selector: 'strong' }).closest('li')!);
    const missed = within(past.getByText('Control', { selector: 'strong' }).closest('li')!);
    expect(attended!.getByText('12/10/2026 · Rosa · Dra. Vega')).toBeInTheDocument();
    expect(missed!.getByText('No asistió')).toHaveClass('bad');
    // La que no se atendió no tiene nada que abrir.
    expect(missed!.queryByText(/^Ver /)).not.toBeInTheDocument();

    await user.click(attended!.getByText('Ver lo que se hizo, las indicaciones y la receta'));

    expect(attended!.getByText('Endodoncia · Primer molar inferior izquierdo')).toBeVisible();
    expect(attended!.getByText('No mastiques de ese lado por dos días.')).toBeVisible();
    expect(attended!.getByText('Ibuprofeno 400 mg').closest('li')).toHaveTextContent(
      'Ibuprofeno 400 mg: 1 tableta cada 8 horas por 3 días',
    );
    expect(attended!.queryByRole('heading', { name: 'Cuidados' })).not.toBeInTheDocument();
  });

  it('gives each person their data, their health form and a way to fix what is wrong', async () => {
    mockApi({ [ACCOUNT]: page(verified()) });
    renderApp('/mi-cuenta');

    const family = within(await screen.findByRole('region', { name: 'Mi familia' }));
    const [ofRosa, ofLuis] = family.getAllByRole('article').map((card) => within(card));
    expect(ofRosa!.getByText('DNI 44556677')).toBeInTheDocument();
    expect(ofRosa!.getByText(/El doctor ya tiene tu ficha de salud/)).toBeInTheDocument();
    expect(ofRosa!.getByRole('link', { name: 'Reservar una cita' })).toHaveAttribute('href', '/reservar?para=21');
    // A quien falta contar su salud se le ofrece; un dato mal escrito se pide corregir con el mensaje ya empezado.
    expect(ofLuis!.getByRole('link', { name: /Ficha de salud/ })).toHaveAttribute(
      'href',
      '/mi-cuenta/pacientes/22/salud',
    );
    expect(ofLuis!.getByRole('link', { name: 'Reservar para Luis' })).toHaveAttribute('href', '/reservar?para=22');
    expect(ofLuis!.getByRole('link', { name: 'Corregir un dato' })).toHaveAttribute(
      'href',
      `https://wa.me/51912345678?text=${encodeURIComponent('Hola, quiero corregir un dato de Luis Quispe en su ficha: ')}`,
    );
    expect(family.getByRole('link', { name: 'Reserva su primera cita' })).toHaveAttribute('href', '/reservar');
  });

  it('lets each appointment be saved to the calendar and shows how to get there', async () => {
    mockApi({
      [ACCOUNT]: page(
        account({
          upcoming: [
            appointment({ countdown: 'En 3 días', calendar_url: '/reservar/cita/7/calendario.ics?signature=abc' }),
          ],
        }),
      ),
    });
    renderApp('/mi-cuenta');

    const upcoming = within(await screen.findByRole('region', { name: 'Próximas citas' }));
    expect(upcoming.getByText('En 3 días')).toBeInTheDocument();
    // El archivo lo entrega Laravel: es un enlace de siempre, no una página de la web.
    expect(upcoming.getByRole('link', { name: 'Guardar en mi calendario' })).toHaveAttribute(
      'href',
      '/reservar/cita/7/calendario.ics?signature=abc',
    );
    expect(upcoming.getByRole('link', { name: 'Cómo llegar' })).toHaveAttribute(
      'href',
      'https://maps.example/como-llegar',
    );
    expect(
      within(screen.getByRole('region', { name: 'Resumen' })).getByRole('link', { name: /Tu cita es en 3 días/ }),
    ).toBeVisible();
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
