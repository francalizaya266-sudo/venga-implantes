import { useParams } from 'react-router';
import { usePage } from '@/api/queries';
import type { ChangeData } from '@/api/types';
import { SiteLayout } from '@/components/layout/SiteLayout';
import { Loading } from '@/components/ui/Loading';
import { paths } from '@/lib/paths';
import { ChangeAppointment } from '@/pages/change/ChangeAppointment';
import { ErrorPage } from '@/pages/ErrorPage';

/** Cambiar el horario de una cita de Mi cuenta: entra con el celular recordado. */
export function AccountChangePage() {
  const { id = '' } = useParams();

  return <ChangePage key={id} resource={`account/appointments/${id}/change`} back={paths.account} />;
}

/** Cambiar el horario de la cita que llegó por el enlace de WhatsApp. */
export function LinkChangePage() {
  const { token = '' } = useParams();

  return <ChangePage key={token} resource={`links/${token}/change`} back={paths.link(token)} />;
}

/** Sin la barra de abajo ni el pie completo: aquí lo que importa son los horarios. */
function ChangePage({ resource, back }: { resource: string; back: string }) {
  const { data, error } = usePage<ChangeData>(resource);

  if (error) {
    return <ErrorPage error={error} />;
  }

  return (
    <SiteLayout whatsappFloat={false} minimalFooter>
      {data ? <ChangeAppointment change={data.data} resource={resource} back={back} /> : <Loading />}
    </SiteLayout>
  );
}
