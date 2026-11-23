import { onlineManager, useQuery, type QueryClient } from '@tanstack/react-query';
import { useEffect, useSyncExternalStore } from 'react';
import { ApiError, apiUrl, cacheKey, get, type Query } from '@/api/client';
import type { DataResponse, PageMeta, PageResponse, Site } from '@/api/types';
import { applyMeta } from '@/lib/meta';

/** Un error de red se reintenta; una respuesta de la API (404, 403…) ya es la respuesta. */
export function retry(failures: number, error: unknown): boolean {
  return !(error instanceof ApiError) && failures < 2;
}

/**
 * El error que cuenta para la pantalla. Con la página ya a la vista, un corte de red al volver a pedirla no la
 * borra (ni lo que el paciente estaba escribiendo); una respuesta de la API (403, 404…) sí.
 */
export function pageError(data: unknown, error: Error | null): Error | null {
  return data !== undefined && !(error instanceof ApiError) ? null : error;
}

/**
 * Si hay señal, según React Query: el navegador avisa cuando se va y cuando vuelve. Sin ella, lo que se pide queda
 * en pausa hasta que vuelva (en vez de fallar), así que una página nueva espera sus datos todo ese rato.
 */
export function useOnline(): boolean {
  return useSyncExternalStore(
    (onChange) => onlineManager.subscribe(onChange),
    () => onlineManager.isOnline(),
    () => true,
  );
}

/** Lo que comparten todas las páginas: los datos del consultorio y los textos que se editan en el panel. */
export function useSite(): Site | undefined {
  const { data } = useQuery<DataResponse<Site>>({
    queryKey: [cacheKey(apiUrl('site'))],
    queryFn: () => get(apiUrl('site')),
    staleTime: 5 * 60_000,
  });

  return data?.data;
}

/** Los datos del consultorio, si ya están: el documento con que se abrió la web los trae escritos. */
export function cachedSite(client: QueryClient): Site | undefined {
  return client.getQueryData<DataResponse<Site>>([cacheKey(apiUrl('site'))])?.data;
}

/** Pone el título y lo demás de la página en el documento cuando llegan (o cambian) sus datos. */
export function usePageMeta(meta: PageMeta | undefined): void {
  useEffect(() => {
    if (meta) {
      applyMeta(meta);
    }
  }, [meta]);
}

interface Page<T> {
  data: PageResponse<T> | undefined;
  error: Error | null;
}

/**
 * Los datos de una página, pedidos a la API. Si es la página con que se abrió la web, Laravel ya los dejó
 * escritos en el HTML y no hay nada que esperar.
 */
export function usePage<T>(path: string, query: Query = {}): Page<T> {
  const url = apiUrl(path, query);
  const { data, error } = useQuery<PageResponse<T>, Error>({ queryKey: [cacheKey(url)], queryFn: () => get(url) });
  const errorMeta = error instanceof ApiError ? (error.body.meta as PageMeta | undefined) : undefined;

  usePageMeta(data?.meta ?? errorMeta);

  return { data, error: pageError(data, error) };
}
