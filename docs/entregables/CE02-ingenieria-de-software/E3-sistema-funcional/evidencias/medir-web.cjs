/**
 * Mide la web del paciente en un navegador de verdad (Chromium con Playwright) y revisa lo que se puede revisar
 * solo de accesibilidad. No es Lighthouse: da las mismas métricas de laboratorio (FCP, LCP, CLS, TBT y lo que se
 * descarga), pero no su puntaje. RNF-REN-02 y RNF-USA-03 del E1.
 *
 * Perfiles: "escritorio" sin límites, y "celular" como el de Lighthouse para móvil: pantalla de 412 × 823, red 4G
 * lenta (150 ms de latencia, 1,6 Mbit/s de bajada) y el procesador 4 veces más lento. Cada página se abre 3 veces
 * con la caché vacía y se toma la mediana.
 *
 * Uso: node medir-web.cjs [URL base] [rutas…]
 *      node medir-web.cjs http://127.0.0.1:8000 / /tratamientos/limpieza-dental /reservar
 * Necesita Playwright con Chromium (npm i -D playwright && npx playwright install chromium, o el que ya tenga la
 * máquina).
 */
const { chromium } = require('playwright');

const base = process.argv[2] ?? 'http://127.0.0.1:8000';
const routes = process.argv.length > 3 ? process.argv.slice(3) : ['/', '/tratamientos/limpieza-dental', '/reservar'];
const RUNS = 3;

const profiles = {
  escritorio: { viewport: { width: 1280, height: 800 } },
  celular: {
    viewport: { width: 412, height: 823 },
    deviceScaleFactor: 1.75,
    isMobile: true,
    hasTouch: true,
    network: { latency: 150, downloadThroughput: (1.6 * 1024 * 1024) / 8, uploadThroughput: (750 * 1024) / 8 },
    cpu: 4,
  },
};

/** Se instala antes de que cargue la página: guarda LCP, CLS y las tareas largas. */
function observe() {
  window.__metrics = { lcp: 0, cls: 0, longTasks: [] };
  new PerformanceObserver((list) => {
    for (const entry of list.getEntries()) {
      window.__metrics.lcp = entry.startTime;
    }
  }).observe({ type: 'largest-contentful-paint', buffered: true });
  new PerformanceObserver((list) => {
    for (const entry of list.getEntries()) {
      if (!entry.hadRecentInput) {
        window.__metrics.cls += entry.value;
      }
    }
  }).observe({ type: 'layout-shift', buffered: true });
  new PerformanceObserver((list) => {
    for (const entry of list.getEntries()) {
      window.__metrics.longTasks.push({ start: entry.startTime, duration: entry.duration });
    }
  }).observe({ type: 'longtask', buffered: true });
}

function collect() {
  const [navigation] = performance.getEntriesByType('navigation');
  const fcp = performance.getEntriesByName('first-contentful-paint')[0]?.startTime ?? 0;
  const resources = performance.getEntriesByType('resource');
  const { lcp, cls, longTasks } = window.__metrics;

  return {
    ttfb: navigation.responseStart,
    fcp,
    lcp,
    cls,
    // Tiempo total de bloqueo: lo que cada tarea larga pasa de 50 ms, entre el primer pintado y la carga.
    tbt: longTasks
      .filter((task) => task.start >= fcp)
      .reduce((sum, task) => sum + Math.max(0, task.duration - 50), 0),
    load: navigation.loadEventEnd,
    requests: resources.length + 1,
    kb: (resources.reduce((sum, entry) => sum + entry.transferSize, 0) + navigation.transferSize) / 1024,
  };
}

