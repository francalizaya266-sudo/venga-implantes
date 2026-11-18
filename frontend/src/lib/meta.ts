import type { PageMeta } from '@/api/types';

const MARK = 'data-page-meta';

function setMeta(attribute: 'name' | 'property', key: string, content: string | null): void {
  let element = document.head.querySelector<HTMLMetaElement>(`meta[${attribute}="${key}"]`);

  if (content === null) {
    element?.remove();

    return;
  }

  if (!element) {
    element = document.createElement('meta');
    element.setAttribute(attribute, key);
    element.setAttribute(MARK, '');
    document.head.append(element);
  }

  element.content = content;
}

function setCanonical(url: string | null): void {
  let element = document.head.querySelector<HTMLLinkElement>('link[rel="canonical"]');

  if (url === null) {
    element?.remove();

    return;
  }

  if (!element) {
    element = document.createElement('link');
    element.rel = 'canonical';
    element.setAttribute(MARK, '');
    document.head.append(element);
  }

  element.href = url;
}

/**
 * Pone en el documento lo que la página dice de sí misma: título, descripción, imagen al compartir y datos
 * estructurados. Al abrir la web, Laravel ya los escribió en el HTML (es lo que leen Google y WhatsApp); esto
 * los mantiene al día al pasar de una página a otra sin recargar.
 */
export function applyMeta(meta: PageMeta): void {
  const url = window.location.origin + window.location.pathname;

  document.title = meta.title;
  setMeta('name', 'description', meta.description);
  setMeta('name', 'robots', meta.noindex ? 'noindex' : null);
  setCanonical(meta.noindex ? null : url);
  setMeta('property', 'og:title', meta.og_title);
  setMeta('property', 'og:description', meta.description);
  setMeta('property', 'og:url', url);
  setMeta('property', 'og:image', meta.image);

  document.head.querySelectorAll('script[type="application/ld+json"]').forEach((element) => element.remove());

  for (const data of meta.json_ld) {
    const script = document.createElement('script');
    script.type = 'application/ld+json';
    script.setAttribute(MARK, '');
    script.textContent = JSON.stringify(data);
    document.head.append(script);
  }
}

/**
 * Una página de error: su título y que Google no la guarde. Lo demás de la página anterior se retira.
 * Al compartirla se ve el título solo; en la pestaña, con el nombre del consultorio.
 */
export function applyErrorMeta(title: string, clinic?: string): void {
  document.title = clinic ? `${title} · ${clinic}` : title;
  setMeta('name', 'robots', 'noindex');
  setCanonical(null);
  setMeta('property', 'og:title', title);
  document.head.querySelectorAll('script[type="application/ld+json"]').forEach((element) => element.remove());
}
