import { describe, expect, it } from 'vitest';
import { applyErrorMeta, applyMeta } from '@/lib/meta';
import { meta } from '@/test/fixtures';

const content = (selector: string) => document.head.querySelector<HTMLMetaElement>(selector)?.content;
const canonical = () => document.head.querySelector<HTMLLinkElement>('link[rel="canonical"]');
const structured = () =>
  [...document.head.querySelectorAll('script[type="application/ld+json"]')].map((script) => script.textContent);

describe('applyMeta', () => {
  it('writes in the document what the page says about itself', () => {
    window.history.replaceState(null, '', '/tratamientos/limpieza-dental?origen=facebook');

    applyMeta(
      meta({
        title: 'Limpieza dental en Juliaca · Clínica Vega',
        og_title: 'Limpieza dental',
        description: 'Deja tus dientes sin sarro.',
        json_ld: [{ '@type': 'BreadcrumbList' }],
      }),
    );

    expect(document.title).toBe('Limpieza dental en Juliaca · Clínica Vega');
    expect(content('meta[name="description"]')).toBe('Deja tus dientes sin sarro.');
    expect(content('meta[property="og:title"]')).toBe('Limpieza dental');
    expect(content('meta[property="og:image"]')).toBe('http://localhost/imagen-al-compartir.jpg');
    // La dirección propia de la página no lleva de dónde llegó el visitante.
    expect(canonical()?.href).toBe('http://localhost:3000/tratamientos/limpieza-dental');
    expect(content('meta[name="robots"]')).toBeUndefined();
    expect(structured()).toEqual(['{"@type":"BreadcrumbList"}']);
  });

  it('replaces what the previous page left', () => {
    applyMeta(meta({ description: 'La primera.', json_ld: [{ '@type': 'Dentist' }] }));
    applyMeta(meta({ title: 'Mi cuenta · Clínica Vega', description: null, noindex: true }));

    expect(document.title).toBe('Mi cuenta · Clínica Vega');
    expect(content('meta[name="description"]')).toBeUndefined();
    expect(content('meta[name="robots"]')).toBe('noindex');
    expect(canonical()).toBeNull();
    expect(structured()).toEqual([]);
  });
});

describe('applyErrorMeta', () => {
  it('titles the error and keeps it out of Google', () => {
    applyMeta(meta({ json_ld: [{ '@type': 'Dentist' }] }));

    applyErrorMeta('No encontramos esta página', 'Clínica Vega');

    expect(document.title).toBe('No encontramos esta página · Clínica Vega');
    expect(content('meta[property="og:title"]')).toBe('No encontramos esta página');
    expect(content('meta[name="robots"]')).toBe('noindex');
    expect(canonical()).toBeNull();
    expect(structured()).toEqual([]);
  });

  it('uses the title alone until the name of the clinic arrives', () => {
    applyErrorMeta('Algo falló de nuestro lado');

    expect(document.title).toBe('Algo falló de nuestro lado');
  });
});
