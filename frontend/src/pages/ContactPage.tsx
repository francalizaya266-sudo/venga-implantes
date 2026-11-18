import { usePage, useSite } from '@/api/queries';
import type { ContactData } from '@/api/types';
import { SiteLayout } from '@/components/layout/SiteLayout';
import { Contact } from '@/components/site/Contact';
import { Loading } from '@/components/ui/Loading';
import { ErrorPage } from '@/pages/ErrorPage';

/** Contacto y cómo llegar. La página ya muestra la dirección y el horario: el pie no los repite. */
export function ContactPage() {
  const site = useSite();
  const { data, error } = usePage<ContactData>('pages/contact');

  if (error) {
    return <ErrorPage error={error} />;
  }

  return (
    <SiteLayout page="contacto" wide footerContact={false}>
      {site && data ? <Contact clinic={site.clinic} heading={data.data.heading} /> : <Loading />}
    </SiteLayout>
  );
}
