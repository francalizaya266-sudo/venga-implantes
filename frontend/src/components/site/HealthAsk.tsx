import { AppLink } from '@/components/ui/AppLink';
import { paths } from '@/lib/paths';

/** "Antes de la cita": a quien todavía no le conocemos la salud, el enlace a contarla en dos minutos. */
export function HealthAsk({ patientId, text }: { patientId: number; text: string }) {
  return (
    <AppLink href={paths.accountHealth(patientId)} className="health-ask" data-health-ask>
      <strong>Antes de la cita</strong>
      <span>{text}</span>
    </AppLink>
  );
}
