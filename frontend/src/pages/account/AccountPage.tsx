import { usePage, useSite } from '@/api/queries';
import type { AccountData } from '@/api/types';
import { SiteLayout } from '@/components/layout/SiteLayout';
import { Loading } from '@/components/ui/Loading';
import { AccountGuest } from '@/pages/account/AccountGuest';
import { AccountHome } from '@/pages/account/AccountHome';
import { ErrorPage } from '@/pages/ErrorPage';

/** Mi cuenta: entra con el celular recordado (sin contraseña); desde otro celular, se pide el enlace. */
export function AccountPage() {
  const site = useSite();
  const { data, error } = usePage<AccountData>('account');

  if (error) {
    return <ErrorPage error={error} />;
  }

  if (!site || !data) {
    return (
      <SiteLayout page="cuenta">
        <Loading />
      </SiteLayout>
    );
  }

  return data.data.guest ? (
    <SiteLayout page="cuenta">
      <AccountGuest clinic={site.clinic} />
    </SiteLayout>
  ) : (
    <SiteLayout page="cuenta" wide>
      <AccountHome account={data.data} clinic={site.clinic} howToPay={site.how_to_pay} />
    </SiteLayout>
  );
}
