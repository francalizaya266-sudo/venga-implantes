import { screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import type { TreatmentData } from '@/api/types';
import { mockApi, renderApp } from '@/test/app';
import { page, treatment } from '@/test/fixtures';

const API = '/api/v1/treatments/ortodoncia';

function treatmentData(changes: Partial<TreatmentData> = {}): TreatmentData {
  return {
    treatment: {
      ...treatment({
        id: 4,
        slug: 'ortodoncia',
        name: 'Ortodoncia',
        summary: 'Dientes alineados con brackets.',
        art: 'ortodoncia',
        duration_minutes: 60,
        sessions_text: 'De 12 a 24 controles',
        price: 'Desde S/ 2500',
      }),
      description_html: '<p>Los brackets se colocan en una cita.</p>',
      image_url: null,
    },
    campaign: null,
    booking: { treatment: 'ortodoncia', promo: null, direct: true },
    next_free: null,
    doctors: [],
    faqs: [],
    cases: [],
    reviews: [],
    others: [],
    ...changes,
  };
}

/** Lo de la página, sin el encabezado ni el pie (que también tienen "Reservar cita"). */
const main = () => within(screen.getByRole('main'));

function openTreatment(data: TreatmentData) {
  mockApi({ [API]: page(data) });

  return renderApp('/tratamientos/ortodoncia');
}

describe('TreatmentPage', () => {
  it('says what the treatment is, how long it takes and what it costs, and books it directly', async () => {
    openTreatment(treatmentData());

    expect(await screen.findByRole('heading', { level: 1, name: 'Ortodoncia' })).toBeInTheDocument();
    expect(screen.getByText('Dientes alineados con brackets.')).toBeInTheDocument();
    expect(screen.getByText('60 min aprox.')).toBeInTheDocument();
    expect(screen.getByText('De 12 a 24 controles')).toBeInTheDocument();
    expect(screen.getByText('Desde S/ 2500')).toBeInTheDocument();
    expect(screen.getByText('Los brackets se colocan en una cita.')).toBeInTheDocument();
    expect(screen.getByText('Pagas en el consultorio con Yape, Plin, tarjeta o efectivo.')).toBeInTheDocument();

    expect(screen.queryByText('Empieza con una evaluación')).not.toBeInTheDocument();
    expect(main().getByRole('link', { name: 'Reservar cita' })).toHaveAttribute(
      'href',
      '/reservar?tratamiento=ortodoncia',
    );
    expect(main().getByRole('link', { name: 'Escribir por WhatsApp' })).toHaveAttribute(
      'href',
      `https://wa.me/51912345678?text=${encodeURIComponent('Hola, quisiera información sobre ortodoncia.')}`,
    );
  });

  it('starts with an evaluation when the treatment is not booked on its own, and says it before the button', async () => {
    openTreatment(
      treatmentData({
        treatment: { ...treatmentData().treatment, price: null },
        booking: { treatment: 'evaluacion', promo: null, direct: false },
        next_free: {
          title: 'Próximo horario para tu evaluación',
          date: '2026-11-04',
          day: 'Miércoles 4 de noviembre',
          times: [{ time: '09:00', label: '9:00 a. m.' }],
          treatment: { slug: 'evaluacion', name: 'Evaluación', duration_minutes: 30 },
          promo: null,
        },
      }),
    );

    expect(await screen.findByText('Empieza con una evaluación')).toBeInTheDocument();
    // Sin precio publicado, se dice dónde se sabe y no se explica cómo pagar.
    expect(screen.getByText('En tu evaluación')).toBeInTheDocument();
    expect(screen.queryByText('Pagas en el consultorio con Yape, Plin, tarjeta o efectivo.')).not.toBeInTheDocument();
    expect(main().getByRole('link', { name: 'Reservar evaluación' })).toHaveAttribute(
      'href',
      '/reservar?tratamiento=evaluacion',
    );
    expect(screen.getByRole('heading', { level: 2, name: 'Próximo horario para tu evaluación' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: '9:00 a. m.' })).toHaveAttribute(
      'href',
      '/reservar?tratamiento=evaluacion&fecha=2026-11-04&hora=09%3A00',
    );
  });

  it('with a promotion running, crosses out the normal price and books with the promotion', async () => {
    openTreatment(
      treatmentData({
        campaign: {
          slug: 'ortodoncia-de-verano',
          name: 'Ortodoncia de verano',
          summary: null,
          image_url: null,
          until: 'Hasta el 30 de noviembre',
          discount: '20 % de descuento',
          discount_beside_name: null,
          price: 'desde S/ 2000',
          until_lower: 'hasta el 30 de noviembre',
        },
        booking: { treatment: 'ortodoncia', promo: 'ortodoncia-de-verano', direct: true },
      }),
    );

    expect(await screen.findByText('Precio con la promoción')).toBeInTheDocument();
    expect(screen.getByText('Desde S/ 2500').tagName).toBe('S');
    expect(screen.getByText(/desde S\/ 2000/)).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Ortodoncia de verano' })).toHaveAttribute(
      'href',
      '/promociones/ortodoncia-de-verano',
    );
    expect(screen.getByText(/hasta el 30 de noviembre/)).toBeInTheDocument();
    expect(main().getByRole('link', { name: 'Reservar con la promoción' })).toHaveAttribute(
      'href',
      '/reservar?tratamiento=ortodoncia&promo=ortodoncia-de-verano',
    );
  });

  it('adds the questions about the treatment and the other services, and only the sections that have something', async () => {
    openTreatment(
      treatmentData({
        faqs: [{ id: 1, question: '¿Duele ponerse brackets?', answer_html: '<p>Molesta los primeros días.</p>' }],
        others: [treatment()],
      }),
    );

    expect(await screen.findByRole('heading', { level: 2, name: 'Preguntas sobre ortodoncia' })).toBeInTheDocument();
    expect(screen.getByText('¿Duele ponerse brackets?')).toBeInTheDocument();
    const others = within(screen.getByRole('region', { name: 'Otros servicios' }));
    expect(others.getByRole('link', { name: /Limpieza dental/ })).toHaveAttribute(
      'href',
      '/tratamientos/limpieza-dental',
    );
    expect(screen.queryByRole('heading', { level: 2, name: 'Quién lo realiza' })).not.toBeInTheDocument();
    expect(screen.queryByRole('heading', { level: 2, name: 'Opiniones' })).not.toBeInTheDocument();
  });

  it('shows the not found page for a treatment the API does not have', async () => {
    mockApi({
      [API]: {
        status: 404,
        body: { message: 'No encontramos lo que buscas. Puede que el enlace esté incompleto o ya no exista.' },
      },
    });
    renderApp('/tratamientos/ortodoncia');

    expect(await screen.findByRole('heading', { level: 1, name: 'No encontramos esta página' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Ir al inicio' })).toHaveAttribute('href', '/');
  });
});
