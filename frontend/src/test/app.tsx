import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { createMemoryRouter, RouterProvider, type RouteObject } from 'react-router';
import { vi } from 'vitest';
import { routes } from '@/app/router';
import { Root } from '@/app/Root';
import { site } from '@/test/fixtures';

interface Reply {
  status?: number;
  body: unknown;
}

/**
 * Lo que responde un recurso de la API: el cuerpo (200), una respuesta con su estado o una función que la arma
 * con lo que se le envió (o que falla, como `offline`).
 */
type Answer = unknown | ((sent: unknown) => Reply);

export interface ApiCall {
  method: string;
  url: string;
  headers: Record<string, string>;
  body: unknown;
}

/** La respuesta que no llega (sin señal): 'POST /api/v1/…': offline. */
export function offline(): never {
  throw new TypeError('Failed to fetch');
}

function isReply(value: unknown): value is Reply {
  return typeof value === 'object' && value !== null && 'body' in value;
}

/**
 * La API de mentira de una prueba. Las claves son "GET /api/v1/home" o "POST /api/v1/…" (sin método, GET). Lo
 * que no se declare responde 404. Devuelve las peticiones hechas, para comprobar qué se envió.
 */
export function mockApi(answers: Record<string, Answer> = {}): ApiCall[] {
  const calls: ApiCall[] = [];
  const all: Record<string, Answer> = { 'GET /api/v1/site': { data: site() } };

  for (const [key, answer] of Object.entries(answers)) {
    all[key.includes(' ') ? key : `GET ${key}`] = answer;
  }

  vi.stubGlobal(
    'fetch',
    vi.fn((input: RequestInfo | URL, init: RequestInit = {}) => {
      const url = String(input);
      const method = init.method ?? 'GET';
      const body: unknown = typeof init.body === 'string' ? JSON.parse(init.body) : undefined;
      calls.push({ method, url, headers: (init.headers ?? {}) as Record<string, string>, body });

      const answer = all[`${method} ${url}`];

      try {
        const reply: Reply =
          answer === undefined
            ? { status: 404, body: { message: `Sin respuesta de prueba para ${method} ${url}` } }
            : typeof answer === 'function'
              ? (answer as (sent: unknown) => Reply)(body)
              : isReply(answer)
                ? answer
                : { body: answer };

        return Promise.resolve(new Response(JSON.stringify(reply.body), { status: reply.status ?? 200 }));
      } catch (error) {
        return Promise.reject(error);
      }
    }),
  );

  return calls;
}

function mount(routeObjects: RouteObject[], path: string) {
  // Sin reintentos ni datos guardados: cada prueba ve lo que la API de mentira responde.
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false, staleTime: 30_000 } } });
  const router = createMemoryRouter(routeObjects, { initialEntries: [path] });
  const view = render(
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>,
  );

  return { ...view, router, queryClient, user: userEvent.setup() };
}

/** Abre la web en una dirección, con todas sus páginas. `documentError`: el estado con que respondió Laravel. */
export function renderApp(path = '/', documentError: number | null = null) {
  return mount(routes(documentError), path);
}

/** Monta una pieza suelta dentro de lo que la web le da a todas (avisos, navegación, consultas a la API). */
export function renderPiece(piece: ReactNode, path = '/') {
  return mount(
    [
      {
        element: <Root documentError={null} />,
        children: [
          { path: '*', element: piece },
          { path: '/destino/*', element: <p>Otra página</p> },
        ],
      },
    ],
    path,
  );
}
