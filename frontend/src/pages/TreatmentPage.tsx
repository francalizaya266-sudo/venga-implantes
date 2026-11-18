import { useParams } from 'react-router';
import { usePage, useSite } from '@/api/queries';
import type { Site, TreatmentData } from '@/api/types';
import { SiteLayout } from '@/components/layout/SiteLayout';
import { Cases } from '@/components/site/Cases';
import { Doctors } from '@/components/site/Doctors';
import { Faqs } from '@/components/site/Faqs';
import { NextFree } from '@/components/site/NextFree';
import { PriceList } from '@/components/site/PriceList';
import { Reviews } from '@/components/site/Reviews';
import { AppLink } from '@/components/ui/AppLink';
import { TreatmentArt } from '@/components/ui/Art';
import { Html } from '@/components/ui/Html';
import { Loading } from '@/components/ui/Loading';
import { Section } from '@/components/ui/Section';
import { paths } from '@/lib/paths';
import { whatsappUrl } from '@/lib/whatsapp';
import { ErrorPage } from '@/pages/ErrorPage';

/** La página de un tratamiento: qué es, cuánto dura y cuesta, y cómo reservarlo. */
export function TreatmentPage() {
  const { slug = '' } = useParams();
  const site = useSite();
  const { data, error } = usePage<TreatmentData>(`treatments/${slug}`);

  if (error) {
    return <ErrorPage error={error} />;
  }

  return <SiteLayout wide>{site && data ? <TreatmentBody site={site} data={data.data} /> : <Loading />}</SiteLayout>;
}

function TreatmentBody({ site, data }: { site: Site; data: TreatmentData }) {
  const { treatment, campaign, booking, next_free: nextFree } = data;
  const direct = booking?.direct ?? false;
  const whatsapp = whatsappUrl(site.clinic, `Hola, quisiera información sobre ${treatment.name.toLowerCase()}.`);
  const photoBelow = nextFree !== null && treatment.image_url !== null;

  return (
    <>
      <nav className="crumbs" aria-label="Estás en">
        <AppLink href={paths.treatments}>Servicios</AppLink>
      </nav>

      {/* A la derecha, lo más útil: los próximos horarios libres; si no hay, la foto. */}
      <section className={`treatment-head${nextFree || treatment.image_url ? ' with-aside' : ''}`}>
        <div>
          <span className="treatment-art">
            <TreatmentArt art={treatment.art} size={112} lazy={false} />
          </span>
          <h1>{treatment.name}</h1>
          {treatment.summary ? <p className="lead">{treatment.summary}</p> : null}

          <dl className="facts-row">
            <div>
              <dt>Duración</dt>
              <dd>{treatment.duration_minutes} min aprox.</dd>
            </div>
            {treatment.sessions_text ? (
              <div>
                <dt>Sesiones</dt>
                <dd>{treatment.sessions_text}</dd>
              </div>
            ) : null}
            <div>
              <dt>{campaign ? 'Precio con la promoción' : 'Precio'}</dt>
              <dd>
                {campaign ? (
                  <>
                    <s className="was">{treatment.price}</s> {campaign.price}
                  </>
                ) : (
                  (treatment.price ?? 'En tu evaluación')
                )}
              </dd>
            </div>
          </dl>

          {campaign ? (
            <p className="promo-note">
              Promoción <AppLink href={paths.promotion(campaign.slug)}>{campaign.name}</AppLink> ·{' '}
              {campaign.until_lower}.
            </p>
          ) : null}

          {/* Lo que no se reserva solo empieza por la evaluación: se dice antes del botón, no en letra chica después. */}
          {direct ? null : (
            <div className="starts-with">
              <strong>Empieza con una evaluación</strong>
              <p>El doctor revisa tu caso, te da el presupuesto y agenda las sesiones contigo.</p>
            </div>
          )}
          <div className="row">
            {booking ? (
              <AppLink href={paths.booking({ tratamiento: booking.treatment, promo: booking.promo })} className="btn">
                {direct ? (campaign ? 'Reservar con la promoción' : 'Reservar cita') : 'Reservar evaluación'}
              </AppLink>
            ) : null}
            {whatsapp ? (
              <a href={whatsapp} className="btn quiet" rel="noopener">
                Escribir por WhatsApp
              </a>
            ) : null}
          </div>
          {treatment.price ? <p className="muted small">{site.how_to_pay}</p> : null}
        </div>
        {nextFree ? (
          <NextFree nextFree={nextFree} />
        ) : treatment.image_url ? (
          <img src={treatment.image_url} alt={treatment.name} className="treatment-photo" />
        ) : null}
      </section>

      {treatment.description_html || photoBelow ? (
        <Section className={`treatment-body${photoBelow ? ' with-image' : ''}`}>
          {treatment.description_html ? <Html className="prose" html={treatment.description_html} /> : null}
          {photoBelow && treatment.image_url ? (
            <img src={treatment.image_url} alt={treatment.name} className="treatment-photo" loading="lazy" />
          ) : null}
        </Section>
      ) : null}

      {data.cases.length ? (
        <Section aria-labelledby="casos">
          <h2 id="casos">Resultados de pacientes</h2>
          <Cases cases={data.cases} />
        </Section>
      ) : null}

      {data.doctors.length ? (
        <Section aria-labelledby="quien">
          <h2 id="quien">Quién lo realiza</h2>
          <Doctors doctors={data.doctors} />
        </Section>
      ) : null}

      {data.reviews.length ? (
        <Section aria-labelledby="opiniones">
          <h2 id="opiniones">Opiniones</h2>
          <Reviews reviews={data.reviews} />
        </Section>
      ) : null}

      {data.faqs.length ? (
        <Section aria-labelledby="preguntas">
          <h2 id="preguntas">Preguntas sobre {treatment.name.toLowerCase()}</h2>
          <Faqs faqs={data.faqs} />
        </Section>
      ) : null}

      {data.others.length ? (
        <Section aria-labelledby="otros">
          <h2 id="otros">Otros servicios</h2>
          <PriceList treatments={data.others} />
        </Section>
      ) : null}
    </>
  );
}
