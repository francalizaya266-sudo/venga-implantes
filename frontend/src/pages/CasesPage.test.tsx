import { screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import type { CasesData, SmileCase } from '@/api/types';
import { mockApi, renderApp } from '@/test/app';
import { page } from '@/test/fixtures';

function smileCase(id: number, title: string, treatment: { slug: string; name: string }): SmileCase {
  return {
    id,
    title,
    before_url: `/storage/casos/${id}-antes.jpg`,
    after_url: `/storage/casos/${id}-despues.jpg`,
    description_html: null,
    treatment,
  };
}

const implants = { slug: 'implantes', name: 'Implantes' };
const whitening = { slug: 'blanqueamiento', name: 'Blanqueamiento' };
const implant = smileCase(1, 'Un implante en lugar del premolar', implants);
const brighter = smileCase(2, 'Tres tonos más claro', whitening);

function cases(changes: Partial<CasesData> = {}): CasesData {
  return { cases: [implant, brighter], treatments: [implants, whitening], selected: null, ...changes };
}

const shown = () => screen.getAllByRole('figure').map((figure) => within(figure).getByRole('strong').textContent);

describe('CasesPage', () => {
  it('shows the before and after of each case and filters them by treatment', async () => {
    mockApi({
      '/api/v1/cases': page(cases()),
      '/api/v1/cases?tratamiento=implantes': page(cases({ cases: [implant], selected: 'implantes' })),
    });
    const { user, router } = renderApp('/antes-y-despues');

    expect(await screen.findByRole('img', { name: 'Antes: Tres tonos más claro' })).toHaveAttribute(
      'src',
      '/storage/casos/2-antes.jpg',
    );
    expect(shown()).toEqual(['Un implante en lugar del premolar', 'Tres tonos más claro']);
    const filter = within(screen.getByRole('navigation', { name: 'Filtrar por tratamiento' }));
    expect(filter.getByRole('link', { name: 'Todos' })).toHaveAttribute('aria-current', 'page');

    await user.click(filter.getByRole('link', { name: 'Implantes' }));

    expect(router.state.location.search).toBe('?tratamiento=implantes');
    expect(await screen.findByRole('link', { name: 'Implantes', current: 'page' })).toBeInTheDocument();
    expect(shown()).toEqual(['Un implante en lugar del premolar']);
  });

  it('says cases are coming, without a filter, while none is published', async () => {
    mockApi({ '/api/v1/cases': page(cases({ cases: [], treatments: [] })) });
    renderApp('/antes-y-despues');

    expect(await screen.findByText('Pronto publicaremos casos de nuestros pacientes.')).toBeInTheDocument();
    expect(screen.queryByRole('navigation', { name: 'Filtrar por tratamiento' })).not.toBeInTheDocument();
  });
});
