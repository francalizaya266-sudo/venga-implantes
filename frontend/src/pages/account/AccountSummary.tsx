import type { ReactNode } from 'react';
import type { AccountData, AppointmentCard, PlanCard, Welcome } from '@/api/types';
import { AppLink } from '@/components/ui/AppLink';
import { Icon, ToothIcon } from '@/components/ui/Icon';
import { paths } from '@/lib/paths';
import { lowerFirst } from '@/lib/text';

type Account = Extract<AccountData, { guest: false }>;

interface Props {
  account: Account;
  /** Lo que se ve con la persona elegida arriba (o toda la familia). */
  upcoming: AppointmentCard[];
  sessions: Welcome['sessions'];
  checkups: Welcome['checkups'];
  plans: PlanCard[];
  balances: NonNullable<Account['balances']>;
  /** Hay pagos o planes que contar: sin nada de eso, la línea de pagos no sale. */
  hasMoney: boolean;
  /** Se ve a toda la familia: lo que se debe es la suma de todos. */
  everyone: boolean;
  howToPay: string;
}

interface Line {
  key: string;
  href: string;
  icon: ReactNode;
  title: string;
  note: string;
  tone?: 'bad' | 'warn' | 'ok';
  /** Cuánto del tratamiento va hecho, de 0 a 100. */
  meter?: number;
}

/**
 * Lo importante de la cuenta, dicho en una frase por tema: cuándo es la próxima cita, cómo va el tratamiento y cómo
 * van los pagos. Cada frase lleva a su parte de la página. Sin verificar, los montos y el tratamiento se cambian por
 * la invitación a confirmar que es el titular.
 */
export function AccountSummary({
  account,
  upcoming,
  sessions,
  checkups,
  plans,
  balances,
  hasMoney,
  everyone,
  howToPay,
}: Props) {
  const lines = [
    appointmentLine(upcoming, sessions, checkups),
    account.elevated ? treatmentLine(plans, sessions) : null,
    account.elevated && hasMoney ? moneyLine(account, plans, balances, everyone, howToPay) : null,
    account.elevated
      ? null
      : {
          key: 'privado',
          href: '#privado',
          icon: <Icon name="shield-check" />,
          title: 'Tus tratamientos, pagos e historial',
          note: 'Confirma que eres tú con un enlace por WhatsApp y los verás aquí.',
        },
  ].filter((line): line is Line => line !== null);

  return (
    <section className="account-summary" aria-label="Resumen">
      <ul>
        {lines.map((line) => (
          <li key={line.key}>
            <AppLink href={line.href} className={`summary-line${line.tone ? ` ${line.tone}` : ''}`}>
              <span className="summary-icon" aria-hidden="true">
                {line.icon}
              </span>
              <span className="summary-text">
                <strong>{line.title}</strong>
                <span>{line.note}</span>
                {line.meter === undefined ? null : (
                  <span className="meter" aria-hidden="true">
                    <span style={{ width: `${line.meter}%` }} />
                  </span>
                )}
              </span>
            </AppLink>
          </li>
        ))}
      </ul>
    </section>
  );
}

/** La cita que sigue; sin cita, lo que falta agendar (una sesión del plan, un control) o la invitación a reservar. */
function appointmentLine(
  upcoming: AppointmentCard[],
  sessions: Welcome['sessions'],
  checkups: Welcome['checkups'],
): Line {
  const icon = <Icon name="calendar" />;
  const [next] = upcoming;

  if (next) {
    // "Tu cita" o "Cita de Mateo": dentro de la frase, "La cita de Mateo".
    const whose = next.kicker.startsWith('Cita de ') ? `La ${lowerFirst(next.kicker)}` : 'Tu cita';
    const more = upcoming.length - 1;

    return {
      key: 'cita',
      href: '#proximas',
      icon,
      title: next.countdown ? `${whose} es ${next.countdown.toLowerCase()}` : `${whose} ya tiene fecha`,
      note:
        next.when +
        (next.can_confirm ? ' Falta confirmarla.' : '') +
        (more > 0 ? ` Y ${more === 1 ? 'hay 1 cita más' : `hay ${more} citas más`}.` : ''),
      tone: next.can_confirm ? 'warn' : undefined,
    };
  }

  const [session] = sessions;

  if (session) {
    return { key: 'cita', href: '#proximas', icon, title: session.title, note: 'Falta agendar la próxima sesión.' };
  }

  const [checkup] = checkups;

  if (checkup) {
    return {
      key: 'cita',
      href: '#proximas',
      icon,
      title: checkup.kicker,
      note: `${checkup.title}. Ya toca una revisión.`,
    };
  }

  return {
    key: 'cita',
    href: paths.booking(),
    icon,
    title: 'No tienes citas por delante',
    note: 'Reserva cuando quieras: toma dos minutos.',
  };
}

/** Cómo va el tratamiento: de un plan, sus palabras ("1 de 3 hechos · 1 en curso"); de varios, la suma. */
function treatmentLine(plans: PlanCard[], sessions: Welcome['sessions']): Line | null {
  const [plan] = plans;

  if (!plan) {
    return null;
  }

  const done = plans.reduce((sum, each) => sum + each.steps.done, 0);
  const total = plans.reduce((sum, each) => sum + each.steps.total, 0);
  const meter = total > 0 ? Math.round((done / total) * 100) : 0;
  const base = { key: 'tratamiento', href: '#planes', icon: <ToothIcon />, meter };

  if (plans.length > 1) {
    return { ...base, title: `${plans.length} tratamientos en curso`, note: `${done} de ${total} hechos entre todos.` };
  }

  return {
    ...base,
    title: `${plan.title}: ${plan.steps.text}`,
    note: plan.next_session
      ? `Próxima sesión: ${lowerFirst(plan.next_session)}`
      : sessions.some((session) => session.patient_id === plan.patient_id)
        ? 'Falta agendar la próxima sesión.'
        : plan.who,
  };
}

/** Lo que hay que pagar ya (en rojo si algo venció) o que está al día, con el próximo pago. */
function moneyLine(
  account: Account,
  plans: PlanCard[],
  balances: NonNullable<Account['balances']>,
  everyone: boolean,
  howToPay: string,
): Line {
  const owing = balances.filter((balance) => balance.owed);
  const owed = everyone && account.owed_total ? `${account.owed_total} por pagar` : owing[0]?.text;
  const late = plans.some((plan) => plan.progress?.owed?.late);
  const [next] = plans
    .map((plan) => plan.progress?.next)
    .filter((due) => due !== null && due !== undefined)
    .sort((a, b) => a.date.localeCompare(b.date));
  const nextNote = next ? `Próximo pago: ${next.amount}, ${lowerFirst(next.day)}.` : null;
  const base = { key: 'pagos', href: '#pagos', icon: <Icon name="wallet" /> };

  if (owed) {
    return {
      ...base,
      title: late ? `${owed}, con un pago vencido` : owed,
      note: nextNote ?? howToPay,
      tone: late ? 'bad' : 'warn',
    };
  }

  return { ...base, title: 'Pagos al día', note: nextNote ?? 'No hay pagos pendientes.', tone: 'ok' };
}
