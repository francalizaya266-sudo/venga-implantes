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

/**
 * El nombre de un doctor como se dice dentro de una frase, con su artículo: "con la Dra. Ana Torres", "con el Dr.
 * Luis Ramos". Sin "Dr." ni "Dra." delante, va tal cual. La API arma sus frases con la misma regla
 * (name_with_article del doctor).
 */
export function withArticle(name: string): string {
  if (/^(dra\.|doctora)\s/i.test(name)) {
    return `la ${name}`;
  }
  if (/^(dr\.|doctor)\s/i.test(name)) {
    return `el ${name}`;
  }
  return name;
}
