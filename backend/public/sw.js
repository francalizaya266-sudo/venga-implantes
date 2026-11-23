/*
 * Avisos en el celular (Mi cuenta): muestra en la pantalla los avisos de citas y cuotas que envía el consultorio y,
 * al tocarlos, abre Mi cuenta. No guarda páginas ni datos: la web se carga siempre como está en el servidor.
 */
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
  let notice = {};

  try {
    notice = event.data ? event.data.json() : {};
  } catch {
    notice = { body: event.data ? event.data.text() : '' };
  }

  event.waitUntil(
    self.registration.showNotification(notice.title || 'Tienes un aviso', {
      body: notice.body || '',
      icon: '/icon-192.png',
      lang: 'es-PE',
      // Un aviso nuevo de la misma cita reemplaza al anterior, y vuelve a sonar.
      tag: notice.tag || undefined,
      renotify: Boolean(notice.tag),
      data: { url: notice.url || '/mi-cuenta' },
    }),
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = new URL(event.notification.data?.url || '/mi-cuenta', self.location.origin).href;

  event.waitUntil(
    (async () => {
      const open = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
      const tab = open.find((client) => new URL(client.url).origin === self.location.origin);

      if (tab) {
        try {
          await tab.focus();
          await tab.navigate(url);

          return;
        } catch {
          // Una pestaña que no se deja mover: se abre otra.
        }
      }

      await self.clients.openWindow(url);
    })(),
  );
});
