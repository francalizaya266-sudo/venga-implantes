import { useParams } from 'react-router';
import { useAction } from '@/api/actions';
import { apiUrl } from '@/api/client';
import { usePage, useSite } from '@/api/queries';
import type { LinkData } from '@/api/types';
import { SiteLayout } from '@/components/layout/SiteLayout';
import { AppointmentCard } from '@/components/site/AppointmentCard';
import { AppLink } from '@/components/ui/AppLink';
import { Loading } from '@/components/ui/Loading';
import { useLeave } from '@/lib/leave';
import { paths } from '@/lib/paths';
import { ErrorPage } from '@/pages/ErrorPage';

/**
 * El enlace personal que llega por WhatsApp. El de una cita la muestra para confirmarla, cambiarla o cancelarla;
 * el de acceso abre Mi cuenta. Abrir el enlace no cambia nada (la vista previa de WhatsApp también lo abre):
 * solo los botones actúan.
 */
export function LinkPage() {
  const { token = '' } = useParams();
  const site = useSite();
  const { data, error } = usePage<LinkData>(`links/${token}`);

  if (error) {
    return <ErrorPage error={error} />;
  }

  if (!site || !data) {
    return (
      <SiteLayout>
        <Loading />
      </SiteLayout>
    );
  }

  const link = data.data;

  if (link.kind === 'expired') {
    return (
      <SiteLayout>
        <section className="stack">
          <h1>Este enlace ya no está activo</h1>
          <p className="lead">Puede que la cita ya haya pasado o que te hayamos enviado un enlace nuevo.</p>
          <p>
            Si este celular está recordado, tus citas están en <AppLink href={paths.account}>Mi cuenta</AppLink>. Si no,
            escríbenos por WhatsApp y te ayudamos.
          </p>
        </section>
      </SiteLayout>
    );
  }

  if (link.kind === 'access') {
    return (
      <SiteLayout page="cuenta">
        <Enter token={token} />
      </SiteLayout>
    );
  }

  // Sin la barra de abajo: aquí los botones son los de la cita.
  return (
    <SiteLayout whatsappFloat={false}>
      <section className="stack-lg">
        <div className="stack">
          <h1>{link.title}</h1>
          <AppointmentCard
            appointment={link.appointment}
            clinic={site.clinic}
            confirmUrl={apiUrl(`links/${token}/confirm`)}
            changeHref={paths.linkChange(token)}
            cancelUrl={apiUrl(`links/${token}/cancel`)}
            solo
          />
        </div>

        <p className="muted small">
          ¿Tienes más citas o familiares en este celular? Míralos en <AppLink href={paths.account}>Mi cuenta</AppLink>.
        </p>
      </section>
    </SiteLayout>
  );
}

/** El enlace de acceso: al tocar el botón, este celular queda recordado y se entra a Mi cuenta. */
function Enter({ token }: { token: string }) {
  const { run, busy } = useAction();
  const leave = useLeave();

  return (
    <section className="stack">
      <h2>Entrar a Mi cuenta</h2>
      <p className="lead">
        Toca el botón para ver tus citas. Este celular quedará recordado y la próxima vez entrarás directo.
      </p>
      <form
        onSubmit={(event) => {
          event.preventDefault();
          void run(apiUrl(`links/${token}/enter`), {}, { onDone: (message) => leave(paths.account, message) });
        }}
      >
        <button type="submit" className="btn block" disabled={busy}>
          Entrar a Mi cuenta
        </button>
      </form>
      <p className="muted small">
        Si este no es tu celular (por ejemplo, uno prestado), no toques el botón: después puedes cerrar la sesión desde
        Mi cuenta.
      </p>
    </section>
  );
}
