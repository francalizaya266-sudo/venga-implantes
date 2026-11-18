import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { RouterProvider } from 'react-router/dom';
import { preloadedData } from '@/api/client';
import { retry } from '@/api/queries';
import { createAppRouter } from '@/app/router';
import '@/styles/site.css';

const container = document.getElementById('app');

if (!container) {
  throw new Error('Falta el elemento #app donde se monta la web.');
}

// Con JavaScript, las secciones aparecen suaves al llegar a ellas (site.css).
document.documentElement.classList.add('js');

const queryClient = new QueryClient({
  defaultOptions: { queries: { staleTime: 30_000, retry } },
});

// Los datos de la página con que se abrió la web ya vienen escritos en el HTML: se pinta sin esperar a la red.
for (const [key, body] of preloadedData()) {
  queryClient.setQueryData([key], body);
}

// Si Laravel respondió con un error (403, 429…), la página lo dice en vez de intentar pintar otra cosa.
const documentError = Number(container.dataset.error) || null;

createRoot(container).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={createAppRouter(documentError)} />
    </QueryClientProvider>
  </StrictMode>,
);
