import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import type { Review } from '@/api/types';
import { mockApi, renderApp } from '@/test/app';
import { page } from '@/test/fixtures';

const review: Review = {
  id: 1,
  rating: 5,
  body: 'Me atendieron a la hora y sin dolor.',
  author: 'Rosa Q.',
  source: 'Google',
  url: null,
  date: 'octubre de 2026',
};

describe('ReviewsPage', () => {
  it('shows the published reviews', async () => {
    mockApi({ '/api/v1/reviews': page({ reviews: [review] }) });
    renderApp('/opiniones');

    expect(await screen.findByText('Me atendieron a la hora y sin dolor.')).toBeInTheDocument();
    expect(screen.getByLabelText('5 de 5 estrellas')).toBeInTheDocument();
  });

  it('is a page that does not exist while no review is published', async () => {
    mockApi({ '/api/v1/reviews': { status: 404, body: { message: 'No encontramos esta página.' } } });
    renderApp('/opiniones');

    expect(await screen.findByRole('heading', { level: 1, name: 'No encontramos esta página' })).toBeInTheDocument();
    expect(screen.queryByText(/Pronto publicaremos/)).not.toBeInTheDocument();
  });
});
