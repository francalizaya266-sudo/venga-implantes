/** Sin mayúsculas ni tildes, para buscar "extraccion" y encontrar "Extracción". */
export function plain(text: string): string {
  return text.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
}

/** El texto de un HTML que llega de la API, sin sus etiquetas. */
export function textOf(html: string): string {
  return new DOMParser().parseFromString(html, 'text/html').body.textContent ?? '';
}

/** Un precio ("desde S/ 150") que, si no entra en una línea, baja entero: nunca separa "S/" de la cifra. */
export function keepAmountTogether(price: string): string {
  return price.replaceAll('S/ ', 'S/\u00a0');
}
