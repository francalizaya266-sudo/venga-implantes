import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { Contact } from '@/components/site/Contact';
import { renderPiece } from '@/test/app';
import { site } from '@/test/fixtures';

describe('Contact', () => {
  it('keeps the written address under the map, so the box is never empty if Google does not load', () => {
    const clinic = { ...site().clinic, map_embed_url: 'https://maps.example/embed' };
    renderPiece(<Contact clinic={clinic} />);

    const map = screen.getByTitle('Mapa: Jr. Apurímac 648, Juliaca');
    const box = map.parentElement as HTMLElement;
    expect(box.querySelector('.map-behind')).toHaveTextContent('Clínica VegaJr. Apurímac 648, Juliaca');
    // Va antes que el mapa: queda debajo y, cuando el mapa carga, lo tapa.
    expect(box.firstElementChild).toHaveClass('map-behind');
  });
});
