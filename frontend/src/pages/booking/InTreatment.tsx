import type { Clinic, FamilyMember } from '@/api/types';
import { whatsappUrl } from '@/lib/whatsapp';

interface Props {
  person: FamilyMember;
  clinic: Clinic;
  /** En el primer paso: se le dice que para otra persona puede seguir. */
  others: boolean;
}

/** Quien está en tratamiento no reserva por la web: su doctor programa sus sesiones. */
export function InTreatment({ person, clinic, others }: Props) {
  const whatsapp = whatsappUrl(clinic, 'Hola, estoy en tratamiento y necesito un cambio en mis citas.');

  return (
    <div className="flash info" role="status">
      <p>
        {person.is_self
          ? `${person.first_name}, como estás en tratamiento, tu doctor programa tus próximas sesiones.`
          : `${person.first_name} está en tratamiento: su doctor programa sus próximas sesiones.`}{' '}
        {whatsapp ? (
          <>
            <a href={whatsapp} rel="noopener">
              Escríbenos por WhatsApp
            </a>{' '}
            si necesitas un cambio.
          </>
        ) : (
          'Escríbenos si necesitas un cambio.'
        )}
      </p>
      {others ? (
        <p className="muted small">Si la cita es para otra persona, sigue: la eliges en el último paso.</p>
      ) : null}
    </div>
  );
}
