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

describe('site.css', () => {
  it('on the phone and the tablet, the services end with the wide button to see them all', () => {
    const shown = rules(css).filter(
      (rule) => rule.selectors.includes('.services-all') && /(^|;)\s*display:\s*block/.test(rule.body),
    );

    expect(shown.map((rule) => rule.at)).toEqual([['@media (max-width: 1023px)']]);
  });
});
