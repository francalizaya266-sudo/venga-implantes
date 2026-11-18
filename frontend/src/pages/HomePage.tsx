import { usePage, useSite } from '@/api/queries';
import type { Campaign, Clinic, Doctor, HomeData, Site } from '@/api/types';
import { SiteLayout } from '@/components/layout/SiteLayout';
import { Cases } from '@/components/site/Cases';
import { Contact } from '@/components/site/Contact';
import { Portrait } from '@/components/site/Doctors';
import { Faqs } from '@/components/site/Faqs';
import { GoogleRating } from '@/components/site/GoogleRating';
import { NextFree } from '@/components/site/NextFree';
import { Reviews } from '@/components/site/Reviews';
import { ServiceCards } from '@/components/site/ServiceCards';
import { Welcome } from '@/components/site/Welcome';
import { AppLink } from '@/components/ui/AppLink';
import { CoverLayer, FeatureArt } from '@/components/ui/Art';
import { Html } from '@/components/ui/Html';
import { Icon } from '@/components/ui/Icon';
import { Loading } from '@/components/ui/Loading';
import { Section } from '@/components/ui/Section';
import { artUrl } from '@/lib/art';
import { paths } from '@/lib/paths';
import { whatsappUrl } from '@/lib/whatsapp';
import { ErrorPage } from '@/pages/ErrorPage';

/** Junto al acceso rápido entran dos filas de tratamientos; el resto está en su página. */
const SERVICES_SHOWN = 8;

/**
 * La portada, en azul noche a todo el ancho: si está abierto, la frase del consultorio, qué es y el implante 3D
 * de la marca. Con la foto real del consultorio, la foto va a la derecha. Quien vuelve desde su celular recordado
 * ve primero lo suyo, dentro de la misma franja.
 */
function Hero({ site, home }: { site: Site; home: HomeData }) {
  const { clinic } = site;
  const { title, hero_image_url: photo } = site.home;
  // Lo que tranquiliza antes de reservar. Sin reserva en línea no se promete nada de eso.
  const trust = home.can_book
    ? [
        ['calendar-check', 'Horarios libres de verdad'],
        ['chat-check', 'Confirmas por WhatsApp'],
        ['wallet', 'Pagas en el consultorio'],
        ...(home.shows_prices ? [['tag', 'Precios a la vista']] : []),
      ]
    : [];

  return (
    <>
      <div className={`hero-band${photo ? ' with-photo' : ''}`}>
        {photo ? (
          <figure className="hero-photo">
            <img src={photo} alt={clinic.name} fetchPriority="high" />
          </figure>
        ) : null}
        <div className="wrap wide">
          {home.welcome ? <Welcome welcome={home.welcome} clinic={clinic} /> : null}

          <section
            className={['hero', photo ? 'with-photo' : 'with-art', home.can_book ? '' : 'plain']
              .filter(Boolean)
              .join(' ')}
            aria-labelledby="titulo"
          >
            <div className="hero-text">
              {clinic.hours.status ? (
                <p className={`open-now${clinic.hours.is_open ? ' open' : ''}`}>{clinic.hours.status}</p>
              ) : null}
              {site.home.tagline ? <p className="eyebrow">{site.home.tagline}</p> : null}
              <h1 id="titulo">
                {title.strong}
                {title.thin !== '' ? (
                  <>
                    {' '}
                    <span>{title.thin}</span>
                  </>
                ) : null}
                {title.accent !== '' ? (
                  <>
                    {' '}
                    <em>{title.accent}</em>
                  </>
                ) : null}
              </h1>
              {site.home.intro ? <p className="lead">{site.home.intro}</p> : null}
              {clinic.google_rating ? <GoogleRating rating={clinic.google_rating} /> : null}
              <div className="row">
                <AppLink href={paths.booking()} className="btn light">
                  <Icon name="calendar" />
                  Reservar cita
                </AppLink>
                {home.treatments.length ? (
                  <a href="#servicios" className="btn ghost">
                    Conocer más
                    <Icon name="arrow-down" />
                  </a>
                ) : null}
              </div>
            </div>

            {/*
              El implante con su cinta de luz: es la imagen de la marca, con foto o sin ella. Va en dos capas, el
              implante de bordes limpios y encima la cinta, que es la que lleva el resplandor (site.css). Con foto,
              en el celular no se ve: se pide solo cuando hace falta, y lo primero en cargar es la foto.
            */}
            <figure className="hero-art">
              <CoverLayer name="implante-portada" lazy={Boolean(photo)} />
              <CoverLayer name="implante-portada-luz" lazy={Boolean(photo)} />
            </figure>
          </section>
        </div>
      </div>

      {trust.length ? (
        <div className="wrap wide">
          <ul className="trust">
            {trust.map(([icon, phrase]) => (
              <li key={icon}>
                <Icon name={icon ?? ''} />
                <span>{phrase}</span>
              </li>
            ))}
          </ul>
        </div>
      ) : null}
    </>
  );
}

