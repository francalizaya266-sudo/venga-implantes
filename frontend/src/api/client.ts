/**
 * Cliente de la API de Laravel. El frontend vive en el mismo dominio, así que las peticiones llevan las cookies
 * de siempre (la sesión y el celular recordado) y no hay tokens que guardar.
 */

export type Query = Record<string, string | number | null | undefined>;

interface ErrorBody {
  message?: string | null;
  code?: string;
  errors?: Record<string, string[]>;
  [key: string]: unknown;
}

/** Una respuesta de la API que no fue 2xx: trae el estado, el mensaje para el paciente y los errores por campo. */
export class ApiError extends Error {
  readonly status: number;
  readonly body: ErrorBody;

  constructor(status: number, body: ErrorBody) {
    super(body.message ?? `La petición falló (${status}).`);
    this.name = 'ApiError';
    this.status = status;
    this.body = body;
  }

  get code(): string | undefined {
    return this.body.code;
  }

  /** El primer mensaje de cada campo que no pasó la validación. */
  get fieldErrors(): Record<string, string> {
    return Object.fromEntries(
      Object.entries(this.body.errors ?? {}).map(([field, messages]) => [field, messages[0] ?? '']),
    );
  }
}

interface Preloaded {
  status: number;
  body: unknown;
}

/** Misma forma para una dirección venga de Laravel o de aquí: la ruta y sus parámetros en orden alfabético. */
export function cacheKey(url: string): string {
  const parsed = new URL(url, window.location.origin);
  const params = [...parsed.searchParams.entries()].sort(([a], [b]) => a.localeCompare(b));
  const search = new URLSearchParams(params).toString();

  return parsed.pathname.replace(/\/$/, '') + (search ? `?${search}` : '');
}

/**
 * Lo que Laravel ya dejó escrito en la página al servirla (los datos de la página que se abrió): se usa una
 * sola vez, para pintar sin esperar a la red. Lo demás se pide a la API.
 */
function readPreload(): Map<string, Preloaded> {
  const preload = new Map<string, Preloaded>();
  const element = document.getElementById('preload');

  if (element?.textContent) {
    try {
      for (const [url, response] of Object.entries(JSON.parse(element.textContent) as Record<string, Preloaded>)) {
        preload.set(cacheKey(url), response);
      }
    } catch {
      // Sin datos de arranque válidos, todo se pide a la API.
    }
  }

  return preload;
}

let preload: Map<string, Preloaded> | null = null;

function takePreloaded(url: string): Preloaded | undefined {
  preload ??= readPreload();
  const key = cacheKey(url);
  const found = preload.get(key);
  preload.delete(key);

  return found;
}

/**
 * Las respuestas correctas que Laravel dejó en la página, para sembrar con ellas la caché antes de pintar: así
 * la página con que se abre la web sale completa a la primera, sin un instante en blanco.
 */
export function preloadedData(): [key: string, body: unknown][] {
  preload ??= readPreload();
  const ready: [string, unknown][] = [];

  for (const [key, response] of preload) {
    if (response.status >= 200 && response.status < 300) {
      ready.push([key, response.body]);
      preload.delete(key);
    }
  }

  return ready;
}

/** Solo para las pruebas: vuelve a leer los datos de arranque de la página. */
export function resetPreload(): void {
  preload = null;
}

/** La dirección de un recurso de la API: apiUrl('treatments/limpieza') → "/api/v1/treatments/limpieza". */
export function apiUrl(path: string, query: Query = {}): string {
  const params = new URLSearchParams();

  for (const [key, value] of Object.entries(query)) {
    if (value !== null && value !== undefined && value !== '') {
      params.set(key, String(value));
    }
  }

  const base = path.startsWith('/') ? path : `/api/v1/${path}`;
  const search = params.toString();

  return search ? `${base}${base.includes('?') ? '&' : '?'}${search}` : base;
}

function settle<T>(status: number, body: unknown): T {
  if (status >= 200 && status < 300) {
    return body as T;
  }

  throw new ApiError(status, (body ?? {}) as ErrorBody);
}

async function parse<T>(response: Response): Promise<T> {
  const text = await response.text();
  let body: unknown;

  try {
    body = text ? JSON.parse(text) : null;
  } catch {
    body = { message: 'No pudimos leer la respuesta del servidor. Inténtalo de nuevo.' };
  }

  return settle<T>(response.status, body);
}

/** El token contra peticiones falsificadas que Laravel deja en una cookie. */
function xsrfToken(): string {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

  return match?.[1] ? decodeURIComponent(match[1]) : '';
}

export async function get<T>(url: string): Promise<T> {
  const preloaded = takePreloaded(url);

  if (preloaded) {
    return settle<T>(preloaded.status, preloaded.body);
  }

  return parse<T>(await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }));
}

export async function post<T>(url: string, data: unknown = {}): Promise<T> {
  const send = () =>
    fetch(url, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
      credentials: 'same-origin',
      body: JSON.stringify(data),
    });

  let response = await send();

  // La sesión venció mientras la página estaba abierta: se renueva el token y se intenta una vez más.
  if (response.status === 419) {
    await fetch(apiUrl('site'), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    response = await send();
  }

  return parse<T>(response);
}
