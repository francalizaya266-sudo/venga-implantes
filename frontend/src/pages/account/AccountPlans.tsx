import type { PlanCard, PlanInstallment, PlanItem, Welcome } from '@/api/types';
import { SessionRequest } from '@/components/site/FollowUpCards';
import { Icon } from '@/components/ui/Icon';
import { keepAmountTogether } from '@/lib/text';

type Session = Welcome['sessions'][number];

interface Props {
  plans: PlanCard[];
  /** A quién le falta agendar su próxima sesión: su plan lleva el botón para pedirla. */
  sessions: Session[];
}

/**
 * Los tratamientos de la familia. Cada plan muestra su ruta (qué se hizo, qué está en curso y qué falta, con las
 * mismas palabras que lee el doctor) y, al lado, cómo va el pago con cada cuota. Los terminados y pagados quedan al
 * final, plegados.
 */
export function AccountPlans({ plans, sessions }: Props) {
  const current = plans.filter((plan) => !plan.finished);
  const finished = plans.filter((plan) => plan.finished);

  return (
    <>
      {current.map((plan) => (
        <Plan key={plan.id} plan={plan} session={sessions.find((session) => session.patient_id === plan.patient_id)} />
      ))}

      {finished.length ? (
        <details className="more plans-finished" open={current.length === 0}>
          <summary>
            {finished.length === 1 ? 'Ver 1 tratamiento terminado' : `Ver ${finished.length} tratamientos terminados`}
          </summary>
          {finished.map((plan) => (
            <Plan key={plan.id} plan={plan} />
          ))}
        </details>
      ) : null}
    </>
  );
}

function Plan({ plan, session }: { plan: PlanCard; session?: Session }) {
  const { progress, steps } = plan;

  return (
    <article className={`plan${plan.finished ? ' finished' : ''}`} data-plan>
      <div className="spread plan-head">
        <div>
          <h3>{plan.title}</h3>
          <p className="muted">{plan.who}</p>
        </div>
        <span className={`badge${plan.tone ? ` ${plan.tone}` : ''}`}>{plan.state}</span>
      </div>

      <div className="plan-body">
        <div className="plan-part">
          <h4>Tratamiento</h4>
          {steps.total > 0 ? (
            <>
              <p className="plan-count">{steps.text}</p>
              <div className="plan-meter" role="img" aria-label={`Avance del tratamiento: ${steps.text}`}>
                {plan.items.map((item) => (
                  <span key={item.id} className={item.status}>
                    <span style={{ width: `${Math.round(fractionDone(item) * 100)}%` }} />
                  </span>
                ))}
              </div>
              <ol className="plan-route">
                {plan.items.map((item) => (
                  <li key={item.id} className={item.status}>
                    <span className="stop" aria-hidden="true">
                      {item.status === 'done' ? <Icon name="check" /> : null}
                    </span>
                    <span className="what">
                      <strong>{item.label}</strong>
                      {item.detail ? <span className="muted small">{item.detail}</span> : null}
                    </span>
                    <span className="state">{item.state}</span>
                  </li>
                ))}
              </ol>
            </>
          ) : null}

          {plan.next_session ? (
            <p className="plan-next">
              <Icon name="calendar" />
              <span>
                Próxima sesión: <strong>{plan.next_session}</strong>
              </span>
            </p>
          ) : session && !plan.finished ? (
            <div className="plan-next ask">
              <p>Todavía no tiene fecha la próxima sesión.</p>
              <SessionRequest session={session} />
            </div>
          ) : null}
        </div>

        <div className="plan-part">
          <h4>Pago</h4>
          {progress ? (
            <>
              <p className="plan-count">
                {keepAmountTogether(progress.paid)} <span>pagado de {keepAmountTogether(progress.total)}</span>
              </p>
              <div className="meter" role="img" aria-label={`Pagado ${progress.paid} de ${progress.total}`}>
                <span style={{ width: `${progress.percent}%` }} />
              </div>
              <dl className="facts">
                <dt>Falta pagar</dt>
                <dd>{progress.remaining}</dd>
                {progress.owed ? (
                  <>
                    <dt>{progress.owed.label}</dt>
                    <dd className={progress.owed.late ? 'owed' : undefined}>{progress.owed.amount}</dd>
                  </>
                ) : null}
                {progress.next ? (
                  <>
                    <dt>Próximo pago</dt>
                    <dd>
                      {progress.next.amount}
                      <span className="muted small">
                        {progress.next.day} · {progress.next.label}
                      </span>
                    </dd>
                  </>
                ) : null}
              </dl>
              {plan.installments.length ? <Installments installments={plan.installments} /> : null}
              {plan.payment ? <p className="muted small">{plan.payment}</p> : null}
            </>
          ) : (
            <p>Va incluido en el 2x1 de {plan.included_in}: no tiene costo.</p>
          )}
        </div>
      </div>
    </article>
  );
}

/** Cuánto va hecho de un tratamiento: todo, nada o, en los de varias sesiones, la parte que lleva. */
function fractionDone(item: PlanItem): number {
  if (item.status === 'done') {
    return 1;
  }

  if (item.sessions && item.sessions.planned > 0) {
    return item.sessions.done / item.sessions.planned;
  }

  return item.status === 'in_progress' ? 0.5 : 0;
}

/** El cronograma: cada pago con su fecha, su monto y cómo va. Abierto cuando hay algo vencido o por pagar hoy. */
function Installments({ installments }: { installments: PlanInstallment[] }) {
  const pending = installments.some((installment) => installment.state === 'late' || installment.state === 'due');

  return (
    <details className="more" open={pending}>
      <summary>{installments.length === 1 ? 'Ver el pago' : `Ver los ${installments.length} pagos`}</summary>
      <ol className="plan-dues">
        {installments.map((installment) => (
          <li key={installment.id} className={installment.state}>
            <span className="what">
              <strong>{installment.label}</strong>
              <span className="muted small">{installment.day}</span>
            </span>
            <span className="amount">
              <strong>{installment.amount}</strong>
              <span className="small">{installment.state_label}</span>
              {installment.left ? <span className="small">Falta {installment.left}</span> : null}
            </span>
          </li>
        ))}
      </ol>
    </details>
  );
}
