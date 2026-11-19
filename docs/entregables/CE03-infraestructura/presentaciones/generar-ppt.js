// Arma la presentación de CE03 en líneas generales (los cinco informes en una sola exposición), repartida entre los
// tres integrantes, con los colores de la UPeU (azul y amarillo). Los datos salen de los informes y de evidencias/.
//
//   npm install pptxgenjs@3.12.0 react-icons@5.4.0 react@18.3.1 react-dom@18.3.1 sharp@0.33.5
//   node docs/entregables/CE03-infraestructura/presentaciones/generar-ppt.js
//
// Cada diapositiva lleva arriba a la derecha el círculo amarillo con las iniciales de quien la expone, y sus notas
// del orador con lo que se dice.
const path = require("path");
const fs = require("fs");
const pptxgen = require("pptxgenjs");
const React = require("react");
const ReactDOMServer = require("react-dom/server");
const sharp = require("sharp");
const fa = require("react-icons/fa6");

const AQUI = __dirname;
const DIAGRAMAS = path.join(AQUI, "..", "diagramas");
const APPLY_THEME = process.env.APPLY_THEME; // ruta a apply_theme.js del skill de pptx (opcional)

const THEME = {
  name: "UPeU CE03",
  headFontFace: "Calibri",
  bodyFontFace: "Calibri",
  colors: {
    dk1: "1A1A1A", lt1: "FFFFFF", dk2: "0B2D5B", lt2: "EEF2F8",
    accent1: "0B2D5B", accent2: "F5B400", accent3: "2F5496", accent4: "8EA2C6",
    accent5: "C9D3E6", accent6: "C0392B", hlink: "2F5496", folHlink: "6B4C9A",
  },
};
const HEX = THEME.colors;
const AZUL = HEX.dk2, AMARILLO = HEX.accent2, GRIS = "5B6475";

const PERSONAS = {
  JY: { nombre: "Joselyn Yucra", rol: "Monitoreo y cumplimiento" },
  JA: { nombre: "Jhoel Apaza", rol: "Seguridad e infraestructura" },
  FC: { nombre: "Fran Calizaya", rol: "Aplicación y pruebas" },
};

// ---------- Íconos ----------
const cacheIconos = {};
async function icono(nombre, color) {
  const clave = nombre + color;
  if (!cacheIconos[clave]) {
    const svg = ReactDOMServer.renderToStaticMarkup(React.createElement(fa[nombre], { color: "#" + color, size: 256 }));
    const png = await sharp(Buffer.from(svg)).resize(256, 256).png().toBuffer();
    cacheIconos[clave] = "image/png;base64," + png.toString("base64");
  }
  return cacheIconos[clave];
}

async function medidas(archivo) {
  const m = await sharp(archivo).metadata();
  return { w: m.width, h: m.height };
}