/**
 * Lo que más se busca, a un toque, y al lado los servicios con su precio "desde". En el celular, "Reservar cita"
 * y WhatsApp ya están siempre en la barra de abajo: aquí no se repiten (site.css).
 */
function Start({ clinic, home }: { clinic: Clinic; home: HomeData }) {
  const whatsapp = whatsappUrl(clinic);
  const shown = home.treatments.slice(0, SERVICES_SHOWN);

  return (
    <div className="start">
      <aside className="quick" aria-labelledby="acceso">
        <h2 id="acceso">
          <Icon name="calendar" />
          Acceso rápido
        </h2>
        <p>¿Qué necesitas hacer?</p>
        {home.next_free ? <NextFree nextFree={home.next_free} headingLevel="h3" /> : null}
        <div className="quick-actions">
          <AppLink href={paths.booking()} className="btn light in-bar">
            <Icon name="calendar" />
            Reservar cita
            <Icon name="arrow-right" />
          </AppLink>
          {whatsapp ? (
            <a href={whatsapp} className="btn ghost in-bar" rel="noopener">
              <Icon name="whatsapp" />
              Escribir por WhatsApp
            </a>
          ) : null}
          {clinic.directions_url ? (
            <a href={clinic.directions_url} className="btn ghost" rel="noopener" target="_blank">
              <Icon name="pin" />
              Cómo llegar
            </a>
          ) : null}
        </div>
      </aside>

      {shown.length ? (
        <section className="services" aria-labelledby="servicios">
          <div className="section-head">
            <h2 id="servicios">Nuestros servicios</h2>
            <p>Precios referenciales “desde”. El costo exacto te lo da el doctor en tu evaluación, antes de empezar.</p>
            {home.treatments_count > shown.length ? (
              <AppLink href={paths.treatments} className="arrow">
                Ver los {home.treatments_count} servicios
              </AppLink>
            ) : null}
          </div>
          <ServiceCards treatments={shown} />
        </section>
      ) : null}
    </div>
  );
}

function PromoBand({ campaign }: { campaign: Campaign }) {
  return (
    <AppLink href={paths.promotion(campaign.slug)} className={`promo-band${campaign.image_url ? ' with-photo' : ''}`}>
      {campaign.image_url ? <img src={campaign.image_url} alt="" loading="lazy" className="promo-photo" /> : null}
      <span className="promo-text">
        <span className="tag">Promoción</span>
        <strong>{campaign.name}</strong>
        {campaign.summary ? <span>{campaign.summary}</span> : null}
        {campaign.until ? <span className="until">{campaign.until}</span> : null}
        <span className="go">Ver la promoción</span>
      </span>
      {/* Sin foto propia, la promoción lleva el diente sonriente. */}
      {campaign.image_url ? null : (
        <img src={artUrl('sonrisa')} alt="" width={450} height={410} loading="lazy" className="promo-art" />
      )}
    </AppLink>
  );
}

/** Cómo se reserva, antes de pedir ningún dato. Solo si la web tiene algo que se pueda reservar. */
function HowItWorks({ howToPay }: { howToPay: string }) {
  return (
    <Section className="how-band" aria-labelledby="como">
      <div className="section-head">
        <h2 id="como">Reserva en tres pasos</h2>
        <p>Sin llamadas ni esperas: lo haces desde tu celular.</p>
        <AppLink href={paths.booking()} className="btn">
          Reservar cita
          <Icon name="arrow-right" />
        </AppLink>
      </div>
      <ol className="how">
        <li>
          <img src={artUrl('calendario')} alt="" width={410} height={380} loading="lazy" />
          <strong>Elige día y hora</strong>
          <span>Ves los horarios que están libres y tocas el que te queda bien.</span>
        </li>
        <li>
          <img src={artUrl('escudo')} alt="" width={380} height={380} loading="lazy" />
          <strong>Confirma por WhatsApp</strong>
          <span>Te escribimos con un enlace para confirmar, cambiar el horario o cancelar.</span>
        </li>
        <li>
          <img src={artUrl('sonrisa')} alt="" width={450} height={410} loading="lazy" />
          <strong>Ven a tu cita</strong>
          <span>{howToPay}</span>
        </li>
      </ol>
      <img src={artUrl('portada')} alt="" width={900} height={820} loading="lazy" className="how-art" />
    </Section>
  );
}

/**
 * Quién atiende, en breve: quien encabeza el equipo, con su especialidad y su colegiatura. Su presentación
 * completa, la del consultorio y el resto del equipo están en la página Equipo: aquí no se repiten.
 */
