import { useEffect, useRef, useState, type HTMLAttributes } from 'react';

/**
 * Una sección de la página. Con JavaScript aparece suave al llegar a ella (site.css: ".js main .section");
 * donde el navegador no puede avisar que ya se ve, se muestra de una vez.
 */
export function Section({ className = '', children, ...rest }: HTMLAttributes<HTMLElement>) {
  const ref = useRef<HTMLElement>(null);
  const [seen, setSeen] = useState(() => typeof IntersectionObserver === 'undefined');

  useEffect(() => {
    const element = ref.current;

    if (seen || !element) {
      return;
    }

    const observer = new IntersectionObserver(
      (entries) => {
        if (entries.some((entry) => entry.isIntersecting)) {
          setSeen(true);
        }
      },
      { rootMargin: '0px 0px -8% 0px' },
    );
    observer.observe(element);

    return () => observer.disconnect();
  }, [seen]);

  return (
    <section ref={ref} className={`section ${className}${seen ? ' is-in' : ''}`.replace(/\s+/g, ' ').trim()} {...rest}>
      {children}
    </section>
  );
}
