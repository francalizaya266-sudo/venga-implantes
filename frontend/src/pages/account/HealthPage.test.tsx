import { screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { mockApi, renderApp } from '@/test/app';
import { account, healthData, page } from '@/test/fixtures';

const PATH = '/mi-cuenta/pacientes/12/salud';
const HEALTH = '/api/v1/account/patients/12/health';

const question = (name: string) => within(screen.getByRole('group', { name }));

describe('HealthPage', () => {
  it('sends the health history to the clinic and returns to Mi cuenta', async () => {
    const calls = mockApi({
      [HEALTH]: page(healthData()),
      [`POST ${HEALTH}`]: { status: 201, body: { message: 'Gracias, Rosa. El doctor lo revisará antes de tu cita.' } },
      '/api/v1/account': page(account()),
    });
    const { user, router } = renderApp(PATH);

    expect(await screen.findByRole('heading', { level: 1, name: 'Antes de tu cita, Rosa' })).toBeInTheDocument();
    await user.click(question('¿Tienes alguna alergia?').getByRole('radio', { name: 'Sí' }));
    await user.type(question('¿Tienes alguna alergia?').getByLabelText('¿A qué?'), 'Penicilina');
    await user.click(question('¿Tomas algún medicamento?').getByRole('radio', { name: 'No' }));
    await user.click(screen.getByRole('checkbox', { name: 'Diabetes' }));
    await user.click(screen.getByRole('checkbox', { name: 'Presión alta' }));
    await user.click(screen.getByRole('checkbox', { name: 'Presión alta' }));
    await user.click(screen.getByRole('checkbox', { name: 'Estoy embarazada' }));
    await user.type(screen.getByLabelText('¿Algo más que el doctor deba saber?'), 'Me operaron en marzo');
    await user.click(screen.getByRole('button', { name: 'Enviar al consultorio' }));

    expect(await screen.findByRole('status')).toHaveTextContent(
      'Gracias, Rosa. El doctor lo revisará antes de tu cita.',
    );
    expect(router.state.location.pathname).toBe('/mi-cuenta');
    expect(calls.find((call) => call.method === 'POST')).toMatchObject({
      url: HEALTH,
      body: {
        has_allergies: 'yes',
        allergies: 'Penicilina',
        takes_medication: 'no',
        medication: '',
        conditions: ['diabetes'],
        is_pregnant: true,
        notes: 'Me operaron en marzo',
      },
    });
  });

  it('takes the patient to the first question left unanswered', async () => {
    const calls = mockApi({
      [HEALTH]: page(healthData()),
      [`POST ${HEALTH}`]: {
        status: 422,
        body: {
          message: 'Elige Sí o No.',
          errors: { takes_medication: ['Elige Sí o No.'], medication: ['Cuéntanos qué medicamento tomas.'] },
        },
      },
    });
    const { user } = renderApp(PATH);

    await screen.findByRole('heading', { level: 1, name: 'Antes de tu cita, Rosa' });
    await user.click(question('¿Tienes alguna alergia?').getByRole('radio', { name: 'No' }));
    await user.click(screen.getByRole('button', { name: 'Enviar al consultorio' }));

    const medication = question('¿Tomas algún medicamento?');
    expect(await medication.findByText('Elige Sí o No.')).toBeInTheDocument();
    expect(medication.getByRole('radio', { name: 'No' })).toHaveFocus();
    expect(medication.getByLabelText('¿Cuál?')).toHaveAttribute('aria-invalid', 'true');
    // Lo que no se respondió viaja vacío: es la API la que dice qué falta.
    expect(calls.find((call) => call.method === 'POST')?.body).toMatchObject({
      has_allergies: 'no',
      takes_medication: null,
      is_pregnant: false,
    });

    await user.click(medication.getByRole('radio', { name: 'Sí' }));
    expect(medication.queryByText('Elige Sí o No.')).not.toBeInTheDocument();
    await user.type(medication.getByLabelText('¿Cuál?'), 'Aspirina');
    expect(medication.queryByText('Cuéntanos qué medicamento tomas.')).not.toBeInTheDocument();
  });

  it('returns to Mi cuenta with the notice when the clinic already has the history', async () => {
    mockApi({
      [HEALTH]: {
        status: 409,
        body: { code: 'already_known', message: 'El consultorio ya tiene los antecedentes de Rosa.' },
      },
      '/api/v1/account': page(account()),
    });
    const { router } = renderApp(PATH);

    expect(await screen.findByRole('status')).toHaveTextContent('El consultorio ya tiene los antecedentes de Rosa.');
    expect(router.state.location.pathname).toBe('/mi-cuenta');
  });

  it('leaves out the extra questions that do not apply to the patient', async () => {
    mockApi({ [HEALTH]: page(healthData({ also: [] })) });
    renderApp(PATH);

    await screen.findByRole('heading', { level: 1, name: 'Antes de tu cita, Rosa' });
    expect(screen.queryByRole('group', { name: 'También' })).not.toBeInTheDocument();
  });

  it('does not show the form of a patient of another family', async () => {
    mockApi({ [HEALTH]: { status: 403, body: { message: 'No tienes permiso para ver o cambiar esto.' } } });
    renderApp(PATH);

    expect(await screen.findByRole('heading', { level: 1, name: 'No puedes ver esta página' })).toBeInTheDocument();
  });
});
