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
};

interface Props {
  /** La respuesta de la API que no dejó pintar la página. */
  error?: unknown;
  /** El estado, cuando no viene de la API: la dirección no existe (404) o Laravel abrió la web con un error. */
  status?: number;
}

/** La página de un error, con el encabezado y el pie de siempre para que quien tocó un enlace viejo pueda seguir. */
export function ErrorPage({ error, status }: Props) {
  const code = status ?? (error instanceof ApiError ? error.status : 0);
  const texts = BY_STATUS[code] ?? FAILED;

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
          {texts === FAILED ? (
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
