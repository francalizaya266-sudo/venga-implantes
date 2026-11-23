import { screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { AppointmentCard as Appointment } from '@/api/types';
import { AppointmentCard } from '@/components/site/AppointmentCard';
import { Flash } from '@/lib/flash';
import { mockApi, renderPiece } from '@/test/app';
import { appointment, site } from '@/test/fixtures';

const CONFIRM = '/api/v1/links/abc/confirm';
const CANCEL = '/api/v1/links/abc/cancel';

/** La tarjeta como va en el enlace de WhatsApp y en Mi cuenta (con cancelar) o en el inicio (sin cancelar). */
function renderCard(changes: Partial<Appointment> = {}, { cancel = true } = {}) {
  return renderPiece(
    <>
      <Flash />
      <AppointmentCard
        appointment={appointment(changes)}
        clinic={site().clinic}
        confirmUrl={CONFIRM}
        changeHref="/destino/cambiar"
        cancelUrl={cancel ? CANCEL : undefined}
      />
    </>,
  );
}

describe('AppointmentCard', () => {
  it('shows the appointment with the same words everywhere', () => {
    mockApi();
    renderCard();

    expect(screen.getByText('Miércoles 4 de noviembre, 10:00 a. m.')).toBeInTheDocument();
    expect(screen.getByText('Limpieza dental · Dra. Vega')).toBeInTheDocument();
    expect(screen.getByText('Por confirmar')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Confirmar asistencia' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Cambiar horario' })).toHaveAttribute('href', '/destino/cambiar');
    expect(screen.getByText('Cancelar cita')).toBeInTheDocument();
  });

  it('confirms the appointment and tells how it went', async () => {
    const calls = mockApi({ [`POST ${CONFIRM}`]: { message: 'Listo, tu asistencia quedó confirmada.' } });
    const { user } = renderCard();

    await user.click(screen.getByRole('button', { name: 'Confirmar asistencia' }));

    expect(await screen.findByRole('status')).toHaveTextContent('Listo, tu asistencia quedó confirmada.');
    expect(calls).toContainEqual(expect.objectContaining({ method: 'POST', url: CONFIRM, body: {} }));
  });

  it('cancels the appointment with the reason the patient wrote', async () => {
    const calls = mockApi({ [`POST ${CANCEL}`]: { message: 'Tu cita quedó cancelada.' } });
    const { user } = renderCard();

    await user.click(screen.getByText('Cancelar cita'));
    await user.type(screen.getByLabelText('Motivo (opcional)'), 'Estaré de viaje');
    await user.click(screen.getByRole('button', { name: 'Sí, cancelar la cita' }));

    expect(await screen.findByRole('status')).toHaveTextContent('Tu cita quedó cancelada.');
    expect(calls).toContainEqual(
      expect.objectContaining({ method: 'POST', url: CANCEL, body: { reason: 'Estaré de viaje' } }),
    );
  });

  it('says what the API answered when the link stopped working', async () => {
    mockApi({
      [`POST ${CONFIRM}`]: {
        status: 404,
        body: { message: 'No encontramos lo que buscas. Puede que el enlace esté incompleto o ya no exista.' },
      },
    });
    const { user } = renderCard();

    await user.click(screen.getByRole('button', { name: 'Confirmar asistencia' }));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'No encontramos lo que buscas. Puede que el enlace esté incompleto o ya no exista.',
    );
  });

  it('asks to check the connection when the request does not even arrive', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(() => Promise.reject(new TypeError('Failed to fetch'))),
    );
    const { user } = renderCard();

    await user.click(screen.getByRole('button', { name: 'Confirmar asistencia' }));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'No pudimos conectarnos. Revisa tu conexión e inténtalo de nuevo.',
    );
    expect(screen.getByRole('button', { name: 'Confirmar asistencia' })).toBeEnabled();
  });

  it('sends to WhatsApp, with the message started, when it is too late to cancel here', () => {
    mockApi();
    renderCard({ can_cancel: false });

    expect(screen.queryByText('Cancelar cita')).not.toBeInTheDocument();
    expect(screen.getByText(/Faltan menos de 4 horas para la cita/)).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Escríbenos por WhatsApp' })).toHaveAttribute(
      'href',
      `https://wa.me/51912345678?text=${encodeURIComponent(appointment().cancel_message)}`,
    );
  });

  it('does not offer to confirm what is already confirmed', () => {
    mockApi();
    renderCard({ can_confirm: false, status: 'Confirmada', tone: 'ok' });

    expect(screen.queryByRole('button', { name: 'Confirmar asistencia' })).not.toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Cambiar horario' })).toBeInTheDocument();
  });

  it('offers nothing for an appointment that can no longer be changed', () => {
    mockApi();
    renderCard({ can_change: false, can_confirm: false, can_cancel: false });

    expect(screen.queryByRole('button')).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Cambiar horario' })).not.toBeInTheDocument();
    expect(screen.queryByText(/Faltan menos de/)).not.toBeInTheDocument();
  });

  it('reminds the times the patient asked to change the appointment to, and does not offer to ask again', () => {
    mockApi();
    renderCard({ pending_request: 'jueves 5 de noviembre a las 9:00 a. m. o viernes 6 de noviembre a las 3:00 p. m.' });

    const note = screen.getByText('Pediste cambiarla.').closest('p');
    expect(note).toHaveTextContent(
      'Pediste cambiarla. Te avisamos por WhatsApp. Propusiste: jueves 5 de noviembre a las 9:00 a. m. o viernes 6 de noviembre a las 3:00 p. m.',
    );
    expect(screen.queryByRole('link', { name: 'Cambiar horario' })).not.toBeInTheDocument();
    // Mientras tanto la cita sigue como estaba: se puede confirmar o cancelar.
    expect(screen.getByRole('button', { name: 'Confirmar asistencia' })).toBeInTheDocument();
    expect(screen.getByText('Cancelar cita')).toBeInTheDocument();
  });

  it('invites to tell the health history before the appointment', () => {
    mockApi();
    renderCard({ ask_health: { patient_id: 12, text: 'Cuéntanos tu salud en dos minutos.' } });

    expect(screen.getByRole('link', { name: /Antes de la cita/ })).toHaveAttribute(
      'href',
      '/mi-cuenta/pacientes/12/salud',
    );
  });

  it('warns whoever did not book it and, without cancelling here, points to Mi cuenta', () => {
    mockApi();
    renderCard({ guest_notice: true, ask_health: { patient_id: 12, text: 'Cuéntanos tu salud.' } }, { cancel: false });

    expect(screen.getByText('Reservaron esta cita con tu número.')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Mi cuenta' })).toHaveAttribute('href', '/mi-cuenta');
    // Primero que reconozca la cita: su salud se le pide después.
    expect(screen.queryByRole('link', { name: /Antes de la cita/ })).not.toBeInTheDocument();
    expect(screen.queryByText('Cancelar cita')).not.toBeInTheDocument();
  });
});
