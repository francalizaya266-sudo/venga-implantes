import { useParams } from 'react-router';
import { ApiError } from '@/api/client';
import { usePage, useSite } from '@/api/queries';
import type { PromotionData, PromotionEndedData, Site } from '@/api/types';
import { SiteLayout } from '@/components/layout/SiteLayout';
import { AppLink } from '@/components/ui/AppLink';
import { Html } from '@/components/ui/Html';
import { Loading } from '@/components/ui/Loading';
import { paths } from '@/lib/paths';
import { whatsappUrl } from '@/lib/whatsapp';
import { ErrorPage } from '@/pages/ErrorPage';

/** Una promoción: qué ofrece, hasta cuándo y cómo reservar con ella. Si ya terminó, ofrece las vigentes. */
export function PromotionPage() {
  const { slug = '' } = useParams();
  const site = useSite();
  const { data, error } = usePage<PromotionData>(`promotions/${slug}`);

  if (error instanceof ApiError && error.status === 404 && error.body.data) {
    return (
      <SiteLayout wide>
        <PromotionEnded data={error.body.data as PromotionEndedData} />
      </SiteLayout>
    );
  }

  if (error) {
    return <ErrorPage error={error} />;
  }

  return <SiteLayout wide>{site && data ? <Promotion site={site} data={data.data} /> : <Loading />}</SiteLayout>;
}

function Promotion({
  site,
  data: { campaign, by_evaluation: byEvaluation, booking },
}: {
  site: Site;
  data: PromotionData;
}) {
  const { package: pack, price } = campaign;
  const whatsapp = whatsappUrl(site.clinic, campaign.whatsapp_text);

  return (
    <>
      <nav className="crumbs" aria-label="Estás en">
        <AppLink href={paths.promotions}>Promociones</AppLink>
      </nav>

      <article className={`promo-page${campaign.image_url ? ' with-image' : ''}`}>
        {campaign.image_url ? <img src={campaign.image_url} alt={campaign.name} className="promo-image" /> : null}
        <div className="stack">
          <h1>{campaign.name}</h1>
          {/* Si el nombre ya dice el descuento ("Blanqueamiento 20% de descuento"), no se repite debajo. */}
          {campaign.discount_beside_name ? <p className="discount-big">{campaign.discount_beside_name}</p> : null}
          {campaign.summary ? <p className="lead">{campaign.summary}</p> : null}
          <p className="until">
            {campaign.until}
            {campaign.treatment ? (
              <>
                {' · '}
                <AppLink href={paths.treatment(campaign.treatment.slug)}>{campaign.treatment.name}</AppLink>
              </>
            ) : null}
          </p>

          {price ? (
            <div className="promo-package">
              <p className="prices">
                <s>Precio normal {price.normal}</s> <strong>{price.now}</strong>
              </p>
              <p>{site.how_to_pay}</p>
            </div>
          ) : null}

          {pack ? (
            <section className="promo-package" aria-labelledby="incluye">
              <h2 id="incluye">Qué incluye</h2>
              <ul>
                {pack.items.map((item) => (
                  <li key={item}>{item}</li>
                ))}
              </ul>
              <p className="prices">
                {pack.normal_price ? <s>Precio normal {pack.normal_price}</s> : null} <strong>{pack.price}</strong>{' '}
                {pack.savings ? <span className="save">Ahorras {pack.savings}</span> : null}
              </p>
              {pack.for_two ? (
                <p>
                  <strong>2x1:</strong> con el mismo pago atendemos también a otra persona: un familiar o alguien que
                  traigas.
                </p>
              ) : null}
              <h3>Cómo se puede pagar</h3>
              {pack.payment_options.map((option) => (
                <p key={option}>{option}</p>
              ))}
              {pack.description ? <p className="muted">{pack.description}</p> : null}
            </section>
          ) : null}

          {campaign.has_slots_left ? (
            <>
              {campaign.promo_code ? (
                <p className="code">
                  Código: <strong>{campaign.promo_code}</strong>
                </p>
              ) : null}
              {byEvaluation && campaign.treatment ? (
                <p>
                  {campaign.treatment.name} se programa después de una evaluación: reservas la evaluación y la promoción
                  queda guardada para tu tratamiento.
                </p>
              ) : null}
              <div className="row">
                <AppLink href={paths.booking(booking ?? {})} className="btn">
                  {byEvaluation ? 'Reservar evaluación con la promoción' : 'Reservar con la promoción'}
                </AppLink>
                {whatsapp ? (
                  <a href={whatsapp} className="btn quiet" rel="noopener">
                    Escribir por WhatsApp
                  </a>
                ) : null}
              </div>
            </>
          ) : (
            <p className="flash info">
              Los cupos de esta promoción ya se agotaron. Escríbenos y te contamos qué otras opciones hay.
            </p>
          )}

          {campaign.description_html ? <Html className="prose" html={campaign.description_html} /> : null}
          {pack ? (
            <p className="muted small">
              El precio y la forma de pago se acuerdan en la consulta, al firmar el plan de tratamiento. No es
              acumulable con otras promociones salvo que se indique.
            </p>
          ) : (
            <p className="muted small">
              El descuento se aplica al pagar en el consultorio. No es acumulable con otras promociones salvo que se
              indique.
            </p>
          )}
        </div>
      </article>
    </>
  );
}

function PromotionEnded({ data: { ended, campaigns } }: { data: PromotionEndedData }) {
  return (
    <>
      <section className="page-head">
        <h1>{ended ? 'Esta promoción ya terminó' : 'No encontramos esta promoción'}</h1>
        <p className="lead">
          {ended ? `“${ended}” ya no está vigente.` : 'Puede que el enlace esté incompleto.'} Estas son las que siguen
          activas.
        </p>
      </section>

      {campaigns.length ? (
        <ul className="choice-list">
          {campaigns.map((campaign) => (
            <li key={campaign.slug}>
              <AppLink href={paths.promotion(campaign.slug)} className="choice">
                <strong>{campaign.name}</strong>
                {campaign.summary ? <span className="meta">{campaign.summary}</span> : null}
              </AppLink>
            </li>
          ))}
        </ul>
      ) : (
        <p className="panel">
          Ahora no tenemos promociones vigentes. Igual puedes <AppLink href={paths.booking()}>reservar tu cita</AppLink>
          .
        </p>
      )}
    </>
  );
}
