import { useState, type FormEvent } from 'react';
import { problemOf } from '@/api/actions';
import { ApiError, apiUrl, post } from '@/api/client';
import type { Clinic, DataResponse } from '@/api/types';
import { AppLink } from '@/components/ui/AppLink';
import { useFlash } from '@/lib/flash';
import { paths } from '@/lib/paths';
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
