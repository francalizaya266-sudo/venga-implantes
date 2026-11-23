import { screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { NoticesState } from '@/api/types';
import { listenForInstall } from '@/lib/install';
import { mockApi, renderApp } from '@/test/app';
import { account, page } from '@/test/fixtures';

const ACCOUNT = '/api/v1/account';
const KEY = 'BEl62iUYgUivxIkv69yViEuiBIa-Ib9-SkvMeAtA3LFgDzkrxZJjSgSnfckjBJuBkr3qBUYIHBQFLXYp5Nksh8U';
const IPHONE =
  'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 Version/18.1 Mobile Safari/604.1';
const SUBSCRIPTION = {
  endpoint: 'https://fcm.googleapis.com/fcm/send/abc123',
  expirationTime: null,
  keys: {
    p256dh: 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM',
    auth: 'tBHItJI5svbpez7KI4CCXg',
  },
};

function notices(changes: Partial<NoticesState> = {}): NoticesState {
  return { key: KEY, this_phone: false, phones: 0, whatsapp: true, ...changes };
}

/** Un navegador que puede mostrar avisos (Chrome en Android), con lo que el paciente responde al permiso. */
function phoneBrowser({ permission = 'default', answer = 'granted', subscribed = false } = {}) {
  const subscription = {
    options: { applicationServerKey: null },
    toJSON: () => SUBSCRIPTION,
    unsubscribe: vi.fn(async () => true),
  };
  let current = subscribed ? subscription : null;
  const registration = {
    pushManager: {
      getSubscription: vi.fn(async () => current),
      subscribe: vi.fn(async () => (current = subscription)),
    },
    showNotification: vi.fn(async () => undefined),
  };

  vi.stubGlobal('Notification', { permission, requestPermission: vi.fn(async () => answer) });
  vi.stubGlobal('PushManager', function PushManager() {});
  Object.defineProperty(navigator, 'serviceWorker', {
    configurable: true,
    value: {
      register: vi.fn(async () => registration),
      ready: Promise.resolve(registration),
      getRegistration: vi.fn(async () => registration),
    },
  });

  return { registration, subscription };
}

function iphone({ installed = false } = {}) {
  Object.defineProperty(navigator, 'userAgent', { configurable: true, get: () => IPHONE });
  vi.stubGlobal('matchMedia', (query: string) => ({ matches: installed && query === '(display-mode: standalone)' }));
}

afterEach(() => {
  Reflect.deleteProperty(navigator, 'serviceWorker');
  Reflect.deleteProperty(navigator, 'userAgent');
});

describe('Notices on the patient phone', () => {
  it('turns them on in this phone, and only then lets the patient drop WhatsApp', async () => {
    let state = notices();
    const { registration } = phoneBrowser();
    const calls = mockApi({
      [ACCOUNT]: () => ({ body: page(account({ notices: state })) }),
      'POST /api/v1/account/notices/phone': () => {
        state = notices({ this_phone: true, phones: 1 });

        return { body: { message: 'Listo. Los avisos de tus citas y cuotas saldrán en la pantalla de este celular.' } };
      },
      'POST /api/v1/account/notices/whatsapp': () => {
        state = notices({ this_phone: true, phones: 1, whatsapp: false });

        return { body: { message: 'Listo. Tus avisos ya no llegarán por WhatsApp, solo a tu celular.' } };
      },
    });
    const { user } = renderApp('/mi-cuenta');

    const card = within(await screen.findByRole('region', { name: 'Avisos en tu celular' }));
    const whatsapp = card.getByRole('checkbox', { name: 'Recibir los avisos también por WhatsApp' });
    // Sin un celular con avisos, WhatsApp es el único camino: no se puede dejar.
    expect(whatsapp).toBeChecked();
    expect(whatsapp).toBeDisabled();
    expect(
      card.getByText('Para dejar de recibirlos por WhatsApp, primero activa los avisos en tu celular.'),
    ).toBeInTheDocument();

    await user.click(card.getByRole('button', { name: 'Activar avisos en este celular' }));

    expect(await card.findByText(/Listo. Los avisos de tus citas y cuotas saldrán/)).toBeInTheDocument();
    expect(calls.find((call) => call.url.endsWith('/notices/phone'))?.body).toEqual({
      ...SUBSCRIPTION,
      content_encoding: 'aes128gcm',
    });
    // El primero llega al momento, para que vea cómo se ven.
    expect(registration.showNotification).toHaveBeenCalledWith('Avisos activados', expect.anything());
    expect(card.getByRole('button', { name: 'Dejar de recibirlos aquí' })).toBeInTheDocument();
    expect(card.getByText(/^Activos\./)).toBeInTheDocument();

    await user.click(whatsapp);

    expect(await card.findByText(/ya no llegarán por WhatsApp/)).toBeInTheDocument();
    expect(calls.find((call) => call.url.endsWith('/notices/whatsapp'))?.body).toEqual({ whatsapp: false });
    expect(whatsapp).not.toBeChecked();
    expect(card.getByText(/Solo te llegan a tu celular/)).toBeInTheDocument();
  });

  it('says what to do when the patient does not allow them, and sends nothing', async () => {
    phoneBrowser({ answer: 'denied' });
    const calls = mockApi({ [ACCOUNT]: page(account({ notices: notices() })) });
    const { user } = renderApp('/mi-cuenta');

    await user.click(await screen.findByRole('button', { name: 'Activar avisos en este celular' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('No se activaron porque no diste el permiso.');
    expect(calls.filter((call) => call.method === 'POST')).toEqual([]);
  });

  it('stops them in this phone and in the browser', async () => {
    let state = notices({ this_phone: true, phones: 1 });
    const { subscription } = phoneBrowser({ permission: 'granted', subscribed: true });
    const calls = mockApi({
      [ACCOUNT]: () => ({ body: page(account({ notices: state })) }),
      'POST /api/v1/account/notices/phone/off': () => {
        state = notices();

        return { body: { message: 'Listo. Este celular ya no mostrará los avisos.' } };
      },
    });
    const { user } = renderApp('/mi-cuenta');

    await user.click(await screen.findByRole('button', { name: 'Dejar de recibirlos aquí' }));

    expect(await screen.findByText('Listo. Este celular ya no mostrará los avisos.')).toBeInTheDocument();
    expect(subscription.unsubscribe).toHaveBeenCalled();
    expect(calls.some((call) => call.url.endsWith('/notices/phone/off'))).toBe(true);
    expect(screen.getByRole('button', { name: 'Activar avisos en este celular' })).toBeInTheDocument();
  });

  it('shows the API refusal next to WhatsApp', async () => {
    phoneBrowser({ permission: 'granted', subscribed: true });
    mockApi({
      [ACCOUNT]: page(account({ notices: notices({ this_phone: true, phones: 2 }) })),
      'POST /api/v1/account/notices/whatsapp': {
        status: 422,
        body: {
          message: 'x',
          errors: {
            whatsapp: ['Primero activa los avisos en este celular: así no te quedas sin enterarte de tus citas.'],
          },
        },
      },
    });
    const { user } = renderApp('/mi-cuenta');

    await user.click(await screen.findByRole('checkbox', { name: 'Recibir los avisos también por WhatsApp' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Primero activa los avisos en este celular');
  });

  it('says how to unblock them when the browser blocked the page', async () => {
    phoneBrowser({ permission: 'denied' });
    mockApi({ [ACCOUNT]: page(account({ notices: notices() })) });
    renderApp('/mi-cuenta');

    expect(await screen.findByText(/Este navegador tiene bloqueados los avisos de esta página/)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Activar avisos en este celular' })).not.toBeInTheDocument();
  });
});

describe('Mi cuenta as an app', () => {
  it('on iPhone explains how to add it and shows the code the app asks for', async () => {
    iphone();
    const calls = mockApi({
      [ACCOUNT]: page(account({ notices: notices() })),
      'POST /api/v1/account/app-code': { data: { code: '482913', minutes: 15 }, message: null },
    });
    const { user } = renderApp('/mi-cuenta');

    const card = within(await screen.findByRole('region', { name: 'Avisos en tu celular' }));
    expect(card.getByRole('heading', { name: 'Instala la app en tu iPhone' })).toBeInTheDocument();
    expect(card.getByText(/En iPhone, los avisos llegan en la app/)).toBeInTheDocument();
    expect(card.queryByRole('button', { name: 'Activar avisos en este celular' })).not.toBeInTheDocument();

    await user.click(card.getByRole('button', { name: 'Ver el código para la app' }));

    expect(await card.findByText('482 913')).toBeInTheDocument();
    expect(card.getByText('Vale 15 minutos y sirve una vez.')).toBeInTheDocument();
    expect(calls.filter((call) => call.method === 'POST').map((call) => call.url)).toEqual([
      '/api/v1/account/app-code',
    ]);
  });

  it('in the installed iPhone app, opens Mi cuenta with that code', async () => {
    iphone({ installed: true });
    let entered = false;
    const calls = mockApi({
      [ACCOUNT]: () => ({ body: entered ? page(account()) : page({ guest: true }) }),
      'POST /api/v1/account/app-code/enter': (sent: unknown) => {
        if ((sent as { code: string }).code !== '482 913') {
          return {
            status: 422,
            body: {
              message: 'x',
              errors: {
                code: [
                  'Ese código no sirve: ya se usó o pasaron más de 15 minutos. Pide otro en Mi cuenta, en Safari.',
                ],
              },
            },
          };
        }

        entered = true;

        return { body: { message: 'Listo. Mi cuenta ya está abierta en esta app.' } };
      },
    });
    const { user } = renderApp('/mi-cuenta');

    const code = await screen.findByLabelText('Código');
    expect(screen.getByRole('heading', { name: 'Entra con tu código' })).toBeInTheDocument();
    await user.type(code, '111 111');
    await user.click(screen.getByRole('button', { name: 'Entrar' }));

    expect(await screen.findByText(/Ese código no sirve/)).toBeInTheDocument();
    expect(code).toHaveAttribute('aria-invalid', 'true');

    await user.clear(code);
    await user.type(code, '482 913');
    expect(screen.queryByText(/Ese código no sirve/)).not.toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Entrar' }));

    expect(await screen.findByRole('heading', { level: 2, name: 'Hola, Rosa' })).toBeInTheDocument();
    expect(calls.filter((call) => call.method === 'POST').map((call) => call.body)).toEqual([
      { code: '111 111' },
      { code: '482 913' },
    ]);
  });

  it('on Android offers to install it with one button, once', async () => {
    listenForInstall();
    mockApi({ [ACCOUNT]: page(account()) });
    const { user } = renderApp('/mi-cuenta');
    expect(await screen.findByRole('heading', { level: 2, name: 'Hola, Rosa' })).toBeInTheDocument();
    expect(screen.queryByRole('region', { name: 'Mi cuenta como app' })).not.toBeInTheDocument();

    // Chrome avisa que se puede instalar; la barra que mostraría por su cuenta no sale.
    const prompt = vi.fn(async () => undefined);
    const offer = Object.assign(new Event('beforeinstallprompt', { cancelable: true }), {
      prompt,
      userChoice: Promise.resolve({ outcome: 'accepted' }),
    });
    window.dispatchEvent(offer);
    expect(offer.defaultPrevented).toBe(true);

    await user.click(await screen.findByRole('button', { name: 'Instalar la app' }));

    expect(prompt).toHaveBeenCalledOnce();
    expect(screen.queryByRole('region', { name: 'Mi cuenta como app' })).not.toBeInTheDocument();
  });
});
