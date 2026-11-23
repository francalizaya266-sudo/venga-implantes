import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { RouterProvider } from 'react-router/dom';
import { preloadedData } from '@/api/client';
import { cachedSite, retry } from '@/api/queries';
import { createAppRouter, loadPage, prefetchPages } from '@/app/router';
import { listenForInstall } from '@/lib/install';
import { registerWorker } from '@/lib/push';
import '@/styles/site.css';

const container = document.getElementById('app');

if (!container) {
  throw new Error('Falta el elemento #app donde se monta la web.');
}

// Con JavaScript, las secciones aparecen suaves al llegar a ellas (site.css).
document.documentElement.classList.add('js');

// La web se puede instalar como app, y el botón para hacerlo está en Mi cuenta: el aviso de Chrome llega al cargar.
listenForInstall();

const queryClient = new QueryClient({
  defaultOptions: { queries: { staleTime: 30_000, retry } },
});

// Los datos de la página con que se abrió la web ya vienen escritos en el HTML: se pinta sin esperar a la red.
for (const [key, body] of preloadedData()) {
  queryClient.setQueryData([key], body);
}

// Si Laravel respondió con un error (403, 429…), la página lo dice en vez de intentar pintar otra cosa.
const documentError = Number(container.dataset.error) || null;

// La página con que se abrió la web se pinta con su código ya en el navegador (el documento lo pidió junto con
// este archivo): sale completa de una vez, sin mostrar antes el hueco de la página.
const firstPage = documentError === null ? loadPage(window.location.pathname) : Promise.resolve();

firstPage.then(() => {
  createRoot(container).render(
    <StrictMode>
      <QueryClientProvider client={queryClient}>
        <RouterProvider router={createAppRouter(documentError)} />
      </QueryClientProvider>
    </StrictMode>,
  );

  // Las páginas que siguen (las del menú, según las secciones que tiene la web) se bajan en un rato libre, después
  // de pintar la primera.
  const site = cachedSite(queryClient);

  if (site) {
    prefetchPages(site.sections);
  }

  // Lo que recibe los avisos en el celular (y deja instalar la web como app). No pide ningún permiso.
  registerWorker()?.catch(() => undefined);
});
