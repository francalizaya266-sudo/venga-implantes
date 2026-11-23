import { useEffect, useState } from 'react';
import { problemOf, useAction } from '@/api/actions';
import { apiUrl, post } from '@/api/client';
import type { DataResponse, NoticesState } from '@/api/types';
import { Icon } from '@/components/ui/Icon';
import { useInstall } from '@/lib/install';
import {
  PermissionNotGranted,
  currentSubscription,
  isInstalled,
  isIos,
  phoneSupport,
  subscribePhone,
  unsubscribePhone,
} from '@/lib/push';

type Note = { tone: 'ok' | 'bad'; text: string } | null;

/**
 * Avisos y app, en Mi cuenta. Instalar la web como app (un ícono que abre Mi cuenta) y elegir dónde llegan los avisos
 * de citas y cuotas: en la pantalla de este celular, por WhatsApp o por los dos. `notices` es null si este celular no
 * está verificado o el consultorio no activó los avisos: entonces solo se ofrece la app.
 */
export function AccountNotices({ notices }: { notices: NoticesState | null }) {
  const { canInstall, install } = useInstall();
  const [installed] = useState(isInstalled);
  const [ios] = useState(isIos);
  const showsApp = !installed && (canInstall || ios);

  if (!showsApp && !notices) {
    return null;
  }

  return (
    <section className="account-card" aria-labelledby="avisos">
      <h2 id="avisos">
        <Icon name="bell" />
        {notices ? 'Avisos en tu celular' : 'Mi cuenta como app'}
      </h2>

      {showsApp ? (
        <div className="panel stack install">
          {ios ? (
            <IphoneSteps forNotices={notices !== null} />
          ) : (
            <>
              <h3>Instala la app</h3>
              <p>Un ícono en tu pantalla que abre Mi cuenta directo, sin buscar la página.</p>
              <button type="button" className="btn quiet block" onClick={() => void install()}>
                Instalar la app
              </button>
            </>
          )}
        </div>
      ) : null}

      {notices ? <PhoneNotices notices={notices} /> : null}
    </section>
  );
}

/**
 * En iPhone no hay botón de instalar: se agrega desde Compartir. La app instalada no comparte lo que recuerda Safari,
 * así que la primera vez pide el código que se muestra aquí.
 */
function IphoneSteps({ forNotices }: { forNotices: boolean }) {
  const [code, setCode] = useState<{ code: string; minutes: number } | null>(null);
  const [problem, setProblem] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function showCode() {
    setBusy(true);
    setProblem(null);

    try {
      setCode((await post<DataResponse<{ code: string; minutes: number }>>(apiUrl('account/app-code'))).data);
    } catch (error) {
      setProblem(problemOf(error));
    } finally {
      setBusy(false);
    }
  }

  return (
    <>
      <h3>Instala la app en tu iPhone</h3>
      <p>
        Mi cuenta se abre con un toque desde tu pantalla
        {forNotices ? ', y en iPhone es la forma de recibir los avisos' : ''}.
      </p>
      <ol className="install-steps">
        <li>
          En Safari, toca <Icon name="share" /> <strong>Compartir</strong>.
        </li>
        <li>
          Elige <Icon name="plus-square" /> <strong>Agregar a inicio</strong> y luego <strong>Agregar</strong>.
        </li>
        <li>Abre la app desde el ícono nuevo: te pedirá un código para entrar.</li>
      </ol>
      {code ? (
        <p className="app-code" role="status">
          <span className="muted small">Tu código</span>
          <strong>{`${code.code.slice(0, 3)} ${code.code.slice(3)}`}</strong>
          <span className="muted small">Vale {code.minutes} minutos y sirve una vez.</span>
        </p>
      ) : (
        <button type="button" className="btn quiet block" disabled={busy} onClick={() => void showCode()}>
          Ver el código para la app
        </button>
      )}
      {problem ? (
        <p className="flash bad" role="alert">
          {problem}
        </p>
      ) : null}
    </>
  );
}

