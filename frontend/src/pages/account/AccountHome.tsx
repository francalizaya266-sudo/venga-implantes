import { useState } from 'react';
import { useAction } from '@/api/actions';
import { apiUrl } from '@/api/client';
import type { AccountData, Clinic } from '@/api/types';
import { AppointmentCard } from '@/components/site/AppointmentCard';
import { CheckupCard, SessionCard } from '@/components/site/FollowUpCards';
import { AppLink } from '@/components/ui/AppLink';
import { Icon, ToothIcon } from '@/components/ui/Icon';
import { paths } from '@/lib/paths';
import { AccountFamily } from '@/pages/account/AccountFamily';
import { AccountHistory } from '@/pages/account/AccountHistory';
import { AccountMoney } from '@/pages/account/AccountMoney';
import { AccountNotices } from '@/pages/account/AccountNotices';
import { AccountPlans } from '@/pages/account/AccountPlans';
import { AccountSummary } from '@/pages/account/AccountSummary';

type Account = Extract<AccountData, { guest: false }>;

interface Props {
  account: Account;
  clinic: Clinic;
  howToPay: string;
}

/**
 * Mi cuenta. Arriba, lo importante en una frase por tema (la próxima cita, cómo va el tratamiento, cómo van los
 * pagos); debajo, cada parte completa: las citas que vienen y lo que falta agendar, los tratamientos con su avance
 * y sus cuotas, los pagos, las citas anteriores con lo que se llevó a casa, y los datos de cada persona. En una
 * familia, se elige arriba de quién ver. En el celular va todo en una columna, con "Salir" al final; en pantallas
 * anchas, el saludo y el paso a cada parte acompañan a un lado.
 *
 * Los montos, los tratamientos, las citas pasadas y los datos personales llegan cuando este celular tocó un enlace
 * de WhatsApp (`elevated`), y siguen hasta que salga. Al final, la app y dónde llegan los avisos (AccountNotices).
 */
