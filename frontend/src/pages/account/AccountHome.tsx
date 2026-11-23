import { useState } from 'react';
import { useAction } from '@/api/actions';
import { apiUrl } from '@/api/client';
import type { AccountData, Clinic, PlanCard } from '@/api/types';
import { AppointmentCard } from '@/components/site/AppointmentCard';
import { AppLink } from '@/components/ui/AppLink';
import { Icon } from '@/components/ui/Icon';
import { paths } from '@/lib/paths';

type Account = Extract<AccountData, { guest: false }>;

interface Props {
  account: Account;
  clinic: Clinic;
  howToPay: string;
}

/**
 * Mi cuenta. A un lado, de quién es la cuenta y el paso a cada parte; al otro, las citas que vienen, los planes y
 * pagos, y las citas anteriores con los datos. En el celular va todo en una columna, con "Salir" al final.
 *
 * Los montos, las citas pasadas y los datos personales llegan cuando este celular tocó un enlace de WhatsApp
 * (`elevated`), y siguen hasta que salga.
 */
export function AccountHome({ account, clinic, howToPay }: Props) {
  const { run, busy } = useAction();
  const { holder, elevated, upcoming } = account;
  const plans = account.plans ?? [];
  const balances = account.balances ?? [];
  const showsPlans = elevated && (plans.length > 0 || balances.length > 0);

  return (
    <div className="account">
      <aside className="account-side">
        <div className="who">
          <span className="avatar" aria-hidden="true">
            {holder ? holder.initials : <Icon name="user" />}
          </span>
          <div>
            <h2>Hola{holder ? `, ${holder.first_name}` : ''}</h2>
            <p>
              Citas de {account.several ? 'tu familia' : 'este celular'} · <span>{account.phone}</span>
            </p>
          </div>
        </div>
        <nav aria-label="Partes de Mi cuenta">
          <a href="#proximas">
            <Icon name="calendar" />
            Próximas citas
          </a>
          {showsPlans ? (
            <a href="#planes">
              <Icon name="wallet" />
              Planes y pagos
            </a>
          ) : null}
          <a href="#anteriores">
            <Icon name="clock" />
            {elevated ? 'Citas anteriores y datos' : 'Pagos, citas anteriores y datos'}
          </a>
        </nav>
        <form
          className="leave"
          onSubmit={(event) => {
            event.preventDefault();
            void run(apiUrl('account/logout'));
          }}
        >
          <button type="submit" className="link-btn" disabled={busy}>
            Salir en este celular
          </button>
        </form>
      </aside>

      <div className="account-main">
        <section className="account-card" aria-labelledby="proximas">
          <div className="spread">
            <h2 id="proximas">
              <Icon name="calendar" />
              Próximas citas
            </h2>
            <AppLink href={paths.booking()} className="btn small">
              {upcoming.length ? 'Reservar otra cita' : 'Reservar cita'}
            </AppLink>
          </div>

          <div className="appointments">
            {upcoming.length ? (
              upcoming.map((appointment) => (
                <AppointmentCard
                  key={appointment.id}
                  appointment={appointment}
                  clinic={clinic}
                  confirmUrl={apiUrl(`account/appointments/${appointment.id}/confirm`)}
                  changeHref={paths.accountChange(appointment.id)}
                  cancelUrl={apiUrl(`account/appointments/${appointment.id}/cancel`)}
                />
              ))
            ) : (
              <div className="panel">
                <p>No tienes citas próximas.</p>
              </div>
            )}
          </div>
        </section>

        {showsPlans ? (
          <section className="account-card" aria-labelledby="planes">
            <h2 id="planes">
              <Icon name="wallet" />
              Planes y pagos
            </h2>

            {plans.map((plan) => (
              <Plan key={plan.id} plan={plan} />
            ))}

            {balances.map((balance) => (
              <p key={balance.name} className="spread">
                <span>Saldo de {balance.name}</span>
                <strong className={balance.owed ? 'owed' : undefined}>{balance.text}</strong>
              </p>
            ))}
            <p className="muted small">{howToPay}</p>
          </section>
        ) : null}

        <section className="account-card" aria-labelledby="anteriores">
          <h2 id="anteriores">
            <Icon name="clock" />
            {elevated ? 'Citas anteriores y datos personales' : 'Planes, pagos, citas anteriores y datos'}
          </h2>
          {elevated ? (
            <>
              {account.past?.length ? (
                account.past.map((appointment) => (
                  <p key={appointment.id} className="spread">
                    <span>
                      {appointment.date} · <strong>{appointment.name}</strong> · {appointment.treatment}
                    </span>
                    <span className="badge quiet">{appointment.status}</span>
                  </p>
                ))
              ) : (
                <p className="muted">Todavía no hay citas anteriores.</p>
              )}

              <div className="family">
                {(account.family ?? []).map((patient) => (
                  <div key={patient.id} className="panel">
                    <h3>{patient.name}</h3>
                    <dl className="facts" style={{ marginTop: 8 }}>
                      <dt>Parentesco</dt>
                      <dd>{patient.relationship}</dd>
                      <dt>Documento</dt>
                      <dd>{patient.document ?? '—'}</dd>
                      <dt>Nacimiento</dt>
                      <dd>{patient.birth_date ?? '—'}</dd>
                      <dt>Correo</dt>
                      <dd>{patient.email ?? '—'}</dd>
                    </dl>
                  </div>
                ))}
              </div>
              <p className="muted small">¿Algún dato está mal? Escríbenos por WhatsApp y lo corregimos.</p>
            </>
          ) : (
            <>
              <p>Para cuidar tus datos, primero confirmamos que eres tú: te enviaremos un enlace por WhatsApp.</p>
              <SendLink />
            </>
          )}
        </section>
      </div>
    </div>
  );
}

