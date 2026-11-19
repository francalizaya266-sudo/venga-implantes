import { screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import type { Campaign, PromotionData } from '@/api/types';
import { mockApi, renderApp } from '@/test/app';
import { meta, page } from '@/test/fixtures';

const API = '/api/v1/promotions/limpieza-de-verano';
const PATH = '/promociones/limpieza-de-verano';

function promotionData(
  campaign: Partial<PromotionData['campaign']> = {},
  changes: Partial<PromotionData> = {},
): PromotionData {
  return {
    campaign: {
      slug: 'limpieza-de-verano',
      name: 'Limpieza de verano',
      summary: 'Tu limpieza con descuento hasta fin de mes.',
      image_url: null,
      until: 'Hasta el 30 de noviembre',
      discount: '20 % de descuento',
      discount_beside_name: null,
      treatment: { slug: 'limpieza-dental', name: 'Limpieza dental' },
      description_html: null,
      promo_code: 'VERANO',
      has_slots_left: true,
      whatsapp_text: 'Hola, quiero la promoción "Limpieza de verano".',
      price: { normal: 'Desde S/ 120', now: 'desde S/ 96' },
      package: null,
      ...campaign,
    },
    by_evaluation: false,
    booking: { tratamiento: 'limpieza-dental', codigo: 'VERANO', origen: 'campaign' },
    ...changes,
  };
}

const whitening: Campaign = {
  slug: 'blanqueamiento-2x1',
  name: 'Blanqueamiento 2x1',
  summary: 'Dos personas por el precio de una.',
  image_url: null,
  until: 'Hasta el 15 de diciembre',
  discount: null,
  discount_beside_name: null,
  treatment: null,
};

function openPromotion(data: PromotionData) {
  mockApi({ [API]: page(data) });

  return renderApp(PATH);
}

const main = () => within(screen.getByRole('main'));

describe('PromotionPage', () => {
  it('shows the normal price crossed out, the code, and books with the promotion', async () => {
    openPromotion(promotionData());

    expect(await screen.findByRole('heading', { level: 1, name: 'Limpieza de verano' })).toBeInTheDocument();
    expect(screen.getByText('Precio normal Desde S/ 120').tagName).toBe('S');
    expect(screen.getByText('desde S/ 96')).toBeInTheDocument();
    expect(screen.getByText('VERANO')).toBeInTheDocument();
    expect(main().getByRole('link', { name: 'Limpieza dental' })).toHaveAttribute(
      'href',
      '/tratamientos/limpieza-dental',
    );
    expect(main().getByRole('link', { name: 'Reservar con la promoción' })).toHaveAttribute(
      'href',
      '/reservar?tratamiento=limpieza-dental&codigo=VERANO&origen=campaign',
    );
    expect(main().getByRole('link', { name: 'Escribir por WhatsApp' })).toHaveAttribute(
      'href',
      `https://wa.me/51912345678?text=${encodeURIComponent('Hola, quiero la promoción "Limpieza de verano".')}`,
    );
    expect(screen.getByText(/El descuento se aplica al pagar en el consultorio/)).toBeInTheDocument();
  });

  it('books the evaluation and keeps the promotion when the treatment starts with one', async () => {
    openPromotion(
      promotionData(
        { treatment: { slug: 'ortodoncia', name: 'Ortodoncia' }, price: null },
        { by_evaluation: true, booking: { tratamiento: 'evaluacion', codigo: 'VERANO', origen: 'campaign' } },
      ),
    );

    expect(await screen.findByText(/Ortodoncia se programa después de una evaluación/)).toBeInTheDocument();
    expect(main().getByRole('link', { name: 'Reservar evaluación con la promoción' })).toHaveAttribute(
      'href',
      '/reservar?tratamiento=evaluacion&codigo=VERANO&origen=campaign',
    );
  });

  it('lists what a package includes, what is saved and how it can be paid', async () => {
    openPromotion(
      promotionData(
        {
          price: null,
          promo_code: null,
          package: {
            items: ['Limpieza dental', 'Blanqueamiento'],
            normal_price: 'S/ 600.00',
            price: 'S/ 450.00',
            savings: 'S/ 150.00',
            for_two: true,
            payment_options: ['Al contado: S/ 450.00'],
            description: null,
          },
        },
        { booking: { promo: 'limpieza-de-verano', origen: 'campaign' } },
      ),
    );

    const pack = within(await screen.findByRole('region', { name: 'Qué incluye' }));
    expect(pack.getAllByRole('listitem').map((item) => item.textContent)).toEqual([
      'Limpieza dental',
      'Blanqueamiento',
    ]);
    expect(pack.getByText('Ahorras S/ 150.00')).toBeInTheDocument();
    expect(pack.getByText('2x1:')).toBeInTheDocument();
    expect(pack.getByText('Al contado: S/ 450.00')).toBeInTheDocument();
    expect(screen.queryByText(/Código:/)).not.toBeInTheDocument();
    expect(screen.getByText(/El precio y la forma de pago se acuerdan en la consulta/)).toBeInTheDocument();
  });

  it('does not offer to book once the places ran out', async () => {
    openPromotion(promotionData({ has_slots_left: false }));

    expect(await screen.findByText(/Los cupos de esta promoción ya se agotaron/)).toBeInTheDocument();
    expect(main().queryByRole('link', { name: 'Reservar con la promoción' })).not.toBeInTheDocument();
  });

  it('offers the promotions still running when this one ended', async () => {
    mockApi({
      [API]: {
        status: 404,
        body: {
          message: 'Esta promoción ya terminó',
          data: { ended: 'Limpieza de verano', campaigns: [whitening] },
          meta: meta({ noindex: true }),
        },
      },
    });
    renderApp(PATH);

    expect(await screen.findByRole('heading', { level: 1, name: 'Esta promoción ya terminó' })).toBeInTheDocument();
    expect(screen.getByText(/“Limpieza de verano” ya no está vigente/)).toBeInTheDocument();
    expect(main().getByRole('link', { name: /Blanqueamiento 2x1/ })).toHaveAttribute(
      'href',
      '/promociones/blanqueamiento-2x1',
    );
  });

  it('says it when the promotion never existed and there is none running', async () => {
    mockApi({
      [API]: {
        status: 404,
        body: {
          message: 'No encontramos esta promoción',
          data: { ended: null, campaigns: [] },
          meta: meta({ noindex: true }),
        },
      },
    });
    renderApp(PATH);

    expect(await screen.findByRole('heading', { level: 1, name: 'No encontramos esta promoción' })).toBeInTheDocument();
    expect(screen.getByText(/Ahora no tenemos promociones vigentes/)).toBeInTheDocument();
    expect(main().getByRole('link', { name: 'reservar tu cita' })).toHaveAttribute('href', '/reservar');
  });
});
