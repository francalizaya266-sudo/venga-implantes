import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

// Las pruebas no cargan las hojas de estilo (css: false en vite.config.ts): esta la lee como texto.
const css = readFileSync(join(import.meta.dirname, 'site.css'), 'utf8');

/** Una regla de la hoja: sus selectores, lo que declara y las @media que la envuelven. */
interface Rule {
  at: string[];
  selectors: string[];
  body: string;
}

/** Las reglas de site.css, sin comentarios. */
function rules(source: string): Rule[] {
  const found: Rule[] = [];
  const at: string[] = [];
  let rule: string[] | null = null;
  let buffer = '';
  for (const char of source.replace(/\/\*[\s\S]*?\*\//g, '')) {
    if (char === '{') {
      const prelude = buffer.trim().replace(/\s+/g, ' ');
      buffer = '';
      if (prelude.startsWith('@')) {
        at.push(prelude);
      } else {
        // Las comas que separan selectores, no las de adentro de :is() o :not().
        rule = prelude.split(/,(?![^(]*\))/).map((selector) => selector.trim());
      }
    } else if (char === '}') {
      if (rule) {
        found.push({ at: [...at], selectors: rule, body: buffer.trim() });
        rule = null;
      } else {
        at.pop();
      }
      buffer = '';
    } else {
      buffer += char;
    }
  }
  return found;
}

/** Lo que se toca en la web: con el dedo (o en todo aparato, si tiene caja propia) mide al menos 44 px de alto. */
const TOUCHED = [
  '.wordmark',
  '.menu > summary',
  '.header-back',
  '.btn.small',
  '.pill',
  '.arrow',
  '.crumbs a',
  '.next-free .more',
  '.faq-group h2 a',
  '.team.full .doctor .treatments a',
  '.site-footer ul a',
  '.footer-contact a',
  '.footer-bottom a',
  '.site-footer.minimal .wrap > p a',
];

describe('site.css', () => {
  it('with a finger, what is touched on the web measures 44 px', () => {
    const tall = rules(css)
      .filter(
        (rule) =>
          /(^|;)\s*min-height:\s*44px/.test(rule.body) && rule.at.every((at) => /\(pointer:\s*coarse\)/.test(at)),
      )
      .flatMap((rule) => rule.selectors);

    expect(TOUCHED.filter((selector) => !tall.includes(selector))).toEqual([]);
  });

  it('with very large letters, a long word breaks with its hyphen and what went in twos goes one under the other', () => {
    const large = rules(css).filter((rule) => rule.at.includes('@container (max-width: 16em)'));
    const body = (selector: string) => large.find((rule) => rule.selectors.includes(selector))?.body ?? '';

    expect(body('main *')).toMatch(/(^|;)\s*hyphens:\s*auto/);
    // Las de menos de 10 letras ("quienes", "Fernández") bajan enteras.
    expect(body('main *')).toMatch(/hyphenate-limit-chars:\s*10 /);
    expect(body('main :is(.services-grid, .free-times, .times, .trust)')).toMatch(
      /grid-template-columns:\s*minmax\(0, 1fr\)/,
    );
    expect(body('.site-footer .footer-cta')).toMatch(/grid-template-columns:\s*minmax\(0, 1fr\)/);
    expect(body('.site-footer .footer-cta :is(img, .btn svg)')).toMatch(/display:\s*none/);
  });

  it('with very large letters, what went beside a text goes under it, so no column is too narrow to read', () => {
    const large = rules(css).filter((rule) => rule.at.includes('@container (max-width: 16em)'));
    const body = (selector: string) => large.find((rule) => rule.selectors.includes(selector))?.body ?? '';
    const oneColumn = /grid-template-columns:\s*minmax\(0, 1fr\)/;

    // "Teléfono" y su número se leían letra por letra al lado del botón "Llamar".
    expect(body('main .ways li .pill')).toMatch(/grid-column:\s*1 \/ -1/);
    // "Duración" se partía en "Dura" y "ción" al lado de "120 min aprox.".
    expect(body('main .facts-row div')).toMatch(/display:\s*grid/);
    expect(body('main :is(.team:not(.full) .doctor, .team.full .doctor, .meet)')).toMatch(oneColumn);
    expect(body('main .price-list a')).toMatch(oneColumn);
    expect(body('main .price-list a > :is(.icon, .name, .price, .desc, .meta, .more)')).toMatch(/grid-column:\s*1;/);
    expect(body('main .how li')).toMatch(oneColumn);
    expect(body('main .promo-art')).toMatch(/display:\s*none/);
  });

  it('with very large letters, the open menu slides and its last page stays above the bar at the bottom', () => {
    const menu = rules(css).find(
      (rule) =>
        rule.at.includes('@container (max-width: 11.5em)') && rule.selectors.includes('.site-header .menu-panel'),
    );

    expect(menu?.body).toMatch(/max-height:\s*calc\(100dvh - 100% - 1px\)/);
    expect(menu?.body).toMatch(/overflow-y:\s*auto/);
    expect(menu?.body).toMatch(/padding-bottom:\s*calc\(104px/);
  });

  it('the title of a page, like the one of the home, is never bigger than what fits on a narrow phone', () => {
    const pages = ['h1', '.hero h1', '.page-band h1', '.page-head h1', '.treatment-head h1', '.promo-page h1'];
    const titles = rules(css).filter(
      (rule) =>
        rule.at.length === 0 &&
        rule.selectors.some((selector) => pages.includes(selector)) &&
        /font-size/.test(rule.body),
    );

    expect(titles.map((rule) => rule.selectors[0])).toEqual(pages);
    for (const title of titles) {
      expect(title.body).toMatch(/font-size:\s*clamp\(min\([^,]+,\s*[\d.]+vw\)/);
    }
  });

  it('on the phone and the tablet, the services end with the wide button to see them all', () => {
    const shown = rules(css).filter(
      (rule) => rule.selectors.includes('.services-all') && /(^|;)\s*display:\s*block/.test(rule.body),
    );

    expect(shown.map((rule) => rule.at)).toEqual([['@media (max-width: 1023px)']]);
  });
});
