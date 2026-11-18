/** Sin mayúsculas ni tildes, para buscar "extraccion" y encontrar "Extracción". */
export function plain(text: string): string {
  return text.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
}

/** El texto de un HTML que llega de la API, sin sus etiquetas. */
export function textOf(html: string): string {
  return new DOMParser().parseFromString(html, 'text/html').body.textContent ?? '';
}
