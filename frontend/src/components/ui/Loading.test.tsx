import { onlineManager } from '@tanstack/react-query';
import { act, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { mockApi, renderApp } from '@/test/app';
import { page } from '@/test/fixtures';

const privacy = page({ clinic: 'Clínica Vega Implant', address: 'Jr. Apurímac 648, Juliaca', whatsapp: '918 874 568' });

/** La web ya abierta con señal (en Preguntas frecuentes); se va la señal y el paciente toca Privacidad. */
async function loseSignalAndOpenPrivacy() {
  const calls = mockApi({ '/api/v1/faqs': page({ topics: [] }), '/api/v1/pages/privacy': privacy });
  const view = renderApp('/preguntas-frecuentes');
  expect(await screen.findByText('Pronto publicaremos las preguntas más comunes.')).toBeInTheDocument();

  act(() => onlineManager.setOnline(false));
  await act(() => view.router.navigate('/privacidad'));

  return { ...view, calls };
}

afterEach(() => {
  act(() => onlineManager.setOnline(true));
});

describe('Loading', () => {
  it('says there is no signal while the page waits for it, and the page comes out by itself when it returns', async () => {
    const { calls } = await loseSignalAndOpenPrivacy();

    expect(await screen.findByRole('status')).toHaveTextContent(
      'Sin conexión. La página se cargará sola cuando vuelva la señal.',
    );
    expect(document.querySelector('.page-loading')).not.toHaveAttribute('aria-busy');
    expect(calls.map((call) => call.url)).not.toContain('/api/v1/pages/privacy');

    act(() => onlineManager.setOnline(true));

    expect(await screen.findByRole('heading', { level: 1, name: 'Política de privacidad' })).toBeInTheDocument();
    expect(screen.queryByText(/Sin conexión/)).not.toBeInTheDocument();
  });

  it('asks again at once when the browser already has signal', async () => {
    const { user } = await loseSignalAndOpenPrivacy();

    await user.click(await screen.findByRole('button', { name: 'Volver a intentar' }));

    expect(await screen.findByRole('heading', { level: 1, name: 'Política de privacidad' })).toBeInTheDocument();
  });

  it('says there is still no signal when trying again without it', async () => {
    const { user, calls } = await loseSignalAndOpenPrivacy();
    vi.spyOn(navigator, 'onLine', 'get').mockReturnValue(false);

    await user.click(await screen.findByRole('button', { name: 'Volver a intentar' }));

    expect(screen.getByRole('status')).toHaveTextContent(
      'Todavía no hay señal. La página se cargará sola cuando vuelva.',
    );
    expect(calls.map((call) => call.url)).not.toContain('/api/v1/pages/privacy');
  });

  it('is only the empty space of the page while there is signal', () => {
    mockApi({ '/api/v1/pages/privacy': privacy });

    renderApp('/privacidad');

    expect(document.querySelector('.page-loading')).toHaveAttribute('aria-busy', 'true');
    expect(screen.queryByRole('status')).not.toBeInTheDocument();
  });
});