// ---------- Presentación ----------
function nuevaPresentacion(titulo) {
  const pres = new pptxgen();
  pres.layout = "LAYOUT_WIDE"; // 13,33 × 7,5 pulgadas
  pres.theme = { headFontFace: THEME.headFontFace, bodyFontFace: THEME.bodyFontFace };
  pres.author = "Calizaya, Apaza y Yucra";
  pres.company = "Universidad Peruana Unión";
  pres.title = titulo;
  const C = pres.SchemeColor;

  pres.defineSlideMaster({
    title: "PORTADA",
    background: { color: AZUL },
    objects: [
      { text: { text: "UNIVERSIDAD PERUANA UNIÓN  ·  Ingeniería de Sistemas  ·  Campus Juliaca",
        options: { x: 0.8, y: 0.5, w: 11.7, h: 0.4, fontSize: 13, color: C.background1, charSpacing: 2, isTextBox: true, margin: 0 } } },
      { text: { text: "Sistema web de Clínica Vega Implant  ·  Evaluación del Perfil de Egreso  ·  2026-2",
        options: { x: 0.8, y: 6.75, w: 11.7, h: 0.35, fontSize: 12, color: C.background2, isTextBox: true, margin: 0 } } },
      { placeholder: { options: { name: "title", type: "title", x: 0.8, y: 2.0, w: 11.2, h: 1.7, fontSize: 40, bold: true, color: C.background1, valign: "bottom", align: "left", margin: 0 }, text: "" } },
      { placeholder: { options: { name: "body", type: "body", x: 0.8, y: 3.85, w: 11.2, h: 0.6, fontSize: 20, color: C.accent2, margin: 0 }, text: "" } },
    ],
  });
  pres.defineSlideMaster({
    title: "CONTENIDO",
    background: { color: "FFFFFF" },
    margin: [0.5, 0.6, 0.7, 0.6],
    objects: [
      { text: { text: "CE03 Infraestructura Tecnológica  ·  Clínica Vega Implant",
        options: { x: 0.6, y: 7.0, w: 8, h: 0.3, fontSize: 10, color: C.accent4, isTextBox: true, margin: 0 } } },
      { placeholder: { options: { name: "title", type: "title", x: 0.6, y: 0.35, w: 9.6, h: 0.85, fontSize: 30, bold: true, color: C.text2, valign: "middle", align: "left", margin: 0 }, text: "" } },
    ],
    slideNumber: { x: 12.2, y: 6.98, w: 0.6, h: 0.3, fontSize: 10, color: C.accent4, align: "right" },
  });
  pres.defineSlideMaster({
    title: "CIERRE",
    background: { color: AZUL },
    objects: [
      { placeholder: { options: { name: "title", type: "title", x: 0.8, y: 0.6, w: 11.7, h: 0.9, fontSize: 34, bold: true, color: C.background1, align: "left", margin: 0 }, text: "" } },
    ],
    slideNumber: { x: 12.2, y: 6.98, w: 0.6, h: 0.3, fontSize: 10, color: C.background2, align: "right" },
  });
  return pres;
}

// ---------- Piezas ----------
function expositor(pres, slide, iniciales) {
  const C = pres.SchemeColor;
  const p = PERSONAS[iniciales];
  slide.addShape(pres.shapes.OVAL, { x: 12.15, y: 0.45, w: 0.62, h: 0.62, fill: { color: C.accent2 }, line: { color: C.accent2 }, objectName: "Expositor círculo" });
  slide.addText(iniciales, { x: 12.15, y: 0.45, w: 0.62, h: 0.62, fontSize: 15, bold: true, color: C.text2, align: "center", valign: "middle", margin: 0, isTextBox: true, objectName: "Expositor iniciales" });
  slide.addText([{ text: p.nombre, options: { bold: true, breakLine: true } }, { text: p.rol }],
    { x: 9.4, y: 0.42, w: 2.6, h: 0.68, fontSize: 11, color: GRIS, align: "right", valign: "middle", margin: 0, isTextBox: true, objectName: "Expositor nombre" });
}

function contenido(pres, seccion, titulo, iniciales, notas) {
  const slide = pres.addSlide({ masterName: "CONTENIDO", sectionTitle: seccion });
  slide.addText(titulo, { placeholder: "title" });
  if (iniciales) expositor(pres, slide, iniciales);
  if (notas) slide.addNotes(notas);
  return slide;
}

