import type { Clinic, Closing as ClosingTexts } from '@/api/types';
import { AppLink } from '@/components/ui/AppLink';
import { Icon } from '@/components/ui/Icon';
import { artUrl } from '@/lib/art';
import { paths } from '@/lib/paths';
import { whatsappUrl } from '@/lib/whatsapp';

interface Props {
  clinic: Clinic;
  closing: ClosingTexts;
  /** Con qué empieza el WhatsApp, si el botón va ahí. */
  message: string;
}

/** La franja que cierra una página, sobre el pie: su frase (la del panel) y su botón, a reservar o al WhatsApp. */
export function Closing({ clinic, closing, message }: Props) {
  return (
    <div className="footer-cta">
      <img src={artUrl('diente-320')} alt="" width={320} height={320} loading="lazy" decoding="async" />
      <p>
        <strong>{closing.title}</strong> {closing.text}
      </p>
      {closing.whatsapp ? (
        <a href={whatsappUrl(clinic, message) ?? undefined} className="btn" rel="noopener">
          <Icon name="whatsapp" />
          Escribir por WhatsApp
        </a>
      ) : (
        <AppLink href={paths.booking()} className="btn">
          Reservar cita
          <Icon name="arrow-right" />
        </AppLink>
      )}
    </div>
  );
}
