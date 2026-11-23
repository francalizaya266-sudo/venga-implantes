import type { Clinic } from '@/api/types';
import { Icon } from '@/components/ui/Icon';
import { Section } from '@/components/ui/Section';
import { whatsappUrl } from '@/lib/whatsapp';

interface Props {
  clinic: Clinic;
  heading?: string;
  headingLevel?: 'h1' | 'h2';
}

/**
 * Dos tarjetas: a la izquierda, la dirección, el horario y cada forma de escribir o llamar con su botón; a la
 * derecha, el mapa. "Cómo llegar" sale una sola vez: al pie del mapa o, sin mapa, con los demás botones.
 */
export function Contact({ clinic, heading = 'Dónde estamos', headingLevel: Heading = 'h2' }: Props) {
  const { hours } = clinic;
  const map = clinic.map_embed_url;
  const whatsapp = whatsappUrl(clinic);
  const directionsWithWays = !map && clinic.directions_url;

  return (
    <Section className="contact" aria-labelledby="contacto">
      <div className="contact-info">
        <Heading id="contacto">
          <span className="badge">
            <Icon name="pin" />
          </span>
          {heading}
        </Heading>
        {clinic.address ? (
          <div className="fact">
            <Icon name="pin" />
            <p className="address">{clinic.address}</p>
          </div>
        ) : null}

        {hours.lines.length ? (
          <div className="fact">
            <Icon name="clock" />
            <div>
              <p className="label">Horario de atención</p>
              {hours.status ? <p className={`status${hours.is_open ? ' open' : ''}`}>{hours.status}</p> : null}
              <dl className="hours">
                {hours.lines.map((line) => [
                  <dt key={`${line.days}-days`}>{line.days}</dt>,
                  <dd key={`${line.days}-hours`}>
                    {line.hours.map((range) => (
                      <span key={range}>{range}</span>
                    ))}
                  </dd>,
                ])}
              </dl>
              {hours.note ? <p className="muted small">{hours.note}</p> : null}
            </div>
          </div>
        ) : null}

        {/* Cada forma de comunicarse, con su botón. Sin teléfono fijo, se llama al celular del WhatsApp. */}
        {whatsapp || clinic.call_href || clinic.email || directionsWithWays ? (
          <ul className="ways">
            {whatsapp && clinic.whatsapp ? (
              <li>
                <span className="way-icon whatsapp">
                  <Icon name="whatsapp" />
                </span>
                <p>
                  <strong>WhatsApp</strong>
                  <span>{clinic.whatsapp.display}</span>
                </p>
                <a href={whatsapp} className="pill" rel="noopener" aria-label="Escribir por WhatsApp">
                  Escribir
                </a>
              </li>
            ) : null}
            {clinic.call_href ? (
              <li>
                <span className="way-icon">
                  <Icon name="phone" />
                </span>
                <p>
                  <strong>Teléfono</strong>
                  <span>{clinic.phone?.display ?? clinic.whatsapp?.display}</span>
                </p>
                <a href={clinic.call_href} className="pill" aria-label="Llamar al consultorio">
                  Llamar
                </a>
              </li>
            ) : null}
            {clinic.email ? (
              <li>
                <span className="way-icon">
                  <Icon name="mail" />
                </span>
                <p>
                  <strong>Correo</strong>
                  <span>{clinic.email}</span>
                </p>
                <a href={`mailto:${clinic.email}`} className="pill">
                  Enviar correo
                </a>
              </li>
            ) : null}
            {directionsWithWays ? (
              <li>
                <span className="way-icon">
                  <Icon name="pin" />
                </span>
                <p>
                  <strong>Cómo llegar</strong>
                  <span>En Google Maps</span>
                </p>
                <a href={directionsWithWays} className="pill" rel="noopener" target="_blank">
                  Cómo llegar
                </a>
              </li>
            ) : null}
          </ul>
        ) : null}
      </div>

      {map ? (
        <div className="map-block">
          <div className="map">
            {/* Debajo del mapa, la dirección escrita: mientras carga, o si Google no carga, la caja no queda vacía. */}
            <p className="map-behind" aria-hidden="true">
              <Icon name="pin" />
              <strong>{clinic.name}</strong>
              {clinic.address ? <span>{clinic.address}</span> : null}
            </p>
            <iframe
              src={map}
              title={`Mapa: ${clinic.address ?? ''}`}
              loading="lazy"
              referrerPolicy="no-referrer-when-downgrade"
              allowFullScreen
            />
          </div>
          {clinic.directions_url ? (
            <div className="map-foot">
              <p>
                <strong>{clinic.name}</strong>
                <span>{clinic.address}</span>
              </p>
              <a href={clinic.directions_url} className="pill" rel="noopener" target="_blank">
                Cómo llegar
              </a>
            </div>
          ) : null}
        </div>
      ) : null}
    </Section>
  );
}