/**
 * El enlace por WhatsApp que confirma que es el titular. Cómo salió se dice junto al botón, y no arriba de la
 * página, lejos de lo que se tocó; una vez enviado, el botón dice que ya está.
 */
function SendLink() {
  const { run, busy } = useAction();
  const [note, setNote] = useState<{ tone: 'ok' | 'bad'; text: string } | null>(null);
  const sent = note?.tone === 'ok';

  const send = () =>
    void run(
      apiUrl('account/verification'),
      {},
      {
        onDone: (message) => setNote({ tone: 'ok', text: message ?? 'Revisa tu WhatsApp: ahí te llega el enlace.' }),
        onRejected: (problem) => setNote({ tone: 'bad', text: problem }),
      },
    );

  return (
    <>
      <button type="button" className="btn quiet block" disabled={busy || sent} onClick={send}>
        {sent ? (
          <>
            <Icon name="check" />
            Listo, te lo enviamos
          </>
        ) : (
          'Enviarme el enlace'
        )}
      </button>
      {note ? (
        <p className={`flash ${note.tone}`} role={sent ? 'status' : 'alert'}>
          {note.text}
        </p>
      ) : null}
    </>
  );
}

/** Un plan que la familia aceptó: lo pagado, lo que falta, la próxima cuota y lo hecho. */
function Plan({ plan }: { plan: PlanCard }) {
  const { progress } = plan;

  return (
    <article className="plan-card">
      <div className="spread">
        <h3>{plan.title}</h3>
        <span className={`badge${plan.tone ? ` ${plan.tone}` : ''}`}>{plan.state}</span>
      </div>
      <p className="muted">{plan.who}</p>

      {progress ? (
        <>
          <div className="meter" role="img" aria-label={`Pagado ${progress.paid} de ${progress.total}`}>
            <span style={{ width: `${progress.percent}%` }} />
          </div>
          <dl className="facts">
            <dt>Total</dt>
            <dd>{progress.total}</dd>
            <dt>Pagado</dt>
            <dd>{progress.paid}</dd>
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
                  {progress.next.amount} · {progress.next.day}{' '}
                  <span className="muted small">{progress.next.label}</span>
                </dd>
              </>
            ) : null}
          </dl>
          <p className="muted small">{plan.payment}</p>
        </>
      ) : (
        <p>Va incluido en el 2x1 de {plan.included_in}: no tiene costo.</p>
      )}

      {plan.items.length ? (
        <details className="more">
          <summary>{plan.items_summary}</summary>
          <ul className="plan-items">
            {plan.items.map((item) => (
              <li key={item.id} className={item.done ? 'done' : undefined}>
                <span>{item.label}</span> <span className="muted">{item.state}</span>
              </li>
            ))}
          </ul>
        </details>
      ) : null}
    </article>
  );
}
