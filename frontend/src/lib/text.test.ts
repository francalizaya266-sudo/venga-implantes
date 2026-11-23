import { describe, expect, it } from 'vitest';
import { keepAmountTogether } from '@/lib/text';

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