async function tarjeta(pres, slide, x, y, w, h, { titulo, texto, icono: ico, tono = "claro" }) {
  const C = pres.SchemeColor;
  const oscuro = tono === "oscuro";
  slide.addShape(pres.shapes.ROUNDED_RECTANGLE, { x, y, w, h, rectRadius: 0.08, fill: { color: oscuro ? C.text2 : C.background2 }, line: { color: oscuro ? C.text2 : C.background2 }, objectName: "Tarjeta " + titulo });
  let tx = x + 0.25;
  if (ico) {
    slide.addShape(pres.shapes.OVAL, { x: x + 0.25, y: y + 0.25, w: 0.6, h: 0.6, fill: { color: oscuro ? C.accent2 : C.text2 }, line: { color: oscuro ? C.accent2 : C.text2 }, objectName: "Círculo " + titulo });
    slide.addImage({ data: await icono(ico, oscuro ? AZUL : "FFFFFF"), x: x + 0.39, y: y + 0.39, w: 0.32, h: 0.32, altText: titulo });
    tx = x + 1.0;
  }
  slide.addText(titulo, { x: tx, y: y + 0.2, w: x + w - tx - 0.2, h: 0.7, fontSize: 19, bold: true, color: oscuro ? C.background1 : C.text2, valign: "middle", margin: 0, isTextBox: true });
  if (texto) {
    slide.addText(texto, { x: x + 0.25, y: y + 1.0, w: w - 0.5, h: h - 1.15, fontSize: 17, color: oscuro ? C.background2 : C.text1, valign: "top", margin: 0, isTextBox: true, paraSpaceAfter: 4 });
  }
}

function cifra(pres, slide, x, y, w, valor, etiqueta, oscuro = false) {
  const C = pres.SchemeColor;
  slide.addText(valor, { x, y, w, h: 1.0, fontSize: 48, bold: true, color: oscuro ? C.accent2 : C.text2, margin: 0, valign: "bottom", isTextBox: true });
  slide.addText(etiqueta, { x, y: y + 1.05, w, h: 0.75, fontSize: 14, color: oscuro ? C.background2 : GRIS, margin: 0, valign: "top", isTextBox: true });
}

function tabla(pres, slide, filas, { x = 0.6, y = 1.5, w = 12.1, h, anchos, tam = 13 } = {}) {
  // Cada fila crece hasta 0,6" para ocupar el alto disponible (por defecto, hasta el pie de la diapositiva).
  const alto = h || 6.75 - y;
  const filaH = Math.min(0.6, alto / filas.length);
  tam += 2;
  const C = pres.SchemeColor;
  const datos = filas.map((fila, i) => fila.map((celda) => ({
    text: String(celda),
    options: i === 0
      ? { bold: true, color: C.background1, fill: { color: C.text2 }, fontSize: tam }
      : { color: C.text1, fill: { color: i % 2 === 0 ? C.background2 : C.background1 }, fontSize: tam },
  })));
  slide.addTable(datos, { x, y, w, colW: anchos, rowH: filaH, border: { type: "solid", pt: 0.5, color: "C9D3E6" }, margin: [3, 6, 3, 6], valign: "middle", autoPage: false });
}

async function imagen(slide, archivo, caja, alt) {
  const ruta = path.join(DIAGRAMAS, archivo);
  const { w, h } = await medidas(ruta);
  const escala = Math.min(caja.w / w, caja.h / h);
  const iw = w * escala, ih = h * escala;
  slide.addImage({ path: ruta, x: caja.x + (caja.w - iw) / 2, y: caja.y + (caja.h - ih) / 2, w: iw, h: ih, altText: alt });
}

function opcionesGrafico(extra) {
  return Object.assign({
    catAxisLabelColor: GRIS, valAxisLabelColor: GRIS, catAxisLabelFontSize: 12, valAxisLabelFontSize: 11,
    catAxisLabelFontFace: "+mn-lt", valAxisLabelFontFace: "+mn-lt", dataLabelFontFace: "+mn-lt", legendFontFace: "+mn-lt", titleFontFace: "+mj-lt",
    valGridLine: { color: "D9DEE8", size: 0.5 }, catGridLine: { style: "none" },
    showValue: true, dataLabelFormatCode: "0", dataLabelFontSize: 12, dataLabelColor: "1A1A1A", titleColor: AZUL, titleFontSize: 14,
  }, extra);
}

