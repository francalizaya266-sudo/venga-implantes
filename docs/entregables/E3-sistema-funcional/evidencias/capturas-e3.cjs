/**
 * Capturas del E3: una reserva de punta a punta. Un paciente nuevo reserva desde el celular y la misma cita aparece
 * en el panel de recepción: la cita, su aviso de WhatsApp, la agenda del día y "Hoy". Solo con los datos
 * de demostración del seeder: el paciente que reserva es inventado.
 *
 * Uso: DEMO_PASSWORD=<clave de demostración> node capturas-e3.cjs <URL base> <carpeta de salida> <id de la cita>
 *   1. node capturas-e3.cjs http://127.0.0.1:8000 capturas web      (reserva y captura la web)
 *   2. node capturas-e3.cjs http://127.0.0.1:8000 capturas panel 862 (captura el panel con la cita reservada)
 * La clave no va en el repositorio: es la del DemoSeeder.
 */
const { chromium } = require('playwright');

const [base = 'http://127.0.0.1:8000', out = 'capturas', part = 'web', appointmentId] = process.argv.slice(2);
const options = { locale: 'es-PE', timezoneId: 'America/Lima', deviceScaleFactor: 2 };

async function settle(page) {
  await page.waitForLoadState('networkidle').catch(() => {});
  await page.waitForTimeout(700);
}

async function web(browser) {
  const phone = await browser.newContext({ ...options, viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
  const page = await phone.newPage();

  await page.goto(`${base}/reservar`);
  await settle(page);
  await page.getByRole('button', { name: /Limpieza dental/ }).first().click();
  await page.getByRole('list', { name: 'Paso 2 de 3' }).waitFor();
  await settle(page);
  await page.screenshot({ path: `${out}/e3-web-1-horario.png` });

  await page.getByRole('button', { name: /^\d{1,2}:\d{2} [ap]\. m\.$/ }).first().click();
  await page.getByRole('list', { name: 'Paso 3 de 3' }).waitFor();
  await page.getByLabel('Tu celular').fill('951000123');
  await page.getByLabel('Tus nombres').fill('Lucía');
  await page.getByLabel('Tus apellidos').fill('Ccama Prueba');
  await page.getByRole('checkbox', { name: /Acepto la política de privacidad/ }).check();
  await settle(page);
  await page.screenshot({ path: `${out}/e3-web-2-datos.png` });

  await page.getByRole('button', { name: 'Reservar cita' }).click();
  await page.locator('#listo').waitFor();
  await settle(page);
  await page.screenshot({ path: `${out}/e3-web-3-reservada.png` });
  await phone.close();
}

async function login(browser, email) {
  const desk = await browser.newContext({ ...options, viewport: { width: 1280, height: 800 } });
  const page = await desk.newPage();
  await page.goto(`${base}/panel/login`);
  await settle(page);
  await page.locator('[id="form.email"]').fill(email);
  await page.locator('[id="form.password"]').fill(process.env.DEMO_PASSWORD ?? '');
  await page.locator('button[type=submit]').first().click();
  await settle(page);

  return page;
}

async function panel(browser) {
  const reception = await login(browser, 'recepcion@consultorio.test');
  await reception.goto(`${base}/panel/citas/${appointmentId}`);
  await settle(reception);
  await reception.screenshot({ path: `${out}/e3-panel-1-cita.png` });

  await reception.goto(`${base}/panel/avisos`);
  await settle(reception);
  await reception.screenshot({ path: `${out}/e3-panel-2-avisos.png` });

  const date = process.env.FECHA;
  await reception.goto(`${base}/panel/agenda${date ? `?date=${date}` : ''}`);
  await settle(reception);
  await reception.screenshot({ path: `${out}/e3-panel-3-agenda.png` });

  await reception.goto(`${base}/panel`);
  await settle(reception);
  await reception.screenshot({ path: `${out}/e3-panel-4-hoy.png` });
}

(async () => {
  const browser = await chromium.launch();
  await (part === 'panel' ? panel(browser) : web(browser));
  await browser.close();
})();
