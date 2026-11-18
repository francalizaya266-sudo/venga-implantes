import { usePage, useSite } from '@/api/queries';
import type { TeamData } from '@/api/types';
import { SiteLayout } from '@/components/layout/SiteLayout';
import { Doctors } from '@/components/site/Doctors';
import { Html } from '@/components/ui/Html';
import { Icon } from '@/components/ui/Icon';
import { Loading } from '@/components/ui/Loading';
import { ErrorPage } from '@/pages/ErrorPage';

/** Equipo: la presentación del consultorio y cada doctor con su formación y lo que atiende. */
export function TeamPage() {
  const site = useSite();
  const { data, error } = usePage<TeamData>('team');

  if (error) {
    return <ErrorPage error={error} />;
  }

  if (!site || !data) {
    return (
      <SiteLayout page="equipo" wide>
        <Loading />
      </SiteLayout>
    );
  }

  const { doctors } = data.data;

  return (
    <SiteLayout page="equipo" wide>
      {/* La presentación del consultorio, bajo el encabezado de la página. */}
      {site.home.about_html ? <Html className="page-intro prose" html={site.home.about_html} /> : null}

      {doctors.length ? (
        <>
          <Doctors doctors={doctors} full />

          {/* Lo que distingue al equipo, en pocas palabras (Página web › Textos de las páginas). */}
          {site.team_values.length ? (
            <ul className="values" aria-label="Lo que nos distingue">
              {site.team_values.map((value) => (
                <li key={value.title}>
                  <Icon name={value.icon} />
                  <strong>{value.title}</strong>
                  {value.text ? <span>{value.text}</span> : null}
                </li>
              ))}
            </ul>
          ) : null}
        </>
      ) : (
        <p className="panel">Pronto presentaremos a nuestro equipo.</p>
      )}
    </SiteLayout>
  );
}
