import { useState, type FormEvent } from 'react';
import { problemOf, useAction } from '@/api/actions';
import { ApiError, apiUrl, post } from '@/api/client';
import type { Clinic, DataResponse } from '@/api/types';
import { AppLink } from '@/components/ui/AppLink';
import { useFlash } from '@/lib/flash';
import { paths } from '@/lib/paths';
import { isInstalled, isIos } from '@/lib/push';
import { whatsappUrl } from '@/lib/whatsapp';

interface Requested {
  phone: string;
  whatsapp_message: string;
}

/**
 * Mi cuenta desde un celular que no está recordado: se pide el enlace por WhatsApp. No dice si el número existe:
 * a todos se les responde lo mismo.
 */
export function AccountGuest({ clinic }: { clinic: Clinic }) {
  const flash = useFlash();
  const [phone, setPhone] = useState('');
  const [problem, setProblem] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [requested, setRequested] = useState<Requested | null>(null);
  const whatsapp = requested ? whatsappUrl(clinic, requested.whatsapp_message) : null;

  async function request(event: FormEvent) {
    event.preventDefault();
    setBusy(true);
    flash.clear();

    try {
      const { data } = await post<DataResponse<Requested>>(apiUrl('account/access'), { phone });
      setRequested(data);
    } catch (error) {
      const invalid = error instanceof ApiError ? error.fieldErrors.phone : undefined;

      if (invalid) {
        setProblem(invalid);
      } else {
        flash.show({ tone: 'bad', text: problemOf(error) });
      }
    } finally {
      setBusy(false);
    }
  }

  // En iPhone, la app instalada no comparte lo que recuerda Safari: se entra con el código que muestra Mi cuenta allá.
  if (isInstalled() && isIos()) {
    return <AppCodeEntry />;
  }

  return (
    <section className="stack-lg">
      <p className="lead">
        No necesitas contraseña: entras con el enlace que te enviamos por WhatsApp, y desde ahí este celular queda
        recordado.
      </p>

      {requested ? (
        <div className="panel stack" role="status">
          <h2>Te enviaremos tu enlace por WhatsApp</h2>
          <p>
            Si tu número está registrado, el consultorio te enviará tu enlace al {requested.phone} en su horario de
            atención. Tócalo desde este celular para entrar.
          </p>
          {whatsapp ? (
            <a href={whatsapp} className="btn whatsapp block" rel="noopener">
              Avisar al consultorio por WhatsApp
            </a>
          ) : null}
        </div>
      ) : (
        <form className="panel stack" onSubmit={(event) => void request(event)}>
          <h2>Pedir mi enlace</h2>
          <div className="field">
            <label htmlFor="phone">Tu celular</label>
            <div className="prefix">
              <span>+51</span>
              <input
                id="phone"
                name="phone"
                type="tel"
                inputMode="tel"
                autoComplete="tel-national"
                className="input"
                placeholder="987 654 321"
                required
                value={phone}
                onChange={(event) => {
                  setPhone(event.target.value);
                  setProblem(null);
                }}
                aria-invalid={problem ? true : undefined}
              />
            </div>
            {problem ? <span className="error">{problem}</span> : null}
          </div>
          <button type="submit" className="btn block" disabled={busy}>
            Pedir mi enlace por WhatsApp
          </button>
        </form>
      )}

      {/* Quien nunca reservó no tiene cuenta todavía: una línea basta, sin otro bloque que compita con el formulario. Con
          su primera reserva, este celular queda recordado sin esperar el enlace. */}
      <p className="muted">
        ¿Aún no tienes citas con nosotros? <AppLink href={paths.booking()}>Reserva la primera</AppLink> y entras a Mi
        cuenta al instante, sin esperar ningún enlace.
      </p>
    </section>
  );
}

/**
 * Mi cuenta en la app recién instalada: el código de 6 números que Mi cuenta muestra en Safari la deja abierta aquí.
 * Quien no la tiene abierta en ningún lado puede reservar o pedir su enlace desde la web.
 */
function AppCodeEntry() {
  const { run, busy, errors, fix } = useAction();
  const [code, setCode] = useState('');

  return (
    <section className="stack-lg">
      <form
        className="panel stack"
        onSubmit={(event) => {
          event.preventDefault();
          void run(apiUrl('account/app-code/enter'), { code });
        }}
      >
        <h2>Entra con tu código</h2>
        <p>
          Abre Mi cuenta en Safari, toca «Ver el código para la app» y escríbelo aquí. Solo se pide esta vez: luego la
          app queda abierta.
        </p>
        <div className="field">
          <label htmlFor="app-code">Código</label>
          <input
            id="app-code"
            name="code"
            className="input"
            inputMode="numeric"
            autoComplete="one-time-code"
            maxLength={7}
            placeholder="123 456"
            required
            value={code}
            onChange={(event) => {
              setCode(event.target.value);
              fix('code');
            }}
            aria-invalid={errors.code ? true : undefined}
          />
          {errors.code ? <span className="error">{errors.code}</span> : null}
        </div>
        <button type="submit" className="btn block" disabled={busy}>
          Entrar
        </button>
      </form>
      <p className="muted">
        ¿Todavía no entraste a Mi cuenta en Safari? <AppLink href={paths.booking()}>Reserva tu cita</AppLink> o pide tu
        enlace desde la página en Safari.
      </p>
    </section>
  );
}
