import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { AppLink } from '@/components/ui/AppLink';
import { renderPiece } from '@/test/app';

/** Un enlace de siempre haría que el navegador cargue otra página: aquí basta con saber que el router no lo tomó. */
function stayOnClick(): void {
  document.addEventListener('click', (event) => event.preventDefault(), { once: true });
}

describe('AppLink', () => {
  it('moves to another page of the web without loading it again', async () => {
    const { user, router } = renderPiece(<AppLink href="/destino/limpieza?promo=verano">Ver</AppLink>);

    await user.click(screen.getByRole('link', { name: 'Ver' }));

    expect(await screen.findByText('Otra página')).toBeInTheDocument();
    expect(router.state.location).toMatchObject({ pathname: '/destino/limpieza', search: '?promo=verano' });
  });

  it('takes the full addresses Laravel writes as pages of the web', async () => {
    const { user, router } = renderPiece(<AppLink href="http://localhost:3000/destino/equipo">Ver</AppLink>);

    await user.click(screen.getByRole('link', { name: 'Ver' }));

    expect(router.state.location.pathname).toBe('/destino/equipo');
  });

  it.each([
    ['another site', 'https://wa.me/51912345678?text=Hola'],
    ['the panel', '/panel'],
    ['a file', '/storage/consentimientos/7.pdf'],
    ['the calendar file of an appointment', '/reservar/cita/9/calendario'],
    ['a part of the same page', '#proximas'],
  ])('leaves %s to the browser', async (_what, href) => {
    const { user, router } = renderPiece(<AppLink href={href}>Ver</AppLink>);
    const link = screen.getByRole('link', { name: 'Ver' });
    stayOnClick();

    await user.click(link);

    expect(link).toHaveAttribute('href', href);
    expect(router.state.location.pathname).toBe('/');
  });

  it('leaves a link that opens in another tab to the browser', async () => {
    const { user, router } = renderPiece(
      <AppLink href="/destino/privacidad" target="_blank">
        Ver
      </AppLink>,
    );
    stayOnClick();

    await user.click(screen.getByRole('link', { name: 'Ver' }));

    expect(router.state.location.pathname).toBe('/');
  });
});
