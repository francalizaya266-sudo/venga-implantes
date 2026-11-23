import { useEffect } from 'react';
import { ApiError } from '@/api/client';
import { useSite } from '@/api/queries';
import { SiteLayout } from '@/components/layout/SiteLayout';
import { AppLink } from '@/components/ui/AppLink';
import { applyErrorMeta } from '@/lib/meta';
import { paths } from '@/lib/paths';

interface Texts {
  title: string;
  lead: string;
  /** Si se ofrece "Volver a intentar" (cargar la página de nuevo) en vez de "Ir al inicio". */
  retry?: true;
}

const BY_STATUS: Record<number, Texts> = {
  403: {
    title: 'No puedes ver esta página',
    lead: 'Este enlace no es para este celular o ya no está disponible. Si necesitas ayuda, escríbenos por WhatsApp.',
  },
  404: {
    title: 'No encontramos esta página',
    lead: 'Puede que el enlace esté incompleto o que la página ya no exista.',
  },
  419: {
    title: 'La página venció',
    lead: 'Estuvo abierta mucho tiempo y, por seguridad, hay que cargarla de nuevo. Vuelve atrás y repite lo último que hiciste.',
  },
  429: { title: 'Demasiados intentos seguidos', lead: 'Por seguridad, espera unos minutos y vuelve a intentarlo.' },
};

const FAILED: Texts = {
  title: 'Algo falló de nuestro lado',
  lead: 'No es tu culpa. Vuelve a intentarlo en unos minutos y, si sigue pasando, escríbenos por WhatsApp.',
  retry: true,
};

/** La página o sus datos no llegaron por la conexión: lo mismo que se dice cuando falla una acción (NETWORK_PROBLEM). */
const NO_CONNECTION: Texts = {
  title: 'No pudimos conectarnos',
  lead: 'Revisa tu conexión e inténtalo de nuevo.',
  retry: true,
};

/**
 * Cómo avisa cada navegador que algo no llegó por la red: el código de una página ("Failed to fetch dynamically
 * imported module" en Chrome, "Importing a module script failed" en Safari) o los datos ("Failed to fetch", "Load
 * failed", "NetworkError…"). Un error del código de la web no se parece a estos.
 */
const CONNECTION_FAILED =
  /failed to fetch|load failed|networkerror|importing a module script failed|dynamically imported module/i;

function isConnectionProblem(error: unknown): boolean {
  if (navigator.onLine === false) {
    return true;
  }

  // El aviso del navegador puede llegar envuelto en otro error, como su causa.
  for (let current: unknown = error; current instanceof Error; current = current.cause) {
    if (current instanceof TypeError && CONNECTION_FAILED.test(current.message)) {
      return true;
    }
  }

  return false;
}

interface Props {
  /** La respuesta de la API que no dejó pintar la página. */
  error?: unknown;
  /** El estado, cuando no viene de la API: la dirección no existe (404) o Laravel abrió la web con un error. */
  status?: number;
}

/** La página de un error, con el encabezado y el pie de siempre para que quien tocó un enlace viejo pueda seguir. */
export function ErrorPage({ error, status }: Props) {
  const code = status ?? (error instanceof ApiError ? error.status : 0);
  const texts = BY_STATUS[code] ?? (code === 0 && isConnectionProblem(error) ? NO_CONNECTION : FAILED);

  const clinic = useSite()?.clinic.name;

  useEffect(() => {
    applyErrorMeta(texts.title, clinic);
  }, [texts.title, clinic]);

  return (
    <SiteLayout>
      <section className="stack error-site">
        <h1>{texts.title}</h1>
        <p className="lead">{texts.lead}</p>
        <p className="error-actions">
          {texts.retry ? (
            <button type="button" className="btn" onClick={() => window.location.reload()}>
              Volver a intentar
            </button>
          ) : (
            <AppLink href={paths.home} className="btn">
              Ir al inicio
            </AppLink>
          )}
          <AppLink href={paths.booking()} className="btn quiet">
            Reservar cita
          </AppLink>
        </p>
      </section>
    </SiteLayout>
  );
}
