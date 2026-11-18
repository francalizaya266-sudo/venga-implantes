import { usePage } from '@/api/queries';
import type { PromotionsData } from '@/api/types';
import { SiteLayout } from '@/components/layout/SiteLayout';
import { AppLink } from '@/components/ui/AppLink';
import { Loading } from '@/components/ui/Loading';
import { paths } from '@/lib/paths';
import { ErrorPage } from '@/pages/ErrorPage';

/** Promociones vigentes, cada una con su página. */
export function PromotionsPage() {
  const { data, error } = usePage<PromotionsData>('promotions');

  if (error) {
    return <ErrorPage error={error} />;
  }

  return (
    <SiteLayout page="promociones" wide>
      {data ? <Promotions campaigns={data.data.campaigns} /> : <Loading />}
    </SiteLayout>
  );
}

function Promotions({ campaigns }: PromotionsData) {
  if (!campaigns.length) {
    return (
      <p className="panel">
        Ahora no tenemos promociones vigentes. Síguenos en redes o escríbenos por WhatsApp para enterarte de las
        próximas.
      </p>
    );
  }

  return (
    <div className="promos">
      {campaigns.map((campaign) => (
        <AppLink key={campaign.slug} href={paths.promotion(campaign.slug)} className="promo-card">
          {campaign.image_url ? <img src={campaign.image_url} alt="" loading="lazy" /> : null}
          <span className="body">
            <strong>{campaign.name}</strong>
            {campaign.discount_beside_name ? <span className="discount">{campaign.discount_beside_name}</span> : null}
            {campaign.summary ? <span>{campaign.summary}</span> : null}
            <span className="until">{campaign.until}</span>
          </span>
        </AppLink>
      ))}
    </div>
  );
}