function portada(pres, titulo, codigo, notas) {
  const C = pres.SchemeColor;
  const slide = pres.addSlide({ masterName: "PORTADA", sectionTitle: "Portada" });
  slide.addText(titulo, { placeholder: "title" });
  slide.addText(codigo, { placeholder: "body" });
  const nombres = Object.values(PERSONAS).map((p, i, a) => ({ text: p.nombre, options: { breakLine: i < a.length - 1 } }));
  slide.addText("EXPONEN", { x: 0.8, y: 4.9, w: 3, h: 0.3, fontSize: 12, bold: true, color: C.accent2, charSpacing: 3, margin: 0, isTextBox: true });
  slide.addText(nombres, { x: 0.8, y: 5.25, w: 6, h: 1.2, fontSize: 18, color: C.background1, margin: 0, valign: "top", isTextBox: true, paraSpaceAfter: 2 });
  slide.addNotes(notas);
}

async function agenda(pres, bloques, notas) {
  const C = pres.SchemeColor;
  const slide = contenido(pres, "Portada", "Quién expone qué", null, notas);
  const w = 3.85;
  for (let i = 0; i < bloques.length; i++) {
    const [ini, temas] = bloques[i];
    const x = 0.6 + i * (w + 0.275);
    slide.addShape(pres.shapes.ROUNDED_RECTANGLE, { x, y: 1.55, w, h: 5.1, rectRadius: 0.08, fill: { color: C.background2 }, line: { color: C.background2 }, objectName: "Bloque " + ini });
    slide.addShape(pres.shapes.OVAL, { x: x + 0.3, y: 1.85, w: 0.8, h: 0.8, fill: { color: C.accent2 }, line: { color: C.accent2 } });
    slide.addText(ini, { x: x + 0.3, y: 1.85, w: 0.8, h: 0.8, fontSize: 20, bold: true, color: C.text2, align: "center", valign: "middle", margin: 0, isTextBox: true });
    slide.addText([{ text: PERSONAS[ini].nombre, options: { bold: true, breakLine: true } }, { text: PERSONAS[ini].rol, options: { fontSize: 13, color: GRIS } }],
      { x: x + 1.25, y: 1.8, w: w - 1.45, h: 0.9, fontSize: 17, color: C.text2, valign: "middle", margin: 0, isTextBox: true });
    slide.addText(temas.map((t, j) => ({ text: t, options: { bullet: true, breakLine: j < temas.length - 1 } })),
      { x: x + 0.3, y: 2.95, w: w - 0.6, h: 3.5, fontSize: 18, color: C.text1, valign: "top", margin: 0, isTextBox: true, paraSpaceAfter: 8 });
    // Unos 2 minutos por tema, como guía para repartir el tiempo de la exposición.
    slide.addText(`Tiempo aproximado: ${temas.length * 2} min`, { x: x + 0.3, y: 5.95, w: w - 0.6, h: 0.45, fontSize: 14, bold: true, color: C.accent3, margin: 0, isTextBox: true });
  }
}

function cierre(pres, titulo, puntos, notas) {
  const C = pres.SchemeColor;
  pres.addSection({ title: "Cierre" });
  const slide = pres.addSlide({ masterName: "CIERRE", sectionTitle: "Cierre" });
  slide.addText(titulo, { placeholder: "title" });
  const w = 3.75;
  puntos.forEach(([cab, txt], i) => {
    const x = 0.8 + i * (w + 0.225);
    slide.addShape(pres.shapes.ROUNDED_RECTANGLE, { x, y: 1.9, w, h: 3.9, rectRadius: 0.08, fill: { color: "16407A" }, line: { color: "16407A" }, objectName: "Cierre " + cab });
    slide.addText(String(i + 1).padStart(2, "0"), { x: x + 0.3, y: 2.15, w: 1.2, h: 0.7, fontSize: 32, bold: true, color: C.accent2, margin: 0, isTextBox: true });
    slide.addText(cab, { x: x + 0.3, y: 2.95, w: w - 0.6, h: 0.75, fontSize: 18, bold: true, color: C.background1, margin: 0, valign: "top", isTextBox: true });
    slide.addText(txt, { x: x + 0.3, y: 3.75, w: w - 0.6, h: 1.9, fontSize: 14, color: C.background2, margin: 0, valign: "top", isTextBox: true });
  });
  slide.addText("Gracias  ·  ¿Preguntas?", { x: 0.8, y: 6.2, w: 8, h: 0.5, fontSize: 20, bold: true, color: C.accent2, margin: 0, isTextBox: true });
  slide.addNotes(notas);
}

