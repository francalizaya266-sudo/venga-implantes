import { afterEach, describe, expect, it, vi } from 'vitest';
import { keyBytes, subscribePhone } from '@/lib/push';

const KEY = 'BEl62iUYgUivxIkv69yViEuiBIa-Ib9-SkvMeAtA3LFgDzkrxZJjSgSnfckjBJuBkr3qBUYIHBQFLXYp5Nksh8U';
const JSON_SUBSCRIPTION = {
  endpoint: 'https://push.example.test/abc',
  expirationTime: null,
  keys: { p256dh: 'p', auth: 'a' },
};

function browser({ subscribe, existing = null }: { subscribe: () => Promise<unknown>; existing?: unknown }) {
  const pushManager = { getSubscription: vi.fn(async () => existing), subscribe: vi.fn(subscribe) };
  const registration = { pushManager };

  vi.stubGlobal('Notification', { permission: 'default', requestPermission: vi.fn(async () => 'granted') });
  Object.defineProperty(navigator, 'serviceWorker', {
    configurable: true,
    value: { register: vi.fn(async () => registration), ready: Promise.resolve(registration) },
  });

  return pushManager;
}

afterEach(() => {
  vi.useRealTimers();
  Reflect.deleteProperty(navigator, 'serviceWorker');
});

describe('subscribePhone', () => {
  it('stops waiting when the browser push service never answers', async () => {
    vi.useFakeTimers();
    vi.stubGlobal('PushManager', {});
    browser({ subscribe: () => new Promise(() => {}) });

    const waiting = expect(subscribePhone(KEY)).rejects.toThrow('no respondió');
    await vi.advanceTimersByTimeAsync(20_000);

    await waiting;
  });

  it('replaces a subscription made with an old key and says how it is encrypted', async () => {
    vi.stubGlobal('PushManager', { supportedContentEncodings: ['aesgcm'] });
    const old = {
      options: { applicationServerKey: new Uint8Array([1, 2, 3]).buffer },
      unsubscribe: vi.fn(async () => true),
    };
    const pushManager = browser({ existing: old, subscribe: async () => ({ toJSON: () => JSON_SUBSCRIPTION }) });

    expect(await subscribePhone(KEY)).toEqual({ ...JSON_SUBSCRIPTION, content_encoding: 'aesgcm' });
    expect(old.unsubscribe).toHaveBeenCalled();
    expect(pushManager.subscribe).toHaveBeenCalledWith({ userVisibleOnly: true, applicationServerKey: keyBytes(KEY) });
  });

  it('keeps the subscription that already uses the clinic key', async () => {
    vi.stubGlobal('PushManager', {});
    const same = {
      options: { applicationServerKey: keyBytes(KEY).buffer },
      unsubscribe: vi.fn(async () => true),
      toJSON: () => JSON_SUBSCRIPTION,
    };
    const pushManager = browser({ existing: same, subscribe: async () => same });

    expect(await subscribePhone(KEY)).toEqual({ ...JSON_SUBSCRIPTION, content_encoding: 'aes128gcm' });
    expect(same.unsubscribe).not.toHaveBeenCalled();
    expect(pushManager.subscribe).not.toHaveBeenCalled();
  });
});

it('reads the clinic key as the 65 bytes the browser asks for', () => {
  const bytes = keyBytes(KEY);

  expect(bytes).toHaveLength(65);
  expect(bytes[0]).toBe(4);
});
