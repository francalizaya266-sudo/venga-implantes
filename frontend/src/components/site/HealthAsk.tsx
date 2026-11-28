import { AppLink } from '@/components/ui/AppLink';
import { paths } from '@/lib/paths';

interface Props {
  patientId: number;
  text: string;
  /** Junto a una cita es "Antes de la cita"; entre los datos de la persona, "Ficha de salud". */
  title?: string;
}

/** "Antes de la cita": a quien todavía no le conocemos la salud, el enlace a contarla en dos minutos. */
export function HealthAsk({ patientId, text, title = 'Antes de la cita' }: Props) {
  return (
    <AppLink href={paths.accountHealth(patientId)} className="health-ask" data-health-ask>
      <strong>{title}</strong>
      <span>{text}</span>
    </AppLink>
  );
}
