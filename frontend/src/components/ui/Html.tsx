import type { HTMLAttributes } from 'react';

/**
 * Texto con formato que llega de la API ya convertido a HTML y limpio (lo que se escribe en el panel pasa por
 * App\Support\Markdown, que no deja pasar etiquetas ni enlaces peligrosos).
 */
export function Html({ html, ...rest }: { html: string } & HTMLAttributes<HTMLDivElement>) {
  return <div {...rest} dangerouslySetInnerHTML={{ __html: html }} />;
}
