import type { Review } from '@/api/types';

export function Reviews({ reviews }: { reviews: Review[] }) {
  return (
    <div className="quotes">
      {reviews.map((review) => (
        <figure key={review.id} className="quote">
          <p className="stars" aria-label={`${review.rating} de 5 estrellas`}>
            {'★'.repeat(review.rating)}
            <span className="off">{'★'.repeat(5 - review.rating)}</span>
          </p>
          <blockquote>{review.body}</blockquote>
          <figcaption>
            {review.author}{' '}
            <span>
              {review.url ? (
                <a href={review.url} rel="noopener nofollow" target="_blank">
                  en {review.source}
                </a>
              ) : (
                <>en {review.source}</>
              )}
              {review.date ? ` · ${review.date}` : null}
            </span>
          </figcaption>
        </figure>
      ))}
    </div>
  );
}
