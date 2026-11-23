import { screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { loadPage } from '@/app/router';
import { mockApi, renderApp } from '@/test/app';

// Páginas que se bajan a pedido, con lo mínimo: su título. La de contacto no llega (sin señal).
vi.mock('@/pages/FaqPage', () => ({ FaqPage: () => <h1>Preguntas frecuentes</h1> }));
vi.mock('@/pages/TeamPage', () => ({ TeamPage: () => <h1>Nuestro equipo</h1> }));
vi.mock('@/pages/ContactPage', () => {
  throw new TypeError('Failed to fetch dynamically imported module');
});

describe('the page the web is opened with', () => {
  it('comes out at once when its code was brought before painting, without the empty space first', async () => {
    mockApi();
    await loadPage('/preguntas-frecuentes');

    renderApp('/preguntas-frecuentes');

    expect(screen.getByRole('heading', { name: 'Preguntas frecuentes' })).toBeInTheDocument();
    expect(document.querySelector('.page-loading')).not.toBeInTheDocument();
  });

  it('shows the empty space of the page while its code arrives when it was not brought before', async () => {
    mockApi();

    renderApp('/equipo');

    expect(document.querySelector('.page-loading')).toBeInTheDocument();
    expect(await screen.findByRole('heading', { name: 'Nuestro equipo' })).toBeInTheDocument();
  });

  it('is painted anyway when its code does not arrive, saying so and offering to try again', async () => {
    // React y React Router anotan el error en la consola.
    vi.spyOn(console, 'error').mockImplementation(() => {});
    mockApi();
    await expect(loadPage('/contacto')).resolves.toBeUndefined();

    renderApp('/contacto');

    expect(await screen.findByRole('heading', { level: 1, name: 'Algo falló de nuestro lado' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Volver a intentar' })).toBeInTheDocument();
    expect(screen.queryByText(/Unexpected Application Error/)).not.toBeInTheDocument();
  });

  it('brings nothing for the home page, which comes in the main file', async () => {
    await expect(loadPage('/')).resolves.toBeUndefined();
    await expect(loadPage('/no-existe')).resolves.toBeUndefined();
  });
});
