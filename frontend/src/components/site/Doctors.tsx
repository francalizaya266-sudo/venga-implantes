import type { Doctor } from '@/api/types';
import { AppLink } from '@/components/ui/AppLink';
import { Html } from '@/components/ui/Html';
import { Icon } from '@/components/ui/Icon';
import { paths } from '@/lib/paths';

/** La foto del doctor o, sin foto, sus iniciales: nunca una foto de banco. */
export function Portrait({ doctor }: { doctor: Doctor }) {
  return doctor.photo_url ? (
    <img src={doctor.photo_url} alt={doctor.name} className="portrait" loading="lazy" width={320} height={320} />
  ) : (
    <span className="portrait monogram" aria-hidden="true">
      {doctor.initials}
    </span>
  );
}

interface Props {
  doctors: Doctor[];
  /** Cada doctor en su tarjeta, con presentación, formación y lo que atiende (página Equipo). */
  full?: boolean;
}

/**
 * El botón de cada doctor sirve para elegir con quién atenderse; con un solo doctor repetiría el "Reservar cita"
 * que la página ya tiene, así que fuera de Equipo solo sale cuando hay de dónde elegir.
 */
export function Doctors({ doctors, full = false }: Props) {
  const offerBooking = doctors.length > 1;

  return (
    <div className={`team${full ? ' full' : ''}`}>
      {doctors.map((doctor) => (
        <article key={doctor.id} className="doctor" id={doctor.slug}>
          <Portrait doctor={doctor} />
          <div className="who">
            <h3>{doctor.name}</h3>
            {doctor.specialty ? <p className="specialty">{doctor.specialty}</p> : null}
            <p className="credentials">
              {doctor.cop_number ? `COP ${doctor.cop_number}` : null}
              {doctor.rne_number ? ` · RNE ${doctor.rne_number}` : null}
            </p>
            {!full && doctor.bio_short ? <p className="bio">{doctor.bio_short}</p> : null}
            {!full && offerBooking && doctor.accepts_online_booking ? (
              <AppLink
                href={paths.booking({ doctor: doctor.slug })}
                className="btn small quiet"
                aria-label={`Reservar cita con ${doctor.name}`}
              >
                Reservar cita
              </AppLink>
            ) : null}
          </div>
          {/* En Equipo, bajo el nombre: su presentación y el botón para atenderse con él; al lado, su formación y lo que atiende. */}
          {full ? (
            <>
              <div className="about">
                {doctor.bio_html ? <Html className="prose" html={doctor.bio_html} /> : null}
                {doctor.accepts_online_booking ? (
                  <AppLink
                    href={paths.booking({ doctor: doctor.slug })}
                    className="btn small"
                    aria-label={`Reservar cita con ${doctor.name}`}
                  >
                    <Icon name="calendar" />
                    Reservar cita
                  </AppLink>
                ) : null}
              </div>
              {doctor.education.length || doctor.treatments?.length ? (
                <div className="more">
                  {doctor.education.length ? (
                    <>
                      <h4>Formación</h4>
                      <ul className="education">
                        {doctor.education.map((line) => (
                          <li key={line}>{line}</li>
                        ))}
                      </ul>
                    </>
                  ) : null}
                  {doctor.treatments?.length ? (
                    <>
                      <h4>Atiende</h4>
                      <p className="treatments">
                        {doctor.treatments.map((treatment) => (
                          <AppLink key={treatment.slug} href={paths.treatment(treatment.slug)}>
                            {treatment.name}
                          </AppLink>
                        ))}
                      </p>
                    </>
                  ) : null}
                </div>
              ) : null}
            </>
          ) : null}
        </article>
      ))}
    </div>
  );
}
