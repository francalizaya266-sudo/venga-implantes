import { screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import type { BookedAppointment, BookingOptions } from '@/api/types';
import { mockApi, offline, renderApp } from '@/test/app';
import {
  bookedAppointment,
  bookingDays,
  bookingDevice,
  bookingOptions,
  bookingTreatment,
  meta,
  page,
  slot,
} from '@/test/fixtures';

const OPTIONS = '/api/v1/booking/options';
/** Las opciones se piden con lo que traía la dirección: aquí, el tratamiento ya elegido. */
const OPTIONS_CHOSEN = `${OPTIONS}?tratamiento=limpieza-dental`;
const DAYS = '/api/v1/booking/days?treatment=3';
const BOOK = 'POST /api/v1/booking/appointments';
/** La dirección del último paso: el servicio, el día y la hora ya elegidos. */
const DETAILS = '/reservar?tratamiento=limpieza-dental&fecha=2026-11-04&hora=09:00';
/** La cita reservada, en su propia dirección firmada. */
const BOOKED = '/api/v1/booking/appointments/9?signature=abc';

/** La reserva hecha: lo que responde la API al reservar y lo que muestra después su propia dirección. */
function booked(appointment: BookedAppointment = bookedAppointment()) {
  return { [BOOK]: { status: 201, body: { data: appointment } }, [BOOKED]: page(appointment) };
}

/** La API de una reserva sencilla; cada prueba cambia lo que le importa. */
function bookingApi(options: Partial<BookingOptions> = {}, answers: Parameters<typeof mockApi>[0] = {}) {
  return mockApi({
    [OPTIONS]: page(bookingOptions(options)),
    [OPTIONS_CHOSEN]: page(bookingOptions(options)),
    [DAYS]: { data: bookingDays() },
    ...answers,
  });
}

const step = (name: string) => screen.findByRole('heading', { level: 2, name });
const summary = () => within(screen.getByRole('complementary', { name: 'Resumen de tu cita' }));

describe('BookingPage', () => {
  it('books an appointment in three steps', async () => {
    const calls = bookingApi({}, booked());
    const { user, router } = renderApp('/reservar');

    await step('¿Qué necesitas?');
    expect(screen.getByRole('list', { name: 'Paso 1 de 3' })).toBeInTheDocument();
    expect(summary().getAllByText('Por elegir')).toHaveLength(3);
    await user.click(screen.getByRole('button', { name: /Limpieza dental/ }));

    await step('Elige día y hora');
    expect(screen.getByRole('list', { name: 'Paso 2 de 3' })).toBeInTheDocument();
    expect(summary().getByText('Limpieza dental')).toBeInTheDocument();
    expect(summary().getByText('Miércoles 4 de noviembre')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: '9:00 a. m.' }));

    await step('Tus datos');
    expect(router.state.location.search).toBe('?tratamiento=limpieza-dental&fecha=2026-11-04&hora=09%3A00');
    expect(summary().getByText('9:00 a. m.')).toBeInTheDocument();
    expect(summary().getByText('Dra. Vega')).toBeInTheDocument();
    await user.type(screen.getByLabelText('Tu celular'), '987654321');
    await user.type(screen.getByLabelText('Tus nombres'), 'Rosa');
    await user.type(screen.getByLabelText('Tus apellidos'), 'Quispe');
    await user.click(screen.getByRole('checkbox', { name: /Acepto la política de privacidad/ }));
    await user.click(screen.getByRole('button', { name: 'Reservar cita' }));

    expect(await step('¡Tu cita quedó reservada!')).toHaveFocus();
    expect(calls.find((call) => call.method === 'POST')?.body).toEqual({
      patient_id: null,
      phone: '987654321',
      who: 'me',
      relationship: 'self',
      first_name: 'Rosa',
      last_name: 'Quispe',
      birth_day: '',
      birth_month: '',
      birth_year: '',
      note: '',
      promo_code: '',
      accepts_privacy: true,
      accepts_promotions: false,
      treatment_id: 3,
      doctor_id: null,
      date: '2026-11-04',
      time: '09:00',
      origin: null,
      campaign_slug: null,
    });
    expect(screen.getByText('Miércoles 4 de noviembre, 9:00 a. m.')).toBeInTheDocument();
    expect(screen.getByText(/Te escribiremos por WhatsApp al 987 654 321 con un enlace/)).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Guardar en mi calendario' })).toHaveAttribute(
      'href',
      '/reservar/cita/9/calendario?signature=abc',
    );
    expect(screen.queryByRole('list', { name: /Paso/ })).not.toBeInTheDocument();
    // La pantalla tiene su propia dirección (firmada): recargar no la pierde, y retroceder no vuelve al formulario.
    expect(router.state.location.pathname).toBe('/reservar/cita/9');
    expect(router.state.location.search).toBe('?signature=abc');
    expect(router.state.historyAction).toBe('REPLACE');
  });

  it('opens the booked screen again from its own address, as after reloading the page', async () => {
    const calls = mockApi({ [BOOKED]: page(bookedAppointment({ remembered: true })) });
    renderApp('/reservar/cita/9?signature=abc');

    expect(await step('¡Tu cita quedó reservada!')).toHaveFocus();
    expect(screen.getByText('Miércoles 4 de noviembre, 9:00 a. m.')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Mi cuenta' })).toHaveAttribute('href', '/mi-cuenta');
    expect(calls.map((call) => call.url)).toContain(BOOKED);
  });

  it('says it when the address of a booked appointment is not valid or the appointment was cancelled', async () => {
    mockApi({
      '/api/v1/booking/appointments/9?signature=otra': {
        status: 404,
        body: {
          message: 'No encontramos esta página',
          meta: meta({ title: 'No encontramos esta página', noindex: true }),
        },
      },
    });
    renderApp('/reservar/cita/9?signature=otra');

    expect(await screen.findByRole('heading', { level: 1, name: 'No encontramos esta página' })).toBeInTheDocument();
  });

  it('marks what the API did not accept, names it by the button and forgets it as it gets fixed', async () => {
    bookingApi(
      {},
      {
        [BOOK]: {
          status: 422,
          body: {
            message: 'Escribe tu celular. (y 1 error más)',
            errors: {
              phone: ['Escribe tu celular.'],
              accepts_privacy: ['Para reservar, acepta la política de privacidad.'],
            },
          },
        },
      },
    );
    const { user } = renderApp(DETAILS);

    await step('Tus datos');
    await user.click(screen.getByRole('button', { name: 'Reservar cita' }));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Para reservar, revisa tu celular y la política de privacidad.',
    );
    const phone = screen.getByLabelText('Tu celular');
    expect(phone).toHaveAttribute('aria-invalid', 'true');
    expect(phone).toHaveAccessibleDescription('Escribe tu celular.');
    // En el celular el primer dato por corregir queda arriba, fuera de la vista: la pantalla va a él.
    expect(phone).toHaveFocus();
    expect(screen.getByText('Para reservar, acepta la política de privacidad.')).toBeInTheDocument();

    await user.type(phone, '987654321');

    expect(phone).not.toHaveAttribute('aria-invalid');
    expect(screen.queryByText('Escribe tu celular.')).not.toBeInTheDocument();
    expect(screen.getByRole('alert')).toHaveTextContent('Para reservar, revisa la política de privacidad.');
  });

  it('goes back to choose another time when the chosen one was just taken', async () => {
    const calls = bookingApi(
      {},
      {
        [BOOK]: {
          status: 409,
          body: { code: 'slot_taken', message: 'Ese horario se acaba de ocupar. Por favor elige otro.' },
        },
      },
    );
    const { user, router } = renderApp(DETAILS);

    await step('Tus datos');
    await user.click(screen.getByRole('button', { name: 'Reservar cita' }));

    await step('Elige día y hora');
    expect(screen.getByRole('alert')).toHaveTextContent('Ese horario se acaba de ocupar. Por favor elige otro.');
    expect(router.state.location.search).toBe('?tratamiento=limpieza-dental');
    // Los horarios se piden de nuevo: el que se ocupó ya no debe ofrecerse.
    await waitFor(() => expect(calls.filter((call) => call.url === DAYS)).toHaveLength(2));
  });

  it('keeps what was written when the request does not arrive', async () => {
    bookingApi({}, { [BOOK]: offline });
    const { user } = renderApp(DETAILS);

    await step('Tus datos');
    await user.type(screen.getByLabelText('Tu celular'), '987654321');
    await user.click(screen.getByRole('button', { name: 'Reservar cita' }));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'No pudimos conectarnos. Revisa tu conexión e inténtalo de nuevo.',
    );
    expect(screen.getByLabelText('Tu celular')).toHaveValue('987654321');
    expect(screen.getByRole('button', { name: 'Reservar cita' })).toBeEnabled();
  });

  it('opens at the day and time when it arrives with the treatment chosen, and lets change it', async () => {
    bookingApi(
      {},
      { [`${OPTIONS}?origen=facebook&tratamiento=limpieza-dental`]: page(bookingOptions({ origin: 'facebook' })) },
    );
    const { user, router } = renderApp('/reservar?tratamiento=limpieza-dental&origen=facebook');

    await step('Elige día y hora');
    expect(screen.getByText('Limpieza dental · 45 min aprox.')).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: '← Cambiar servicio' }));

    await step('¿Qué necesitas?');
    // De dónde llegó el paciente sigue en la dirección.
    expect(router.state.location.search).toBe('?origen=facebook');
  });

  it('starts with the treatment the API says the patient came for', async () => {
    bookingApi(
      {},
      {
        [`${OPTIONS}?promo=sonrisa-de-verano`]: page(
          bookingOptions({ initial_treatment_id: 3, campaign_slug: 'sonrisa-de-verano' }),
        ),
      },
    );
    renderApp('/reservar?promo=sonrisa-de-verano');

    await step('Elige día y hora');
    expect(screen.getByText('Limpieza dental · 45 min aprox.')).toBeInTheDocument();
  });

  it('asks for the times of another day when the patient touches it', async () => {
    bookingApi(
      {},
      {
        '/api/v1/booking/slots?treatment=3&date=2026-11-05': {
          data: { date: '2026-11-05', day: 'Jueves 5 de noviembre', slots: [slot('11:00', '11:00 a. m.')] },
        },
      },
    );
    const { user } = renderApp('/reservar?tratamiento=limpieza-dental');

    await step('Elige día y hora');
    await user.click(screen.getByRole('button', { name: 'Jueves 5 de noviembre' }));
    await user.click(await screen.findByRole('button', { name: '11:00 a. m.' }));

    await step('Tus datos');
    expect(screen.getByText('Jueves 5 de noviembre, 11:00 a. m.')).toBeInTheDocument();
    expect(screen.getByText('con Dra. Vega')).toBeInTheDocument();
  });

  it('lets choose the doctor when several attend the treatment', async () => {
    const doctors = [
      { id: 1, name: 'Dra. Vega' },
      { id: 2, name: 'Dr. Ramos' },
    ];
    bookingApi(
      {},
      {
        [DAYS]: { data: bookingDays({ doctors }) },
        [`${DAYS}&doctor=2`]: {
          data: bookingDays({ doctors, doctor_id: 2, slots: [{ ...slot('16:00', '4:00 p. m.'), doctor_id: 2 }] }),
        },
      },
    );
    const { user } = renderApp('/reservar?tratamiento=limpieza-dental');

    await step('Elige día y hora');
    const who = within(screen.getByRole('group', { name: 'Doctor' }));
    expect(who.getByRole('button', { name: 'El primero disponible' })).toHaveAttribute('aria-pressed', 'true');
    await user.click(who.getByRole('button', { name: 'Dr. Ramos' }));

    expect(await screen.findByRole('button', { name: '4:00 p. m.' })).toBeInTheDocument();
    expect(who.getByRole('button', { name: 'Dr. Ramos' })).toHaveAttribute('aria-pressed', 'true');
    expect(summary().getByText('Dr. Ramos')).toBeInTheDocument();
  });

  it('asks the doctor the patient came from, when the address brings one', async () => {
    const doctors = [
      { id: 1, name: 'Dra. Vega' },
      { id: 2, name: 'Dr. Ramos' },
    ];
    bookingApi(
      {},
      {
        [`${OPTIONS}?doctor=dr-ramos&tratamiento=limpieza-dental`]: page(bookingOptions({ preferred_doctor_id: 2 })),
        [`${DAYS}&doctor=2`]: { data: bookingDays({ doctors, doctor_id: 2 }) },
      },
    );
    renderApp('/reservar?tratamiento=limpieza-dental&doctor=dr-ramos');

    await step('Elige día y hora');
    expect(
      within(screen.getByRole('group', { name: 'Doctor' })).getByRole('button', { name: 'Dr. Ramos' }),
    ).toHaveAttribute('aria-pressed', 'true');
  });

  it('says it when the treatment has no free times, and offers to tell the patient when one frees up', async () => {
    bookingApi({}, { [DAYS]: { data: bookingDays({ days: [], date: null, day: null, slots: [] }) } });
    renderApp('/reservar?tratamiento=limpieza-dental');

    await step('Elige día y hora');
    expect(screen.getByText(/No encontramos horarios libres en las próximas semanas\./)).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: '¿Te avisamos cuando haya uno?' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Avísame si se libera un horario' })).toBeInTheDocument();
  });

  it('says with which doctora there are no free times, as it is said: "con la Dra."', async () => {
    const doctors = [
      { id: 1, name: 'Dr. Ramos' },
      { id: 2, name: 'Dra. Lucía Fernández' },
    ];
    const empty = { days: [], date: null, day: null, slots: [] };
    bookingApi(
      {},
      {
        [DAYS]: { data: bookingDays({ doctors }) },
        [`${DAYS}&doctor=2`]: { data: bookingDays({ doctors, doctor_id: 2, ...empty }) },
      },
    );
    const { user } = renderApp('/reservar?tratamiento=limpieza-dental');

    await step('Elige día y hora');
    await user.click(screen.getByRole('button', { name: 'Dra. Lucía Fernández' }));

    expect(
      await screen.findByText(/No encontramos horarios libres en las próximas semanas con la Dra\. Lucía Fernández\./),
    ).toBeInTheDocument();
    expect(screen.getByText(/si se libera un horario con la Dra\. Lucía Fernández\./)).toBeInTheDocument();
  });

  it('lets whoever finds no time that suits them join the waiting list', async () => {
    const calls = bookingApi(
      {},
      {
        'POST /api/v1/booking/waitlist': {
          status: 201,
          body: { message: 'Listo, te anotamos. Si se libera un horario, te escribimos por WhatsApp al 987 654 321.' },
        },
      },
    );
    const { user } = renderApp('/reservar?tratamiento=limpieza-dental');

    await step('Elige día y hora');
    const ask = within(screen.getByRole('region', { name: '¿Ningún horario te sirve?' }));
    await user.click(ask.getByRole('button', { name: 'Avísame si se libera un horario' }));

    expect(ask.getByLabelText('Tu celular')).toHaveFocus();
    await user.type(ask.getByLabelText('Tu celular'), '987654321');
    await user.type(ask.getByLabelText('Nombres'), 'Rosa');
    await user.type(ask.getByLabelText('Apellidos'), 'Quispe');
    await user.click(ask.getByRole('button', { name: 'En la tarde' }));
    await user.type(ask.getByLabelText('¿Qué días te sirven? (opcional)'), 'lunes o sábados');
    await user.click(ask.getByRole('checkbox', { name: /Acepto la política de privacidad/ }));
    await user.click(ask.getByRole('button', { name: 'Avísame' }));

    expect(
      await ask.findByText('Listo, te anotamos. Si se libera un horario, te escribimos por WhatsApp al 987 654 321.'),
    ).toBeInTheDocument();
    expect(calls.find((call) => call.method === 'POST')?.body).toEqual({
      treatment_id: 3,
      doctor_id: null,
      patient_id: null,
      phone: '987654321',
      first_name: 'Rosa',
      last_name: 'Quispe',
      preferred_period: 'afternoon',
      days: 'lunes o sábados',
      accepts_privacy: true,
    });
    // Sigue en el paso de horarios, por si igual encuentra uno.
    expect(screen.getByRole('button', { name: '9:00 a. m.' })).toBeInTheDocument();
  });

  it('marks what the waiting list did not accept next to each field', async () => {
    bookingApi(
      {},
      {
        'POST /api/v1/booking/waitlist': {
          status: 422,
          body: {
            message: 'Revisa tu celular.',
            errors: {
              phone: ['Revisa tu celular: debe tener 9 dígitos y empezar con 9.'],
              accepts_privacy: ['Para anotarte, acepta la política de privacidad.'],
            },
          },
        },
      },
    );
    const { user } = renderApp('/reservar?tratamiento=limpieza-dental');

    await step('Elige día y hora');
    await user.click(screen.getByRole('button', { name: 'Avísame si se libera un horario' }));
    await user.type(screen.getByLabelText('Tu celular'), '12345');
    await user.click(screen.getByRole('button', { name: 'Avísame' }));

    expect(await screen.findByText('Revisa tu celular: debe tener 9 dígitos y empezar con 9.')).toBeInTheDocument();
    expect(screen.getByText('Para anotarte, acepta la política de privacidad.')).toBeInTheDocument();
    const phone = screen.getByLabelText('Tu celular');
    expect(phone).toHaveAttribute('aria-invalid', 'true');
    // El primer campo por revisar queda listo, y el lector de pantalla dice qué pasó al llegar a él.
    await waitFor(() => expect(phone).toHaveFocus());
    expect(phone).toHaveAccessibleDescription('Revisa tu celular: debe tener 9 dígitos y empezar con 9.');
    expect(screen.getByRole('checkbox', { name: /Acepto la política de privacidad/ })).toHaveAccessibleDescription(
      'Para anotarte, acepta la política de privacidad.',
    );
  });

  it('from a remembered phone the waiting list does not ask the number and can be for a relative', async () => {
    const doctors = [
      { id: 1, name: 'Dra. Vega' },
      { id: 2, name: 'Dr. Ramos' },
    ];
    const calls = bookingApi(
      { device: bookingDevice() },
      {
        [DAYS]: { data: bookingDays({ doctors }) },
        [`${DAYS}&doctor=2`]: { data: bookingDays({ doctors, doctor_id: 2 }) },
        'POST /api/v1/booking/waitlist': {
          status: 201,
          body: { message: 'Listo, te anotamos. Si se libera un horario, te escribimos por WhatsApp al 987 654 321.' },
        },
      },
    );
    const { user } = renderApp('/reservar?tratamiento=limpieza-dental');

    await step('Elige día y hora');
    await user.click(screen.getByRole('button', { name: 'Dr. Ramos' }));
    expect(await screen.findByText(/si se libera un horario con el Dr\. Ramos\./)).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Avísame si se libera un horario' }));

    expect(screen.queryByLabelText('Tu celular')).not.toBeInTheDocument();
    expect(screen.queryByRole('checkbox', { name: /política de privacidad/ })).not.toBeInTheDocument();
    expect(screen.getByText('Te escribimos al 987 654 321.')).toBeInTheDocument();
    const who = within(screen.getByRole('group', { name: '¿Para quién es?' }));
    expect(who.getByRole('button', { name: 'Rosa Quispe' })).toHaveAttribute('aria-pressed', 'true');
    await user.click(who.getByRole('button', { name: 'Luis Quispe' }));
    await user.click(screen.getByRole('button', { name: 'Avísame' }));

    await screen.findByText('Listo, te anotamos. Si se libera un horario, te escribimos por WhatsApp al 987 654 321.');
    expect(calls.find((call) => call.method === 'POST')?.body).toMatchObject({
      treatment_id: 3,
      doctor_id: 2,
      patient_id: 22,
      phone: null,
      first_name: null,
      last_name: null,
      preferred_period: 'any',
    });
  });

  it('says it when nothing can be booked on the web, and still offers WhatsApp to whoever is in pain', async () => {
    bookingApi({ treatments: [] });
    renderApp('/reservar');

    await step('¿Qué necesitas?');
    expect(
      screen.getByText('Por ahora no hay horarios para reservar en la web. Escríbenos por WhatsApp y te ayudamos.'),
    ).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Tengo dolor o es urgente/ })).toHaveAttribute(
      'href',
      `https://wa.me/51912345678?text=${encodeURIComponent('Hola, tengo dolor y necesito una cita lo antes posible.')}`,
    );
  });

  it('shows the promotion the patient is booking with in every step', async () => {
    bookingApi(
      {
        treatments: [
          bookingTreatment(),
          bookingTreatment({ id: 4, slug: 'evaluacion', name: 'Evaluación', label: 'Evaluación', is_evaluation: true }),
        ],
        campaign: {
          name: 'Sonrisa de verano',
          discount: '20% de descuento',
          treatment: { id: 3, name: 'Limpieza dental' },
        },
      },
      { '/api/v1/booking/days?treatment=4': { data: bookingDays() } },
    );
    const { user } = renderApp('/reservar');

    await step('¿Qué necesitas?');
    expect(screen.getByText('Reservas con la promoción Sonrisa de verano · 20% de descuento.')).toBeInTheDocument();

    // Con otro servicio, la promoción se revisa en esa cita.
    await user.click(screen.getByRole('button', { name: /Evaluación/ }));

    await step('Elige día y hora');
    expect(
      screen.getByText('Se aplica a Limpieza dental: el doctor lo revisa en esta cita y lo programa contigo.'),
    ).toBeInTheDocument();
  });

  it('checks the promotion code as soon as it is written', async () => {
    const calls = bookingApi(
      {},
      {
        [`${OPTIONS}?codigo=VERANO&tratamiento=limpieza-dental`]: page(
          bookingOptions({
            promo_code: 'VERANO',
            campaign: { name: 'Sonrisa de verano', discount: null, treatment: null },
          }),
        ),
        [`${OPTIONS}?codigo=NOEXISTE&tratamiento=limpieza-dental`]: page(
          bookingOptions({
            promo_code: 'NOEXISTE',
            promo_problem: 'No reconocimos el código NOEXISTE. Puedes reservar igual; lo revisamos en el consultorio.',
          }),
        ),
        ...booked(
          bookedAppointment({
            promo_notice:
              'No reconocimos el código NOEXISTE. Igual reservamos tu cita; lo revisamos en el consultorio.',
          }),
        ),
      },
    );
    const { user } = renderApp(DETAILS);

    await step('Tus datos');
    await user.type(screen.getByLabelText('Tu celular'), '987654321');
    const code = screen.getByLabelText('Código de promoción');
    await user.type(code, ' verano ');
    await user.tab();

    expect(await screen.findByText('Código aplicado: Sonrisa de verano.')).toBeInTheDocument();
    expect(screen.getByText('Reservas con la promoción Sonrisa de verano.')).toBeInTheDocument();
    expect(calls.map((call) => call.url)).toContain(`${OPTIONS}?codigo=VERANO&tratamiento=limpieza-dental`);
    // Mientras se consulta el código, lo escrito sigue en su sitio.
    expect(screen.getByLabelText('Tu celular')).toHaveValue('987654321');

    await user.clear(code);
    await user.type(code, 'noexiste');
    await user.tab();

    // Un código mal escrito se avisa, pero no impide reservar: queda en la cita y recepción lo revisa.
    expect(
      await screen.findByText(
        'No reconocimos el código NOEXISTE. Puedes reservar igual; lo revisamos en el consultorio.',
      ),
    ).toBeInTheDocument();
    expect(code).not.toHaveAttribute('aria-invalid');
    expect(screen.queryByText(/Reservas con la promoción/)).not.toBeInTheDocument();

    await user.type(screen.getByLabelText('Tus nombres'), 'Rosa');
    await user.type(screen.getByLabelText('Tus apellidos'), 'Quispe');
    await user.click(screen.getByRole('checkbox', { name: /Acepto la política de privacidad/ }));
    await user.click(screen.getByRole('button', { name: 'Reservar cita' }));

    await step('¡Tu cita quedó reservada!');
    expect(
      screen.getByText('No reconocimos el código NOEXISTE. Igual reservamos tu cita; lo revisamos en el consultorio.'),
    ).toBeInTheDocument();
    expect(calls.find((call) => call.method === 'POST')?.body).toMatchObject({ promo_code: 'noexiste' });
  });

  it('from a remembered phone it asks who the appointment is for, not the number', async () => {
    const calls = bookingApi({ device: bookingDevice() }, booked(bookedAppointment({ remembered: true })));
    const { user } = renderApp(DETAILS);

    await step('Tus datos');
    expect(screen.queryByLabelText('Tu celular')).not.toBeInTheDocument();
    expect(screen.queryByLabelText('Tus nombres')).not.toBeInTheDocument();
    // Ya aceptó la política de privacidad: no se le vuelve a pedir.
    expect(screen.queryByRole('checkbox', { name: /Acepto la política/ })).not.toBeInTheDocument();
    const who = within(screen.getByRole('group', { name: '¿Para quién es la cita?' }));
    expect(who.getByRole('button', { name: 'Rosa Quispe' })).toHaveAttribute('aria-pressed', 'true');

    await user.click(screen.getByRole('button', { name: 'Reservar cita' }));

    await step('¡Tu cita quedó reservada!');
    expect(calls.find((call) => call.method === 'POST')?.body).toMatchObject({ patient_id: 21, phone: '' });
    expect(screen.getByRole('link', { name: 'Mi cuenta' })).toHaveAttribute('href', '/mi-cuenta');
    expect(screen.queryByRole('link', { name: 'Escribir por WhatsApp' })).not.toBeInTheDocument();
  });

  it('from a remembered phone another person is a relative of the holder', async () => {
    const calls = bookingApi({ device: bookingDevice() }, booked(bookedAppointment({ remembered: true })));
    const { user } = renderApp(DETAILS);

    await step('Tus datos');
    await user.click(screen.getByRole('button', { name: 'Otra persona' }));

    expect(screen.queryByRole('radiogroup', { name: '¿La cita es para ti?' })).not.toBeInTheDocument();
    expect(screen.getByLabelText('El paciente es tu…')).toHaveValue('child');
    await user.type(screen.getByLabelText('Nombres del paciente'), 'Ana');
    await user.type(screen.getByLabelText('Apellidos del paciente'), 'Quispe');
    await user.type(screen.getByLabelText('Día'), '7');
    await user.selectOptions(screen.getByLabelText('Mes'), 'Marzo');
    await user.type(screen.getByLabelText('Año'), '2019');
    await user.click(screen.getByRole('button', { name: 'Reservar cita' }));

    await step('¡Tu cita quedó reservada!');
    expect(calls.find((call) => call.method === 'POST')?.body).toMatchObject({
      patient_id: null,
      who: 'family',
      relationship: 'child',
      first_name: 'Ana',
      last_name: 'Quispe',
      birth_day: '7',
      birth_month: '3',
      birth_year: '2019',
    });
  });

  it('without a remembered phone the patient says whether the appointment is for a relative', async () => {
    bookingApi();
    const { user } = renderApp(DETAILS);

    await step('Tus datos');
    expect(screen.queryByLabelText('El paciente es tu…')).not.toBeInTheDocument();

    await user.click(screen.getByRole('radio', { name: 'Para un familiar' }));

    expect(screen.getByLabelText('El paciente es tu…')).toHaveValue('child');
    expect(screen.getByLabelText('Nombres del paciente')).toBeInTheDocument();
    expect(screen.getByText('Nos ayuda a preparar la atención, sobre todo si es un niño.')).toBeInTheDocument();
  });

  it('does not let book for whoever is in treatment: the doctor schedules those sessions', async () => {
    bookingApi({ device: bookingDevice({ patient_id: 22 }) });
    const { user } = renderApp('/reservar');

    // Se le dice desde el primer paso, no al final.
    await step('¿Qué necesitas?');
    expect(screen.getByRole('status')).toHaveTextContent(
      'Luis está en tratamiento: su doctor programa sus próximas sesiones.',
    );
    expect(
      screen.getByText('Si la cita es para otra persona, sigue: la eliges en el último paso.'),
    ).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: /Limpieza dental/ }));
    await user.click(await screen.findByRole('button', { name: '9:00 a. m.' }));

    await step('Tus datos');
    expect(screen.getByRole('button', { name: 'Luis Quispe · en tratamiento' })).toHaveAttribute(
      'aria-pressed',
      'true',
    );
    expect(screen.getByRole('button', { name: 'Reservar cita' })).toBeDisabled();

    await user.click(screen.getByRole('button', { name: 'Rosa Quispe' }));

    expect(screen.getByRole('button', { name: 'Reservar cita' })).toBeEnabled();
  });

  it('after booking, asks once how the patient heard about the clinic', async () => {
    const calls = bookingApi(
      {},
      {
        ...booked(),
        'POST /api/v1/booking/appointments/9/origin?signature=abc': { message: '¡Gracias por contarnos!' },
      },
    );
    const { user } = renderApp(DETAILS);

    await step('Tus datos');
    await user.click(screen.getByRole('button', { name: 'Reservar cita' }));
    await step('¡Tu cita quedó reservada!');
    await user.click(
      within(screen.getByRole('group', { name: /¿cómo nos conociste\?/ })).getByRole('button', { name: 'Facebook' }),
    );

    expect(screen.getByText('¡Gracias por contarnos!')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Recomendación' })).not.toBeInTheDocument();
    expect(calls[calls.length - 1]).toMatchObject({
      method: 'POST',
      url: '/api/v1/booking/appointments/9/origin?signature=abc',
      body: { origin: 'facebook' },
    });
  });

  it('shows the error page when the booking options cannot be read', async () => {
    mockApi({ [OPTIONS]: { status: 500, body: { message: 'Server Error' } } });
    renderApp('/reservar');

    expect(await screen.findByRole('heading', { level: 1, name: 'Algo falló de nuestro lado' })).toBeInTheDocument();
  });
});
