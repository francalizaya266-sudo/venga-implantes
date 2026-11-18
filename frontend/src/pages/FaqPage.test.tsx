import { screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import type { FaqTopic } from '@/api/types';
import { mockApi, renderApp } from '@/test/app';
import { page } from '@/test/fixtures';

const general: FaqTopic = {
  id: 'general',
  name: 'Antes de venir',
  treatment: null,
  faqs: [
    { id: 1, question: '¿Atienden los sábados?', answer_html: '<p>Sí, de 9:00 a. m. a 1:00 p. m.</p>' },
    { id: 2, question: '¿Cómo se paga?', answer_html: '<p>Con Yape, Plin, tarjeta o efectivo.</p>' },
  ],
};
const extraction: FaqTopic = {
  id: 'extraccion',
  name: 'Extracción',
  treatment: { slug: 'extraccion', art: 'extraccion' },
  faqs: [
    { id: 3, question: '¿Duele la extracción?', answer_html: '<p>Se hace con <strong>anestesia</strong> local.</p>' },
  ],
};

function openFaqs(topics: FaqTopic[]) {
  mockApi({ '/api/v1/faqs': page({ topics }) });

  return renderApp('/preguntas-frecuentes');
}

/** Las preguntas a la vista (las demás siguen en la página, ocultas). */
function questions(): string[] {
  return [...document.querySelectorAll('.faq-list details:not([hidden]) summary')]
    .filter((summary) => !summary.closest('section[hidden]'))
    .map((summary) => summary.textContent);
}

describe('FaqPage', () => {
  it('lists every question under its topic, with the way to its treatment', async () => {
    openFaqs([general, extraction]);

    expect(await screen.findByRole('heading', { level: 2, name: 'Antes de venir' })).toBeInTheDocument();
    expect(questions()).toEqual(['¿Atienden los sábados?', '¿Cómo se paga?', '¿Duele la extracción?']);
    expect(
      within(screen.getByRole('heading', { level: 2, name: 'Extracción' })).getByRole('link', { name: 'Extracción' }),
    ).toHaveAttribute('href', '/tratamientos/extraccion');
    expect(screen.getByRole('status', { hidden: true })).not.toBeVisible();
  });

  it('searches in the questions and their answers, with or without accents', async () => {
    const { user } = openFaqs([general, extraction]);
    const search = await screen.findByLabelText('Buscar una pregunta');

    await user.type(search, 'EXTRACCION duele');
    expect(questions()).toEqual(['¿Duele la extracción?']);

    // "anestesia" solo está en la respuesta.
    await user.clear(search);
    await user.type(search, 'anestesia');
    expect(questions()).toEqual(['¿Duele la extracción?']);

    await user.clear(search);
    await user.type(search, 'yape');
    expect(questions()).toEqual(['¿Cómo se paga?']);
  });

  it('says it when no question has those words', async () => {
    const { user } = openFaqs([general, extraction]);

    await user.type(await screen.findByLabelText('Buscar una pregunta'), 'ortodoncia invisible');

    expect(questions()).toEqual([]);
    expect(screen.getByRole('status')).toHaveTextContent(
      'No encontramos preguntas con esas palabras. Prueba con otras.',
    );
  });

  it('leaves in sight one topic at a time, and choosing it clears what was written', async () => {
    const { user } = openFaqs([general, extraction]);
    const search = await screen.findByLabelText('Buscar una pregunta');
    const topics = within(screen.getByRole('navigation', { name: 'Temas' }));

    await user.type(search, 'yape');
    await user.click(topics.getByRole('link', { name: 'Extracción' }));

    expect(search).toHaveValue('');
    expect(questions()).toEqual(['¿Duele la extracción?']);
    expect(topics.getByRole('link', { name: 'Extracción' })).toHaveAttribute('aria-current', 'true');

    // Y escribir vuelve a buscar en todos los temas.
    await user.type(search, 'sabados');
    expect(questions()).toEqual(['¿Atienden los sábados?']);
    expect(topics.getByRole('link', { name: 'Todas' })).toHaveAttribute('aria-current', 'true');
  });

  it('goes straight to the questions when there is a single topic', async () => {
    openFaqs([general]);

    expect(await screen.findByText('¿Atienden los sábados?')).toBeInTheDocument();
    expect(screen.queryByRole('navigation', { name: 'Temas' })).not.toBeInTheDocument();
    expect(screen.queryByRole('heading', { level: 2, name: 'Antes de venir' })).not.toBeInTheDocument();
  });

  it('says the questions are coming when none is published', async () => {
    openFaqs([]);

    expect(await screen.findByText('Pronto publicaremos las preguntas más comunes.')).toBeInTheDocument();
    expect(screen.queryByLabelText('Buscar una pregunta')).not.toBeInTheDocument();
  });
});
