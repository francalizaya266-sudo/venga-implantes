import { describe, expect, it, vi } from 'vitest';
import { ApiError, apiUrl, cacheKey, get, post, preloadedData, resetPreload } from '@/api/client';
import { mockApi } from '@/test/app';

/** Deja en el documento lo que Laravel escribe al servir la página. */
function preloadText(text: string): void {
  const script = document.createElement('script');
  script.id = 'preload';
  script.type = 'application/json';
  script.textContent = text;
  document.body.append(script);
  resetPreload();
}

function preload(responses: Record<string, { status: number; body: unknown }>): void {
  preloadText(JSON.stringify(responses));
}

describe('apiUrl', () => {
  it('builds the address of a resource and leaves out what has no value', () => {
    expect(apiUrl('treatments/limpieza-dental')).toBe('/api/v1/treatments/limpieza-dental');
    expect(apiUrl('booking/days', { treatment: 3, doctor: null, date: '', promo: undefined })).toBe(
      '/api/v1/booking/days?treatment=3',
    );
  });
});

describe('cacheKey', () => {
  it('is the same however Laravel or the frontend wrote the address', () => {
    const fromLaravel = cacheKey('http://localhost:3000/api/v1/cases/?tratamiento=implantes&pagina=2');
    const fromHere = cacheKey(apiUrl('cases', { pagina: 2, tratamiento: 'implantes' }));

    expect(fromLaravel).toBe('/api/v1/cases?pagina=2&tratamiento=implantes');
    expect(fromHere).toBe(fromLaravel);
  });
});

describe('get', () => {
  it('uses once what Laravel left written in the page and then asks the API', async () => {
    preload({ 'http://localhost:3000/api/v1/team': { status: 200, body: { data: 'del documento' } } });
    const calls = mockApi({ '/api/v1/team': { data: 'de la API' } });

    await expect(get(apiUrl('team'))).resolves.toEqual({ data: 'del documento' });
    expect(calls).toHaveLength(0);

    await expect(get(apiUrl('team'))).resolves.toEqual({ data: 'de la API' });
    expect(calls).toMatchObject([{ method: 'GET', url: '/api/v1/team', headers: { Accept: 'application/json' } }]);
  });

  it('fails with the answer Laravel left when the page was not found', async () => {
    preload({ '/api/v1/treatments/no-existe': { status: 404, body: { message: 'No encontramos esta página' } } });
    mockApi();

    const error = await get(apiUrl('treatments/no-existe')).catch((problem: unknown) => problem);

    expect(error).toBeInstanceOf(ApiError);
    expect(error).toMatchObject({ status: 404, message: 'No encontramos esta página' });
  });

  it('asks the API when the page has no readable data', async () => {
    preloadText('{ esto no es JSON');
    mockApi({ '/api/v1/team': { data: 'de la API' } });

    await expect(get(apiUrl('team'))).resolves.toEqual({ data: 'de la API' });
  });

  it('explains it when the server answers something that is not JSON', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(() => Promise.resolve(new Response('<html>Error</html>', { status: 500 }))),
    );

    await expect(get(apiUrl('team'))).rejects.toMatchObject({
      status: 500,
      message: 'No pudimos leer la respuesta del servidor. Inténtalo de nuevo.',
    });
  });
});

describe('preloadedData', () => {
  it('hands over only the correct answers, to paint the page without waiting', async () => {
    preload({
      '/api/v1/site': { status: 200, body: { data: 'sitio' } },
      '/api/v1/account': { status: 403, body: { message: 'No puedes ver esta página' } },
    });
    mockApi();

    expect(preloadedData()).toEqual([['/api/v1/site', { data: 'sitio' }]]);
    // El error queda para cuando la página lo pida.
    await expect(get(apiUrl('account'))).rejects.toMatchObject({ status: 403 });
  });
});

describe('post', () => {
  it('sends the data with the token Laravel left in the cookie', async () => {
    document.cookie = 'XSRF-TOKEN=abc%3D%3D; path=/';
    const calls = mockApi({ 'POST /api/v1/account/access': { data: { phone: '987 654 321' } } });

    await expect(post(apiUrl('account/access'), { phone: '987654321' })).resolves.toEqual({
      data: { phone: '987 654 321' },
    });
    expect(calls).toMatchObject([
      {
        method: 'POST',
        url: '/api/v1/account/access',
        headers: { 'X-XSRF-TOKEN': 'abc==', 'Content-Type': 'application/json' },
        body: { phone: '987654321' },
      },
    ]);
  });

  it('renews the token and tries once more when the session expired', async () => {
    let attempts = 0;
    const calls = mockApi({
      'POST /api/v1/account/logout': () =>
        ++attempts === 1 ? { status: 419, body: { message: 'CSRF token mismatch.' } } : { body: { message: 'Listo' } },
    });

    await expect(post(apiUrl('account/logout'))).resolves.toEqual({ message: 'Listo' });
    expect(calls.map((call) => `${call.method} ${call.url}`)).toEqual([
      'POST /api/v1/account/logout',
      'GET /api/v1/site',
      'POST /api/v1/account/logout',
    ]);
  });

  it('fails with the first message of each field the API did not accept', async () => {
    mockApi({
      'POST /api/v1/booking/appointments': {
        status: 422,
        body: {
          message: 'Escribe tu celular. (y 1 error más)',
          errors: { phone: ['Escribe tu celular.', 'Debe tener 9 dígitos.'], first_name: ['Escribe tus nombres.'] },
        },
      },
    });

    const error = await post(apiUrl('booking/appointments')).catch((problem: unknown) => problem);

    expect(error).toBeInstanceOf(ApiError);
    expect((error as ApiError).fieldErrors).toEqual({
      phone: 'Escribe tu celular.',
      first_name: 'Escribe tus nombres.',
    });
  });

  it('never shows an empty message when the API answers without one', async () => {
    mockApi({ 'POST /api/v1/account/logout': { status: 403, body: { message: '' } } });

    const error = await post(apiUrl('account/logout')).catch((problem: unknown) => problem);

    expect(error).toBeInstanceOf(ApiError);
    expect((error as ApiError).message).toBe('La petición falló (403).');
  });
});