/** En este celular (activar o dejar los avisos) y por WhatsApp (además, o no). */
function PhoneNotices({ notices }: { notices: NoticesState }) {
  const { run, busy, errors } = useAction();
  const [support] = useState(phoneSupport);
  const [note, setNote] = useState<Note>(null);
  // El servidor recuerda que este celular los activó, pero el navegador pudo perder el permiso: manda el navegador.
  const [browserHas, setBrowserHas] = useState<boolean | null>(null);
  const [working, setWorking] = useState(false);
  const on = notices.this_phone && browserHas !== false && support === 'ready';

  useEffect(() => {
    let active = true;

    if (support === 'ready') {
      void currentSubscription()
        .then((subscription) => active && setBrowserHas(subscription !== null))
        .catch(() => active && setBrowserHas(false));
    }

    return () => {
      active = false;
    };
  }, [support]);

  const done = (text: string | null) => setNote(text ? { tone: 'ok', text } : null);
  const rejected = (text: string) => setNote({ tone: 'bad', text });

  async function activate() {
    setWorking(true);
    setNote(null);

    try {
      const subscription = await subscribePhone(notices.key);
      const saved = await run(apiUrl('account/notices/phone'), subscription, { onDone: done, onRejected: rejected });

      if (saved) {
        setBrowserHas(true);
        // Así se ven: el primero llega ahora mismo, desde el mismo celular.
        void navigator.serviceWorker.ready
          .then((registration) =>
            registration.showNotification('Avisos activados', {
              body: 'Así te llegarán los avisos de tus citas y cuotas.',
              icon: '/icon-192.png',
            }),
          )
          .catch(() => undefined);
      }
    } catch (error) {
      rejected(
        error instanceof PermissionNotGranted
          ? 'No se activaron porque no diste el permiso. Vuelve a tocar el botón y elige «Permitir».'
          : 'No pudimos activarlos en este navegador. Inténtalo de nuevo en un momento.',
      );
    } finally {
      setWorking(false);
    }
  }

  async function deactivate() {
    setWorking(true);
    setNote(null);
    await unsubscribePhone().catch(() => undefined);
    await run(apiUrl('account/notices/phone/off'), {}, { onDone: done, onRejected: rejected });
    setBrowserHas(false);
    setWorking(false);
  }

  // Lo que la API no aceptó (dejar WhatsApp sin un celular con avisos) también se dice aquí, junto a lo que se tocó.
  const refused = Object.values(errors)[0];
  const shown: Note = refused ? { tone: 'bad', text: refused } : note;
  const noOtherPhone = notices.phones === 0 || (notices.phones === 1 && notices.this_phone && !on);

  return (
    <>
      <div className="stack">
        <h3>En este celular</h3>
        {support === 'ready' ? (
          <>
            <p>
              {on
                ? 'Activos. Te avisamos en la pantalla cuando reservas, un día antes de tu cita, si cambia o se cancela, y cuando se acerca una cuota.'
                : 'Te avisamos en la pantalla cuando reservas, un día antes de tu cita, si cambia o se cancela, y cuando se acerca una cuota.'}
            </p>
            {on ? (
              <button
                type="button"
                className="btn quiet block"
                disabled={busy || working}
                onClick={() => void deactivate()}
              >
                Dejar de recibirlos aquí
              </button>
            ) : (
              <button type="button" className="btn block" disabled={busy || working} onClick={() => void activate()}>
                Activar avisos en este celular
              </button>
            )}
          </>
        ) : (
          <p className="muted">{UNAVAILABLE[support]}</p>
        )}
      </div>

      <div className="stack">
        <h3>Por WhatsApp</h3>
        <label className="check">
          <input
            type="checkbox"
            checked={notices.whatsapp}
            disabled={busy || working || noOtherPhone}
            onChange={(event) =>
              void run(
                apiUrl('account/notices/whatsapp'),
                { whatsapp: event.target.checked },
                { onDone: done, onRejected: rejected },
              )
            }
          />
          <span>Recibir los avisos también por WhatsApp</span>
        </label>
        <p className="muted small">
          {noOtherPhone
            ? 'Para dejar de recibirlos por WhatsApp, primero activa los avisos en tu celular.'
            : notices.whatsapp
              ? 'Puedes dejar WhatsApp y recibirlos solo en tu celular. Los enlaces para entrar a Mi cuenta siempre llegan por WhatsApp.'
              : 'Solo te llegan a tu celular. Si un aviso no llega a mostrarse ahí, te escribimos por WhatsApp.'}
        </p>
      </div>

      {shown ? (
        <p className={`flash ${shown.tone}`} role={shown.tone === 'ok' ? 'status' : 'alert'}>
          {shown.text}
        </p>
      ) : null}
    </>
  );
}

const UNAVAILABLE: Record<Exclude<ReturnType<typeof phoneSupport>, 'ready'>, string> = {
  'install-first': 'En iPhone, los avisos llegan en la app: instálala con los pasos de arriba y actívalos desde ahí.',
  blocked:
    'Este navegador tiene bloqueados los avisos de esta página. Permítelos en sus ajustes (el candado junto a la dirección) y vuelve aquí.',
  unsupported: 'Este navegador no puede mostrar avisos. Abre Mi cuenta en Chrome para activarlos.',
};
