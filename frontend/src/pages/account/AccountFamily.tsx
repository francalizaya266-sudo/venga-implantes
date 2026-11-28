import type { Clinic, AccountMember } from '@/api/types';
import { HealthAsk } from '@/components/site/HealthAsk';
import { AppLink } from '@/components/ui/AppLink';
import { paths } from '@/lib/paths';
import { whatsappUrl } from '@/lib/whatsapp';

interface Props {
  family: AccountMember[];
  clinic: Clinic;
}

/**
 * Cada persona de la cuenta con sus datos. A quien falta contar su salud se le ofrece hacerlo; un dato mal escrito
 * se pide corregir por WhatsApp con el mensaje ya empezado, y cada quien tiene su botón para reservarle una cita.
 */
export function AccountFamily({ family, clinic }: Props) {
  return (
    <>
      <div className="family">
        {family.map((member) => {
          const fix = whatsappUrl(clinic, `Hola, quiero corregir un dato de ${member.name} en su ficha: `);

          return (
            <article key={member.id} className="person">
              <div className="person-head">
                <span className="avatar" aria-hidden="true">
                  {member.initials}
                </span>
                <div>
                  <h3>{member.name}</h3>
                  <p className="muted small">{member.relationship}</p>
                </div>
              </div>

              <dl className="facts">
                <dt>Documento</dt>
                <dd>{member.document ?? '—'}</dd>
                <dt>Nacimiento</dt>
                <dd>{member.birth_date ?? '—'}</dd>
                <dt>Correo</dt>
                <dd>{member.email ?? '—'}</dd>
              </dl>

              {member.ask_health ? (
                <HealthAsk patientId={member.id} text={member.ask_health} title="Ficha de salud" />
              ) : (
                <p className="muted small">
                  El doctor ya tiene {member.is_self ? 'tu' : 'su'} ficha de salud. Si algo cambió, cuéntaselo en la
                  cita.
                </p>
              )}

              <div className="actions">
                <AppLink href={paths.booking({ para: member.id })} className="btn small quiet">
                  {member.is_self ? 'Reservar una cita' : `Reservar para ${member.short_name}`}
                </AppLink>
                {fix ? (
                  <a href={fix} className="link-btn" rel="noopener">
                    Corregir un dato
                  </a>
                ) : null}
              </div>
            </article>
          );
        })}
      </div>
      <p className="muted small">
        ¿Falta alguien de tu familia? <AppLink href={paths.booking()}>Reserva su primera cita</AppLink> desde este
        celular y queda en tu cuenta.{' '}
        {whatsappUrl(clinic) ? null : '¿Algún dato está mal? Escríbenos por WhatsApp y lo corregimos.'}
      </p>
    </>
  );
}
