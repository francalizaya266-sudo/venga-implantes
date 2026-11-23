import { describe, expect, it } from 'vitest';
import { keepAmountTogether, withArticle } from '@/lib/text';

describe('keepAmountTogether', () => {
  it('joins the sign to the amount, so a price that does not fit goes down whole', () => {
    expect(keepAmountTogether('desde S/ 2,500')).toBe('desde S/ 2,500');
    // "desde" sigue pudiendo quedar arriba.
    expect(keepAmountTogether('desde S/ 150').split(' ')).toEqual(['desde', 'S/ 150']);
  });

  it('leaves alone a text without an amount in soles', () => {
    expect(keepAmountTogether('Precio en la evaluación')).toBe('Precio en la evaluación');
  });
});

describe('withArticle', () => {
  it.each([
    ['Dra. Lucía Fernández Paredes', 'la Dra. Lucía Fernández Paredes'],
    ['Dr. Luis Ramos', 'el Dr. Luis Ramos'],
    ['Doctora Ana Torres', 'la Doctora Ana Torres'],
    ['Lucía Fernández', 'Lucía Fernández'],
    ['Drago Pérez', 'Drago Pérez'],
  ])('says "%s" inside a sentence as "%s"', (name, said) => {
    expect(withArticle(name)).toBe(said);
  });
});