function Meet({ doctor, others }: { doctor: Doctor; others: number }) {
  const credentials = [
    doctor.cop_number ? `COP ${doctor.cop_number}` : null,
    doctor.rne_number ? `RNE ${doctor.rne_number}` : null,
  ].filter(Boolean);

  return (
    <Section className="meet" aria-labelledby="equipo">
      <Portrait doctor={doctor} />
      <div className="who">
        <p className="kicker">{others ? 'Quiénes te atienden' : 'Quién te atiende'}</p>
        <h2 id="equipo">{doctor.name}</h2>
        {doctor.specialty ? <p className="specialty">{doctor.specialty}</p> : null}
        {credentials.length ? <p className="credentials">{credentials.join(' · ')}</p> : null}
      </div>
      {doctor.bio_short || others ? (
        <div className="more">
          {doctor.bio_short ? <p>{doctor.bio_short}</p> : null}
          {others ? (
            <p>
              {others === 1
                ? 'Junto con otro profesional del equipo.'
                : `Junto con otros ${others} profesionales del equipo.`}
            </p>
          ) : null}
        </div>
      ) : null}
      <AppLink href={paths.team} className="btn quiet">
        Conocer al equipo
        <Icon name="arrow-right" />
      </AppLink>
    </Section>
  );
}

function HomeBody({ site, home }: { site: Site; home: HomeData }) {
  const { clinic } = site;

  return (
    <>
      <Start clinic={clinic} home={home} />

      {home.campaigns.map((campaign) => (
        <PromoBand key={campaign.slug} campaign={campaign} />
      ))}

      {/* La especialidad que declara el doctor, con su tratamiento: lo que distingue al consultorio. */}
      {home.specialty ? (
        <Section className="feature" aria-labelledby="especialidad">
          <div>
            <p className="kicker">Nuestra especialidad</p>
            <h2 id="especialidad">{home.specialty.name}</h2>
            {home.specialty.summary ? <p>{home.specialty.summary}</p> : null}
            <AppLink href={paths.treatment(home.specialty.slug)} className="btn">
              Conocer el tratamiento
              <Icon name="arrow-right" />
            </AppLink>
          </div>
          <FeatureArt art={home.specialty.art} />
        </Section>
      ) : null}

      {home.can_book ? <HowItWorks howToPay={site.how_to_pay} /> : null}

      {home.doctor ? (
        <Meet doctor={home.doctor} others={home.other_doctors} />
      ) : site.home.about_html ? (
        <Section className="about" aria-labelledby="nosotros">
          <h2 id="nosotros">Sobre {clinic.name}</h2>
          <Html className="prose" html={site.home.about_html} />
        </Section>
      ) : null}

      {home.cases.length ? (
        <Section aria-labelledby="casos">
          <div className="section-head">
            <h2 id="casos">Antes y después</h2>
            <p>Pacientes que nos autorizaron a mostrar su resultado.</p>
          </div>
          <Cases cases={home.cases} />
          <p>
            <AppLink href={paths.cases} className="arrow">
              Ver más casos
            </AppLink>
          </p>
        </Section>
      ) : null}

      {home.reviews.length ? (
        <Section aria-labelledby="opiniones">
          <div className="section-head">
            <h2 id="opiniones">Lo que dicen nuestros pacientes</h2>
          </div>
          <Reviews reviews={home.reviews} />
          <p className="row">
            <AppLink href={paths.reviews} className="arrow">
              Leer más opiniones
            </AppLink>
            {clinic.reviews_url ? (
              <a href={clinic.reviews_url} className="arrow" rel="noopener" target="_blank">
                Vernos en Google
              </a>
            ) : null}
          </p>
        </Section>
      ) : null}

      {/* Las preguntas principales, sobre una franja celeste entre dos partes blancas; todas están en su página. */}
      {home.faqs.length ? (
        <Section className="split band" aria-labelledby="preguntas">
          <div className="section-head">
            <h2 id="preguntas">Preguntas frecuentes</h2>
          </div>
          <Faqs faqs={home.faqs} />
          <p>
            <AppLink href={paths.faq} className="arrow">
              Ver todas las preguntas
            </AppLink>
          </p>
        </Section>
      ) : null}

      <Contact clinic={clinic} />
    </>
  );
}

/** El inicio. La dirección, el horario y el WhatsApp ya están en "Dónde estamos": el pie no los repite. */
export function HomePage() {
  const site = useSite();
  const { data, error } = usePage<HomeData>('home');

  if (error) {
    return <ErrorPage error={error} />;
  }

  if (!site || !data) {
    return (
      <SiteLayout wide footerContact={false}>
        <Loading />
      </SiteLayout>
    );
  }

  return (
    <SiteLayout wide footerContact={false} hero={<Hero site={site} home={data.data} />}>
      <HomeBody site={site} home={data.data} />
    </SiteLayout>
  );
}