export function AccountHome({ account, clinic, howToPay }: Props) {
  const { run, busy } = useAction();
  const { holder, elevated } = account;
  const family = account.family ?? [];
  const [chosen, setChosen] = useState<number | null>(null);
  // Solo se elige entre quienes siguen en la cuenta: si alguien ya no está, se vuelve a ver a todos.
  const only = family.length > 1 && family.some((member) => member.id === chosen) ? chosen : null;
  const ofChosen = <Row extends { patient_id: number }>(rows: Row[] | null): Row[] =>
    only === null ? (rows ?? []) : (rows ?? []).filter((row) => row.patient_id === only);

  const upcoming = ofChosen(account.upcoming);
  const sessions = ofChosen(account.sessions);
  const checkups = ofChosen(account.checkups);
  const plans = ofChosen(account.plans);
  const balances = ofChosen(account.balances);
  const payments = ofChosen(account.payments);
  const past = ofChosen(account.past);
  const people = only === null ? family : family.filter((member) => member.id === only);
  const chosenName = family.find((member) => member.id === only)?.short_name;

  // Cada parte sale si la cuenta tiene qué mostrar en ella, aunque la persona elegida no tenga nada.
  const showsPlans = elevated && (account.plans ?? []).length > 0;
  const showsMoney =
    elevated && (showsPlans || (account.balances ?? []).length > 0 || (account.payments ?? []).length > 0);

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
              <ToothIcon />
              Tratamientos
            </a>
          ) : null}
          {showsMoney ? (
            <a href="#pagos">
              <Icon name="wallet" />
              Pagos
            </a>
          ) : null}
          {elevated ? (
            <>
              <a href="#anteriores">
                <Icon name="clock" />
                Citas anteriores
              </a>
              <a href="#familia">
                <Icon name={account.several ? 'users' : 'user'} />
                {account.several ? 'Mi familia' : 'Mis datos'}
              </a>
            </>
          ) : (
            <a href="#privado">
              <Icon name="shield-check" />
              Tratamientos, pagos y datos
            </a>
          )}
          {account.notices ? (
            <a href="#avisos">
              <Icon name="bell" />
              Avisos
            </a>
          ) : null}
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
        {family.length > 1 ? (
          <div className="chips account-people" role="group" aria-label="De quién ver">
            <button type="button" className="chip" aria-pressed={only === null} onClick={() => setChosen(null)}>
              Toda la familia
            </button>
            {family.map((member) => (
              <button
                key={member.id}
                type="button"
                className="chip"
                aria-pressed={only === member.id}
                onClick={() => setChosen(member.id)}
              >
                {member.short_name}
              </button>
            ))}
          </div>
        ) : null}

        <AccountSummary
          account={account}
          upcoming={upcoming}
          sessions={sessions}
          checkups={checkups}
          plans={plans.filter((plan) => !plan.finished)}
          balances={balances}
          hasMoney={showsMoney}
          everyone={only === null}
          howToPay={howToPay}
        />

        <section className="account-card" aria-labelledby="proximas">
          <div className="spread">
            <h2 id="proximas">
              <Icon name="calendar" />
              Próximas citas
            </h2>
            <AppLink href={paths.booking(only === null ? {} : { para: only })} className="btn small">
              {upcoming.length ? 'Reservar otra cita' : 'Reservar cita'}
            </AppLink>
          </div>

          <div className="appointments">
            {upcoming.map((appointment) => (
              <AppointmentCard
                key={appointment.id}
                appointment={appointment}
                clinic={clinic}
                confirmUrl={apiUrl(`account/appointments/${appointment.id}/confirm`)}
                changeHref={paths.accountChange(appointment.id)}
                cancelUrl={apiUrl(`account/appointments/${appointment.id}/cancel`)}
                extras
              />
            ))}
            {/* Lo que falta agendar: la próxima sesión de quien sigue en tratamiento y el control de quien ya le toca. */}
            {sessions.map((session) => (
              <SessionCard key={`sesion-${session.patient_id}`} session={session} />
            ))}
            {checkups.map((checkup) => (
              <CheckupCard key={`control-${checkup.patient_id}`} checkup={checkup} />
            ))}
            {upcoming.length || sessions.length || checkups.length ? null : (
              <div className="panel">
                <p>{chosenName ? `${chosenName} no tiene citas próximas.` : 'No tienes citas próximas.'}</p>
              </div>
            )}
          </div>
        </section>

        {showsPlans ? (
          <section className="account-card" aria-labelledby="planes">
            <h2 id="planes">
              <ToothIcon />
              Tratamientos
            </h2>
            {plans.length ? (
              <AccountPlans plans={plans} sessions={sessions} />
            ) : (
              <p className="muted">{chosenName} no tiene tratamientos en esta cuenta.</p>
            )}
          </section>
        ) : null}

        {showsMoney ? (
          <section className="account-card" aria-labelledby="pagos">
            <h2 id="pagos">
              <Icon name="wallet" />
              Pagos
            </h2>
            <AccountMoney balances={balances} payments={payments} several={account.several} howToPay={howToPay} />
          </section>
        ) : null}

        {elevated ? (
          <>
            <section className="account-card" aria-labelledby="anteriores">
              <h2 id="anteriores">
                <Icon name="clock" />
                Citas anteriores
              </h2>
              <AccountHistory past={past} several={account.several} />
            </section>

            <section className="account-card" aria-labelledby="familia">
              <h2 id="familia">
                <Icon name={account.several ? 'users' : 'user'} />
                {account.several ? 'Mi familia' : 'Mis datos'}
              </h2>
              <AccountFamily family={people} clinic={clinic} />
            </section>
          </>
        ) : (
          <section className="account-card locked" aria-labelledby="privado">
            <h2 id="privado">
              <Icon name="shield-check" />
              Tratamientos, pagos, citas anteriores y datos
            </h2>
            <p>Para cuidar tus datos, primero confirmamos que eres tú: te enviaremos un enlace por WhatsApp.</p>
            <ul className="locked-list">
              <li>Cómo va cada tratamiento y cuándo es la próxima sesión.</li>
              <li>Lo que pagaste, lo que falta y la fecha de cada cuota.</li>
              <li>Tus citas anteriores, con las indicaciones y la receta.</li>
            </ul>
            <SendLink />
          </section>
        )}

        <AccountNotices notices={account.notices} />
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
