import type { CSSProperties } from 'react';
import type { GoogleRating as Rating } from '@/api/types';

/** Estrellas de Google: el puntaje que copiaron en la puesta a punto de la web, con enlace a las opiniones si lo hay. */
export function GoogleRating({ rating }: { rating: Rating }) {
  const score = rating.rating.toFixed(1).replace('.', ',');
  const count = rating.count ? ` · ${rating.count} ${rating.count === 1 ? 'opinión' : 'opiniones'}` : '';
  const content = (
    <>
      <span className="rating-stars" style={{ '--rating': rating.rating } as CSSProperties} aria-hidden="true" />
      <span>
        <strong>{score}</strong> de 5 en Google{count}
      </span>
    </>
  );

  return rating.url ? (
    <a className="rating" href={rating.url} rel="noopener" target="_blank">
      {content}
    </a>
  ) : (
    <p className="rating">{content}</p>
  );
}