// ======================================================================================================
// Presentación única de CE03, en líneas generales: los cinco informes en una sola exposición.
// ======================================================================================================
async function deckGeneral() {
  const pres = nuevaPresentacion("Infraestructura Tecnológica · CE03");
  const C = pres.SchemeColor;
  pres.addSection({ title: "Portada" });
  portada(pres, "Infraestructura Tecnológica del sistema de Clínica Vega Implant", "CE03 · Diseño, SGSI, red y centro de datos en Azure",
    "Exponemos en líneas generales los cinco entregables de la línea de Infraestructura: el diseño de la red y del centro de datos (Semestre 1), y el SGSI, la red y el centro de datos implementados y probados en Azure (Semestre 2).");
  await agenda(pres, [
    ["JY", ["El problema y los entregables", "Misma arquitectura, otra nube", "Monitoreo y auditoría"]],
    ["JA", ["Arquitectura y seguridad", "Centro de datos en Azure", "Riesgos del SGSI"]],
    ["FC", ["Accesos y MFA", "Pruebas de red", "Continuidad probada"]],
  ], "Cada uno expone tres temas. El círculo amarillo arriba a la derecha indica quién habla en cada diapositiva.");

  // ---------- Joselyn: contexto ----------
  pres.addSection({ title: "Contexto" });
  let s = contenido(pres, "Contexto", "Una infraestructura para datos de salud", "JY",
    "La clínica guarda historias clínicas y radiografías, datos sensibles según la Ley 29733. La infraestructura tiene que publicar la web y el panel, proteger la base de datos, unir la sede con el centro de datos y detectar ataques. La línea pide cinco entregables, del diseño a la implementación probada.");
  tabla(pres, s, [
    ["Entregable", "Qué contiene", "Resultado"],
    ["S1-E1 Diseño de Red", "Topología, segmentos, VPN, seguridad perimetral", "12 pruebas planeadas"],
    ["S1-E2 Diseño de Centro de Datos", "Nube, sede, capacidad, RTO y RPO", "RPO 1 h · RTO 2 h"],
    ["S2-E1 SGSI", "Activos, riesgos, SoA, políticas, ética", "26 activos · 16 riesgos · 93 controles"],
    ["S2-E2 Red implementada", "VPN, firewalls, IDS, SIEM, pruebas", "23 de 24 pruebas cumplen"],
    ["S2-E3 Centro de datos", "Inventario, accesos, simulacros", "Restauración en 12 s"],
  ], { y: 1.55, anchos: [3.6, 5.0, 3.5], tam: 14 });

  s = contenido(pres, "Contexto", "Misma arquitectura, otra nube", "JY",
    "La guía propone VMware ESXi y AWS y admite cambiar la tecnología si se mantiene la equivalencia. Usamos Azure for Students: la sede de la clínica es una segunda red de Azure con los mismos segmentos y firewall que tendría el local, y cada servicio de AWS tiene su equivalente.");
  tabla(pres, s, [
    ["Guía de la línea", "En este proyecto"],
    ["Red local en VMware ESXi", "vnet-clinica (Azure North Central US)"],
    ["VLAN y port groups", "Subredes con su NSG y su ruta"],
    ["AWS VPC", "Azure VNet vnet-vega (Chile Central)"],
    ["Security Groups / NACL", "NSG por subred / nftables con política drop"],
    ["VPN Gateway", "strongSwan IKEv2 en cada firewall"],
    ["CloudWatch", "Azure Monitor y Log Analytics"],
  ], { y: 1.55, anchos: [5.2, 6.9], tam: 15 });

  // ---------- Jhoel: arquitectura, centro de datos y riesgos ----------
  pres.addSection({ title: "Arquitectura" });
  s = contenido(pres, "Arquitectura", "Dos redes unidas por una VPN", "JA",
    "Desde internet solo se llega al firewall de producción, que deja pasar la web al proxy de la DMZ; el proxy habla con la aplicación y la aplicación con MySQL, que no tiene IP pública. La sede se une por una VPN IPsec con AES-256: por ahí viajan los registros al SIEM y la copia horaria.");
  await imagen(s, "topologia-general.png", { x: 0.6, y: 1.4, w: 12.1, h: 5.45 }, "Topología general: producción y sede unidas por la VPN");

  s = contenido(pres, "Arquitectura", "Seguridad en capas", "JA",
    "Si una capa falla, la siguiente sigue bloqueando. NSG en cada subred, nftables con política drop en los firewalls, WAF en el proxy, Suricata en la WAN, un honeypot en la DMZ de la sede y Wazuh para correlacionar todo.");
  const capas = [
    ["FaShieldHalved", "NSG", "Filtro con estado en cada subred"],
    ["FaFire", "nftables", "Política drop y registro de cada bloqueo"],
    ["FaGlobe", "WAF", "ModSecurity + OWASP CRS en el proxy"],
    ["FaEye", "Suricata", "IDS en la WAN de los dos firewalls"],
    ["FaBug", "Honeypot", "OpenCanary: SSH y MySQL falsos"],
    ["FaChartLine", "Wazuh", "SIEM que recibe todos los registros"],
  ];
  for (let i = 0; i < capas.length; i++) {
    const [ico, t, d] = capas[i];
    await tarjeta(pres, s, 0.6 + (i % 3) * 4.1, 1.55 + Math.floor(i / 3) * 2.65, 3.9, 2.4, { titulo: t, texto: d, icono: ico });
  }

  s = contenido(pres, "Arquitectura", "Centro de datos en Azure, todo por código", "JA",
    "43 recursos creados desde el repositorio con Bicep y cloud-init: un comando levanta todo en unos 40 minutos. Ninguna clave de la nube vive en archivos: la VM de la aplicación usa su identidad administrada y GitHub publica con OIDC solo desde main.");
  cifra(pres, s, 0.6, 1.7, 2.9, "43", "recursos de Azure en dos regiones");
  cifra(pres, s, 3.7, 1.7, 2.9, "6", "máquinas virtuales Ubuntu 24.04");
  cifra(pres, s, 6.8, 1.7, 2.9, "10", "secretos, todos en Key Vault");
  cifra(pres, s, 9.9, 1.7, 2.9, "0", "claves guardadas en archivos");
  await tarjeta(pres, s, 0.6, 4.3, 5.95, 2.2, { titulo: "Datos privados", icono: "FaDatabase",
    texto: "MySQL Flexible sin acceso público, TLS 1.3 y 4 usuarios de mínimo privilegio." });
  await tarjeta(pres, s, 6.75, 4.3, 5.95, 2.2, { titulo: "Sin contraseñas guardadas", icono: "FaKey",
    texto: "Identidades administradas para la VM y OIDC para GitHub Actions." });

  s = contenido(pres, "Arquitectura", "SGSI: de 11 riesgos altos a ninguno", "JA",
    "Evaluamos 16 riesgos con ISO/IEC 27005, probabilidad por impacto. Antes de los controles había 11 altos o críticos; después ninguno pasa de 9, el nivel que acepta la dirección. Los dos críticos eran el robo de contraseñas del panel y la filtración de secretos.");
  const riesgos = [["R01", 20, 8], ["R02", 16, 8], ["R03", 15, 5], ["R04", 15, 4], ["R05", 20, 8], ["R06", 15, 5], ["R07", 12, 4], ["R08", 9, 9],
    ["R09", 9, 4], ["R10", 12, 6], ["R11", 12, 6], ["R12", 8, 3], ["R13", 12, 6], ["R14", 6, 2], ["R15", 10, 5], ["R16", 8, 4]];
  s.addChart(pres.charts.BAR, [
    { name: "Inherente", labels: riesgos.map((r) => r[0]), values: riesgos.map((r) => r[1]) },
    { name: "Residual", labels: riesgos.map((r) => r[0]), values: riesgos.map((r) => r[2]) },
  ], opcionesGrafico({ x: 0.6, y: 1.45, w: 12.1, h: 5.35, barDir: "col", chartColors: ["8EA2C6", AZUL], showLegend: true, legendPos: "t", legendFontSize: 12,
    showTitle: true, title: "Riesgo inherente y residual (P × I)", valAxisMaxVal: 25, dataLabelPosition: "outEnd", dataLabelFontSize: 10, barGapWidthPct: 60 }));

  // ---------- Fran: accesos, pruebas y continuidad ----------
  pres.addSection({ title: "Implementación" });
  s = contenido(pres, "Implementación", "El panel pide dos factores", "FC",
    "El panel se abre desde internet para que el doctor o la recepción trabajen fuera de la clínica. Por eso exige la contraseña propia y el código de la app autenticadora. Si se pierde el celular, hay códigos de recuperación, y el administrador puede reiniciar el segundo factor.");
  const acc = [
    ["FaMobileScreen", "MFA obligatorio", "Quien no lo tiene solo ve la pantalla para activarlo"],
    ["FaKey", "Contraseña propia", "La temporal se cambia en el primer ingreso"],
    ["FaGaugeHigh", "Límite de intentos", "Filament y nginx frenan los intentos repetidos (429)"],
    ["FaClockRotateLeft", "Auditoría", "Cambios y activación del MFA quedan registrados"],
  ];
  for (let i = 0; i < acc.length; i++) {
    const [ico, t, d] = acc[i];
    await tarjeta(pres, s, 0.6 + (i % 2) * 6.15, 1.55 + Math.floor(i / 2) * 2.6, 5.95, 2.35, { titulo: t, texto: d, icono: ico });
  }

  s = contenido(pres, "Implementación", "24 pruebas, esperado contra obtenido", "FC",
    "Diez positivas y diez negativas de conectividad y segmentación, una de recuperación y tres de detección. Cumplen 23; queda la del honeypot, porque en esta ronda la VM de laboratorio activa era la de recepción.");
  s.addChart(pres.charts.BAR, [
    { name: "Pruebas", labels: ["Positivas", "Negativas", "Recuperación", "Detección (1 pendiente)"], values: [10, 10, 1, 3] },
  ], opcionesGrafico({ x: 0.6, y: 1.45, w: 6.6, h: 5.3, barDir: "col", chartColors: [AZUL], showLegend: false, showTitle: true, title: "Pruebas por tipo", dataLabelPosition: "outEnd" }));
  tabla(pres, s, [
    ["Desde → hacia", "Obtenido"],
    ["Internet → prod. 22, 3306", "Filtrados"],
    ["Recepción → Wazuh :22", "Bloqueado"],
    ["Proxy → app :22", "Bloqueado"],
    ["App → MySQL", "TLS 1.3"],
    ["250 peticiones al panel", "80 × 429"],
    ["VPN entre sedes", "ESTABLISHED"],
  ], { x: 7.5, y: 1.6, w: 5.2, anchos: [3.3, 1.9], tam: 14 });

  s = contenido(pres, "Implementación", "Continuidad probada, no solo escrita", "FC",
    "Tres simulacros el 19/11: la copia de Blob se restauró en una base aparte en 12 segundos con los mismos totales que producción; la VPN cortada a propósito volvió sola en 1,4 segundos; y publicar una versión tomó 65. Además, la sede recibe una copia de la base cada hora.");
  cifra(pres, s, 0.6, 1.7, 2.9, "12 s", "restaurar la base desde Blob");
  cifra(pres, s, 3.7, 1.7, 2.9, "1,4 s", "la VPN volvió sola tras un corte");
  cifra(pres, s, 6.8, 1.7, 2.9, "65 s", "publicar una versión de la app");
  cifra(pres, s, 9.9, 1.7, 2.9, "1 h", "RPO con la copia horaria a la sede");
  const niveles = [
    ["FaDatabase", "MySQL Flexible", "Copias automáticas de 7 días, restauración a un punto en el tiempo"],
    ["FaCloud", "Blob Storage", "Copia diaria con versiones y borrado suave de 14 días"],
    ["FaHouseMedical", "Sede de la clínica", "Copia cada hora por la VPN; se guardan las últimas 48"],
  ];
  for (let i = 0; i < niveles.length; i++) {
    const [ico, t, d] = niveles[i];
    await tarjeta(pres, s, 0.6 + i * 4.1, 4.2, 3.9, 2.4, { titulo: t, texto: d, icono: ico });
  }

  // ---------- Joselyn: monitoreo y auditoría ----------
  pres.addSection({ title: "Control" });
  s = contenido(pres, "Control", "Wazuh ve lo que pasa", "JY",
    "Seis agentes activos, firewalls incluidos, y 3 442 alertas en un día. Lo importante son los escaneos reales de internet: 116 intentos de leer archivos .env, 15 de GraphQL y 13 al puerto 3306 de la sede. Ninguno llegó a un servicio.");
  s.addChart(pres.charts.BAR, [
    { name: "Alertas", labels: ["vm-proxy", "vm-app", "vm-fw-prod", "vm-fw-sede", "vm-wazuh", "vm-recepcion"], values: [1194, 827, 469, 354, 302, 296] },
  ], opcionesGrafico({ x: 0.6, y: 1.45, w: 7.6, h: 5.3, barDir: "bar", chartColors: [AZUL], showLegend: false, showTitle: true, title: "Alertas por agente el 19/11", dataLabelPosition: "outEnd" }));
  cifra(pres, s, 8.7, 1.6, 4.0, "116", "intentos de leer archivos .env");
  cifra(pres, s, 8.7, 3.6, 4.0, "13", "escaneos al MySQL de la sede");

  s = contenido(pres, "Control", "Auditoría interna: lo que falta", "JY",
    "La Declaración de Aplicabilidad tiene 63 de 91 controles aplicables implementados. La auditoría del 19/11 encontró seis no conformidades; la mayor son dos credenciales expuestas durante el despliegue que hay que rotar. Todas tienen responsable y plazo.");
  cifra(pres, s, 0.6, 1.6, 3.6, "69 %", "de los controles aplicables implementados");
  cifra(pres, s, 0.6, 3.9, 3.6, "6", "no conformidades, una mayor");
  tabla(pres, s, [
    ["Hallazgo", "Tipo"],
    ["Dos credenciales expuestas sin rotar", "Mayor"],
    ["La rama main no exige revisión ni CI", "Menor"],
    ["Key Vault con red pública", "Menor"],
    ["WAF en modo detección", "Menor"],
    ["Suricata solo detecta, no bloquea", "Observación"],
  ], { x: 4.8, y: 1.6, w: 7.9, anchos: [5.9, 2.0], tam: 14 });

  cierre(pres, "Qué sigue", [
    ["Cerrar lo urgente", "Rotar las dos credenciales expuestas y proteger la rama main."],
    ["Bloquear, no solo detectar", "WAF en modo On, Suricata como IPS y el honeypot activo."],
    ["Medir lo que falta", "El RTO de perder vm-app entera, en una ventana acordada."],
  ], "Cerramos con los próximos pasos y abrimos a preguntas.");
  return pres;
}

// ======================================================================================================
async function main() {
  const applyTheme = APPLY_THEME ? require(APPLY_THEME).applyTheme : null;
  const archivo = "CE03-Infraestructura-Tecnologica.pptx";
  const pres = await deckGeneral();
  const ruta = path.join(AQUI, archivo);
  await pres.writeFile({ fileName: ruta });
  if (applyTheme) await applyTheme(ruta, THEME);
  console.log(archivo);
}

main().catch((e) => { console.error(e); process.exit(1); });
