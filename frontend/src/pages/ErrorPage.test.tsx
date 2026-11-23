import { screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { ErrorPage } from '@/pages/ErrorPage';
import { mockApi, renderPiece } from '@/test/app';

describe('ErrorPage', () => {
  it('says the fault is on our side when the code of the web breaks, which is not the connection', async () => {
    mockApi();
    renderPiece(<ErrorPage error={new TypeError("Cannot read properties of undefined (reading 'name')")} />);

    expect(await screen.findByRole('heading', { level: 1, name: 'Algo falló de nuestro lado' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Volver a intentar' })).toBeInTheDocument();
  });

  it('asks to check the connection whatever failed while the browser says there is no signal', async () => {
    mockApi();
    vi.spyOn(navigator, 'onLine', 'get').mockReturnValue(false);
    renderPiece(<ErrorPage error={new Error('Algo se cortó')} />);

    expect(await screen.findByRole('heading', { level: 1, name: 'No pudimos conectarnos' })).toBeInTheDocument();
    expect(screen.getByText('Revisa tu conexión e inténtalo de nuevo.')).toBeInTheDocument();
  });

  it('keeps the page that does not exist as such, even without signal', async () => {
    mockApi();
    vi.spyOn(navigator, 'onLine', 'get').mockReturnValue(false);
    renderPiece(<ErrorPage status={404} />);

    expect(await screen.findByRole('heading', { level: 1, name: 'No encontramos esta página' })).toBeInTheDocument();
  });
});
