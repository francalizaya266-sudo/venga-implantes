import type { AccountData } from '@/api/types';

type Account = Extract<AccountData, { guest: false }>;

interface Props {
  balances: NonNullable<Account['balances']>;
  payments: NonNullable<Account['payments']>;
  /** Con varias personas en la cuenta, cada pago dice de quién es. */
  several: boolean;
  howToPay: string;
}

/** Los pagos que se muestran sin abrir "ver todos". */
const RECENT = 4;

/**
 * Pagos: lo que cada persona debe o tiene a favor y lo que ya se pagó, lo más reciente arriba. Es solo para verlo:
 * se paga en el consultorio.
 */
export function AccountMoney({ balances, payments, several, howToPay }: Props) {
  const recent = payments.slice(0, RECENT);
  const older = payments.slice(RECENT);

  return (
    <>
      {balances.length ? (
        balances.map((balance) => (
          <p key={balance.patient_id} className="spread balance">
            <span>Saldo de {balance.name}</span>
            <strong className={balance.owed ? 'owed' : undefined}>{balance.text}</strong>
          </p>
        ))
      ) : (
        <p className="balance settled">No hay saldos por pagar.</p>
      )}

      {payments.length ? (
        <>
          <h3>Lo que pagaste</h3>
          <ul className="payments">
            {recent.map((payment) => (
              <Payment key={payment.id} payment={payment} several={several} />
            ))}
          </ul>
          {older.length ? (
            <details className="more">
              <summary>{older.length === 1 ? 'Ver 1 pago anterior' : `Ver ${older.length} pagos anteriores`}</summary>
              <ul className="payments">
                {older.map((payment) => (
                  <Payment key={payment.id} payment={payment} several={several} />
                ))}
              </ul>
            </details>
          ) : null}
        </>
      ) : null}

      <p className="muted small">{howToPay}</p>
    </>
  );
}

function Payment({ payment, several }: { payment: Props['payments'][number]; several: boolean }) {
  return (
    <li>
      <span className="what">
        <strong>{payment.date}</strong>
        <span className="muted small">{several ? `${payment.name} · ${payment.method}` : payment.method}</span>
      </span>
      <strong className="amount">{payment.amount}</strong>
    </li>
  );
}
