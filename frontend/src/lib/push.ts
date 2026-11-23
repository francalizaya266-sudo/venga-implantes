/**
 * Avisos en el celular: el navegador muestra en la pantalla los avisos de citas y cuotas que envía el consultorio.
 * Aquí se pide el permiso y se obtiene la dirección a la que el consultorio los envía (la suscripción), que luego se
 * guarda en Mi cuenta.
 */

/** Qué puede este navegador: mostrarlos ya, mostrarlos después de instalar la web (iPhone), nada, o los bloqueó. */
export type PhoneSupport = 'ready' | 'install-first' | 'blocked' | 'unsupported';

export interface PhoneSubscription extends PushSubscriptionJSON {
  content_encoding: string;
}

/** Sin señal, el servicio de avisos del navegador puede no responder nunca: se deja de esperar a los 20 segundos. */
const WAIT_MS = 20_000;

/** La web abierta como app instalada (desde el ícono de la pantalla), no en una pestaña del navegador. */
export function isInstalled(): boolean {
  return (
    window.matchMedia?.('(display-mode: standalone)').matches === true ||
    (navigator as Navigator & { standalone?: boolean }).standalone === true
  );
}

/** iPhone o iPad (el iPad se presenta como Mac, pero con pantalla táctil). */
export function isIos(): boolean {
  return (
    /iPad|iPhone|iPod/.test(navigator.userAgent) ||
    (/Macintosh/.test(navigator.userAgent) && navigator.maxTouchPoints > 1)
  );
}

export function phoneSupport(): PhoneSupport {
  // En iPhone, los avisos llegan solo con la web agregada a la pantalla de inicio (iOS 16.4 o más nuevo).
  if (isIos() && !isInstalled()) {
    return 'install-first';
  }

  if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
    return 'unsupported';
  }

  return Notification.permission === 'denied' ? 'blocked' : 'ready';
}

/** Lo que recibe los avisos aunque la web esté cerrada. Se registra al abrir la web, sin pedir nada. */
export function registerWorker(): Promise<ServiceWorkerRegistration> | null {
  return 'serviceWorker' in navigator ? navigator.serviceWorker.register('/sw.js', { scope: '/' }) : null;
}

/** La suscripción de este navegador, si ya aceptó los avisos. */
export async function currentSubscription(): Promise<PushSubscription | null> {
  if (!('serviceWorker' in navigator)) {
    return null;
  }

  const registration = await navigator.serviceWorker.getRegistration('/');

  return (await registration?.pushManager.getSubscription()) ?? null;
}

/**
 * Pide el permiso y se suscribe con la clave del consultorio. Si el paciente no acepta, lanza `PermissionNotGranted`.
 * Una suscripción anterior con otra clave (el consultorio cambió las suyas) se reemplaza.
 */
export async function subscribePhone(key: string): Promise<PhoneSubscription> {
  const permission = await Notification.requestPermission();

  if (permission !== 'granted') {
    throw new PermissionNotGranted(permission);
  }

  await registerWorker();
  const registration = await answered(navigator.serviceWorker.ready);
  const applicationServerKey = keyBytes(key);
  let subscription = await registration.pushManager.getSubscription();

  if (subscription && !sameKey(subscription.options.applicationServerKey, applicationServerKey)) {
    await subscription.unsubscribe();
    subscription = null;
  }

  subscription ??= await answered(registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey }));
  const encodings = (PushManager as unknown as { supportedContentEncodings?: string[] }).supportedContentEncodings;

  return {
    ...subscription.toJSON(),
    content_encoding:
      !encodings?.length || encodings.includes('aes128gcm') ? 'aes128gcm' : (encodings[0] ?? 'aes128gcm'),
  };
}

export async function unsubscribePhone(): Promise<void> {
  await (await currentSubscription())?.unsubscribe();
}

export class PermissionNotGranted extends Error {
  constructor(public readonly permission: NotificationPermission) {
    super(`Permiso de avisos: ${permission}`);
  }
}

/** La clave pública del consultorio (base64 de URL) en bytes, como la pide el navegador. */
export function keyBytes(key: string): Uint8Array<ArrayBuffer> {
  const base64 = (key + '='.repeat((4 - (key.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
  const raw = atob(base64);

  return Uint8Array.from(raw, (char) => char.charCodeAt(0));
}

function answered<T>(promise: Promise<T>): Promise<T> {
  let timer: ReturnType<typeof setTimeout> | undefined;
  const late = new Promise<never>((_, reject) => {
    timer = setTimeout(() => reject(new Error('El servicio de avisos del navegador no respondió')), WAIT_MS);
  });

  return Promise.race([promise, late]).finally(() => clearTimeout(timer));
}

function sameKey(current: ArrayBuffer | null, wanted: Uint8Array): boolean {
  if (!current) {
    return false;
  }

  const bytes = new Uint8Array(current);

  return bytes.length === wanted.length && bytes.every((byte, index) => byte === wanted[index]);
}