/** Lo que se puede revisar sin una persona: textos alternativos, nombres de controles, títulos, idioma y contraste. */
function audit() {
  const problems = [];
  const visible = (element) => element.getClientRects().length > 0 && getComputedStyle(element).visibility !== 'hidden';
  const nameOf = (element) =>
    (
      element.getAttribute('aria-label') ||
      (element.getAttribute('aria-labelledby') || '')
        .split(' ')
        .map((id) => document.getElementById(id)?.textContent ?? '')
        .join(' ') ||
      (element.id && document.querySelector(`label[for="${element.id}"]`)?.textContent) ||
      element.closest('label')?.textContent ||
      element.textContent ||
      element.getAttribute('title') ||
      element.querySelector('img[alt]')?.getAttribute('alt') ||
      ''
    ).trim();

  if (!document.documentElement.lang) {
    problems.push('El documento no dice su idioma.');
  }
  if (!document.title) {
    problems.push('La página no tiene título.');
  }
  const h1 = [...document.querySelectorAll('h1')].filter(visible);
  if (h1.length !== 1) {
    problems.push(`Tiene ${h1.length} títulos h1.`);
  }
  let level = 0;
  for (const heading of [...document.querySelectorAll('h1, h2, h3, h4, h5, h6')].filter(visible)) {
    const current = Number(heading.tagName[1]);
    if (level && current > level + 1) {
      problems.push(`Salta de h${level} a h${current}: "${heading.textContent.trim()}".`);
    }
    level = current;
  }
  for (const image of document.querySelectorAll('img:not([alt])')) {
    problems.push(`Imagen sin texto alternativo: ${image.getAttribute('src')}.`);
  }
  for (const control of document.querySelectorAll('a[href], button, input:not([type=hidden]), select, textarea')) {
    if (visible(control) && !nameOf(control) && control.getAttribute('aria-hidden') !== 'true') {
      problems.push(`Control sin nombre: <${control.tagName.toLowerCase()} class="${control.className}">.`);
    }
  }
  if (![...document.querySelectorAll('a[href^="#"]')].some((link) => /saltar/i.test(link.textContent))) {
    problems.push('No tiene enlace para saltar al contenido.');
  }

  // Contraste del texto con el primer fondo opaco detrás (no mira imágenes de fondo ni degradados).
  const rgb = (color) => (color.match(/[\d.]+/g) ?? []).map(Number);
  const luminance = ([r, g, b]) =>
    [r, g, b]
      .map((channel) => channel / 255)
      .map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4))
      .reduce((sum, c, i) => sum + c * [0.2126, 0.7152, 0.0722][i], 0);
  // El primer fondo de color sólido detrás del texto; null si antes hay una imagen o un degradado (no se puede saber).
  const background = (element) => {
    for (let node = element; node; node = node.parentElement) {
      const style = getComputedStyle(node);
      const color = rgb(style.backgroundColor);
      if (color.length === 3 || color[3] === 1) {
        return color.slice(0, 3);
      }
      if (style.backgroundImage !== 'none') {
        return null;
      }
    }
    return [255, 255, 255];
  };
  let checked = 0;
  let skipped = 0;
  const low = new Map();
  for (const element of document.querySelectorAll('body *')) {
    const ownText = [...element.childNodes].some((node) => node.nodeType === 3 && node.textContent.trim());
    if (!ownText || !visible(element)) {
      continue;
    }
    const style = getComputedStyle(element);
    const behind = background(element);
    if (behind === null) {
      skipped++;
      continue;
    }
    const [l1, l2] = [luminance(rgb(style.color)), luminance(behind)].sort((a, b) => b - a);
    const ratio = (l1 + 0.05) / (l2 + 0.05);
    const size = parseFloat(style.fontSize);
    const large = size >= 24 || (size >= 18.66 && Number(style.fontWeight) >= 700);
    checked++;
    if (ratio < (large ? 3 : 4.5)) {
      const key = `${style.color} sobre rgb(${behind.join(', ')}) (${ratio.toFixed(2)}:1)`;
      low.set(key, [...(low.get(key) ?? []), element.textContent.trim().slice(0, 40)]);
    }
  }
  for (const [colors, texts] of low) {
    problems.push(`Contraste bajo, ${colors}: ${texts.length} textos, por ejemplo "${texts[0]}".`);
  }

  return { problems, checkedTexts: checked, skippedTexts: skipped };
}

const median = (values) => [...values].sort((a, b) => a - b)[Math.floor(values.length / 2)];

(async () => {
  const browser = await chromium.launch();
  const results = [];
  const audits = [];

  for (const route of routes) {
    for (const [name, profile] of Object.entries(profiles)) {
      const runs = [];
      for (let run = 0; run < RUNS; run++) {
        const context = await browser.newContext({ ...profile, network: undefined, cpu: undefined, locale: 'es-PE' });
        const page = await context.newPage();
        const cdp = await context.newCDPSession(page);
        if (profile.network) {
          await cdp.send('Network.enable');
          await cdp.send('Network.emulateNetworkConditions', { offline: false, ...profile.network });
          await cdp.send('Emulation.setCPUThrottlingRate', { rate: profile.cpu });
        }
        await page.addInitScript(observe);
        await page.goto(base + route, { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        runs.push(await page.evaluate(collect));
        if (run === 0 && name === 'celular') {
          audits.push({ route, ...(await page.evaluate(audit)) });
        }
        await context.close();
      }
      const metric = (key) => median(runs.map((value) => value[key]));
      results.push({
        route,
        name,
        ttfb: metric('ttfb'),
        fcp: metric('fcp'),
        lcp: metric('lcp'),
        cls: metric('cls'),
        tbt: metric('tbt'),
        load: metric('load'),
        requests: metric('requests'),
        kb: metric('kb'),
      });
    }
  }
  await browser.close();

  console.log('| Página | Perfil | TTFB (ms) | FCP (ms) | LCP (ms) | CLS | TBT (ms) | Carga (ms) | Pedidos | Descarga (KB) |');
  console.log('|---|---|---|---|---|---|---|---|---|---|');
  for (const r of results) {
    console.log(
      `| \`${r.route}\` | ${r.name} | ${r.ttfb.toFixed(0)} | ${r.fcp.toFixed(0)} | ${r.lcp.toFixed(0)} | ${r.cls.toFixed(3)} | ${r.tbt.toFixed(0)} | ${r.load.toFixed(0)} | ${r.requests} | ${r.kb.toFixed(0)} |`,
    );
  }
  console.log('');
  for (const a of audits) {
    console.log(
      `Accesibilidad de \`${a.route}\` (contraste de ${a.checkedTexts} textos; ${a.skippedTexts} sobre imagen o degradado, sin revisar): ${a.problems.length ? '' : 'sin observaciones.'}`,
    );
    for (const problem of a.problems) {
      console.log(`- ${problem}`);
    }
  }
})();
