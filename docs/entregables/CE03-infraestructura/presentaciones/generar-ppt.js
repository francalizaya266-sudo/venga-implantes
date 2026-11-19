// Arma la presentación única de CE03 (lo que se hizo en cada entregable, con cifras reales), repartida entre los tres
// integrantes, con los colores de la UPeU (azul y amarillo). Los datos salen de los informes y de evidencias/.
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
  const agregar = pres.addSlide.bind(pres);
  pres.numero = 0;
  pres.addSlide = (opciones) => { pres.numero += 1; return agregar(opciones); };

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
    title: "PARTE",
    background: { color: AZUL },
    objects: [
      { placeholder: { options: { name: "title", type: "title", x: 0.8, y: 2.3, w: 11.0, h: 1.8, fontSize: 40, bold: true, color: C.background1, valign: "bottom", align: "left", margin: 0 }, text: "" } },
      { placeholder: { options: { name: "body", type: "body", x: 0.8, y: 4.25, w: 11.0, h: 0.6, fontSize: 20, color: C.accent2, margin: 0 }, text: "" } },
    ],
    slideNumber: { x: 12.2, y: 6.98, w: 0.6, h: 0.3, fontSize: 10, color: C.background2, align: "right" },
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

function contenido(pres, seccion, titulo, iniciales, notas, entregable) {
  const C = pres.SchemeColor;
  const slide = pres.addSlide({ masterName: "CONTENIDO", sectionTitle: seccion });
  slide.addText(titulo, { placeholder: "title" });
  if (iniciales) expositor(pres, slide, iniciales);
  if (notas) slide.addNotes(notas);
  // El entregable al que pertenece esta diapositiva, al pie, para ubicarse.
  if (entregable) {
    slide.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: 4.55, y: 6.93, w: 7.5, h: 0.38, rectRadius: 0.19, fill: { color: C.accent2 }, line: { color: C.accent2 }, objectName: "Etiqueta del entregable" });
    slide.addText(entregable, { x: 4.55, y: 6.93, w: 7.5, h: 0.38, fontSize: 11, bold: true, color: C.text2, align: "center", valign: "middle", margin: 0, isTextBox: true });
  }
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
    const [ini, temas, minutos] = bloques[i];
    const x = 0.6 + i * (w + 0.275);
    slide.addShape(pres.shapes.ROUNDED_RECTANGLE, { x, y: 1.55, w, h: 5.1, rectRadius: 0.08, fill: { color: C.background2 }, line: { color: C.background2 }, objectName: "Bloque " + ini });
    slide.addShape(pres.shapes.OVAL, { x: x + 0.3, y: 1.85, w: 0.8, h: 0.8, fill: { color: C.accent2 }, line: { color: C.accent2 } });
    slide.addText(ini, { x: x + 0.3, y: 1.85, w: 0.8, h: 0.8, fontSize: 20, bold: true, color: C.text2, align: "center", valign: "middle", margin: 0, isTextBox: true });
    slide.addText([{ text: PERSONAS[ini].nombre, options: { bold: true, breakLine: true } }, { text: PERSONAS[ini].rol, options: { fontSize: 13, color: GRIS } }],
      { x: x + 1.25, y: 1.8, w: w - 1.45, h: 0.9, fontSize: 17, color: C.text2, valign: "middle", margin: 0, isTextBox: true });
    slide.addText(temas.map((t, j) => ({ text: t, options: { bullet: true, breakLine: j < temas.length - 1 } })),
      { x: x + 0.3, y: 2.95, w: w - 0.6, h: 3.5, fontSize: 18, color: C.text1, valign: "top", margin: 0, isTextBox: true, paraSpaceAfter: 8 });
    // Unos 2 minutos por tema, como guía para repartir el tiempo de la exposición.
    slide.addText(`Tiempo aproximado: ${minutos || temas.length * 2} min`, { x: x + 0.3, y: 5.95, w: w - 0.6, h: 0.45, fontSize: 14, bold: true, color: C.accent3, margin: 0, isTextBox: true });
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


// Portada de cada parte: número, nombre del entregable, semestre y quién lo expone. Solo el título, sin tablas.
function parte(pres, seccion, numero, titulo, subtitulo, iniciales, notas) {
  const C = pres.SchemeColor;
  pres.addSection({ title: seccion });
  const slide = pres.addSlide({ masterName: "PARTE", sectionTitle: seccion });
  slide.addText(numero, { x: 0.8, y: 1.0, w: 3, h: 1.2, fontSize: 60, bold: true, color: C.accent2, margin: 0, valign: "bottom", isTextBox: true });
  slide.addText(titulo, { placeholder: "title" });
  slide.addText(subtitulo, { placeholder: "body" });
  const p = PERSONAS[iniciales];
  slide.addShape(pres.shapes.OVAL, { x: 0.8, y: 5.5, w: 0.75, h: 0.75, fill: { color: C.accent2 }, line: { color: C.accent2 } });
  slide.addText(iniciales, { x: 0.8, y: 5.5, w: 0.75, h: 0.75, fontSize: 17, bold: true, color: C.text2, align: "center", valign: "middle", margin: 0, isTextBox: true });
  slide.addText([{ text: "Expone " + p.nombre, options: { bold: true, breakLine: true } }, { text: p.rol }],
    { x: 1.75, y: 5.45, w: 6, h: 0.85, fontSize: 15, color: C.background1, valign: "middle", margin: 0, isTextBox: true });
  slide.addNotes(notas);
}

async function cuadricula(pres, s, items, columnas = 2) {
  const w = columnas === 2 ? 5.95 : 3.9, paso = columnas === 2 ? 6.15 : 4.1, h = columnas === 2 ? 2.35 : 2.4, salto = columnas === 2 ? 2.6 : 2.65;
  for (let i = 0; i < items.length; i++) {
    const [ico, t, d] = items[i];
    await tarjeta(pres, s, 0.6 + (i % columnas) * paso, 1.55 + Math.floor(i / columnas) * salto, w, h, { titulo: t, texto: d, icono: ico });
  }
}

function consola(s, lineas, caja) {
  s.addText(lineas.map((t, i) => ({ text: t, options: { breakLine: i < lineas.length - 1 } })),
    Object.assign({ fontFace: "Courier New", fontSize: 14, color: "FFFFFF", fill: { color: "1A1A1A" }, margin: 14, valign: "middle", isTextBox: true, objectName: "Salida de consola" }, caja));
}

const RIESGOS = [["R01", 20, 8], ["R02", 16, 8], ["R03", 15, 5], ["R04", 15, 4], ["R05", 20, 8], ["R06", 15, 5], ["R07", 12, 4], ["R08", 9, 9],
  ["R09", 9, 4], ["R10", 12, 6], ["R11", 12, 6], ["R12", 8, 3], ["R13", 12, 6], ["R14", 6, 2], ["R15", 10, 5], ["R16", 8, 4]];

// ======================================================================================================
// Presentación única de CE03 en 25 diapositivas: lo que se hizo en cada entregable, con cifras reales.
// ======================================================================================================
async function deckGeneral() {
  const pres = nuevaPresentacion("Infraestructura Tecnológica · CE03");
  const C = pres.SchemeColor;
  let s;

  pres.addSection({ title: "Portada" });
  portada(pres, "Infraestructura Tecnológica del sistema de Clínica Vega Implant", "CE03 · Diseño, SGSI, red y centro de datos en Azure",
    "Presentamos lo que hicimos en la línea de Infraestructura: el diseño de la red y del centro de datos, el SGSI, y la red y el centro de datos funcionando en Azure, con cifras reales del 19/11.");
  await agenda(pres, [
    ["JY", ["Equivalencia tecnológica", "S2-E1 · SGSI", "Evidencias y cierre"], 7],
    ["JA", ["S1-E1 · Diseño de red", "S2-E2 · Red implementada"], 8],
    ["FC", ["S1-E2 · Diseño de centro de datos", "S2-E3 · Centro de datos implementado"], 8],
  ], "Jhoel expone lo de red, Fran lo de centro de datos y Joselyn el SGSI y el cierre. El círculo amarillo dice quién habla y la etiqueta del pie, de qué entregable es. Unos 25 minutos en total.");

  pres.addSection({ title: "Contexto" });
  s = contenido(pres, "Contexto", "Misma arquitectura, otra nube", "JY",
    "La clínica guarda historias clínicas, datos sensibles según la Ley 29733. La guía de la línea propone VMware ESXi y AWS, y admite cambiar la tecnología si se mantiene la equivalencia técnica. Usamos Azure for Students: la sede es una segunda red de Azure con los mismos segmentos y firewall que tendría el local.",
    "CE03 · Infraestructura Tecnológica");
  tabla(pres, s, [
    ["Propuesto por la guía", "En este proyecto"],
    ["Red local en VMware ESXi (vSwitch, port groups, VLAN)", "vnet-clinica: subredes con su NSG y su ruta"],
    ["AWS VPC, subredes y route tables", "Azure VNet vnet-vega, subredes y rt-via-firewall"],
    ["EC2", "Máquinas virtuales Ubuntu 24.04 (serie B v2)"],
    ["Security Groups / NACL", "NSG por subred / nftables con política drop"],
    ["Internet y NAT Gateway / VPN Gateway", "IP pública y NAT del firewall / strongSwan IKEv2"],
    ["IAM / CloudWatch", "RBAC e identidades administradas / Azure Monitor"],
  ], { y: 1.55, anchos: [5.6, 6.5], tam: 14 });

  // ---------------------------------------------------------------------------------------- S1-E1
  parte(pres, "S1-E1 Diseño de red", "01", "Análisis y Diseño de Red", "Semestre 1 · Entregable 1 · CE0311", "JA",
    "Empieza la parte de red: el diseño de las dos redes, la VPN, el perímetro y la detección.");

  s = contenido(pres, "S1-E1 Diseño de red", "Objetivos y segmentación", "JA",
    "Dos redes que no se solapan: producción 10.20.0.0/16 y la sede 10.10.0.0/16. Cada segmento equivale a una VLAN y su ruta pasa por el firewall. Los objetivos: exponer solo lo necesario, aislar la base de datos, cifrar lo que cruza internet, segmentar la sede, detectar y recuperar la VPN sola.",
    "S1-E1 · Análisis y Diseño de Red");
  tabla(pres, s, [
    ["Red", "Segmento", "Prefijo", "Equipo"],
    ["Producción", "snet-dmz", "10.20.3.0/24", "vm-proxy"],
    ["Producción", "snet-app", "10.20.4.0/24", "vm-app"],
    ["Producción", "snet-mysql (delegada)", "10.20.5.0/24", "MySQL Flexible"],
    ["Sede", "snet-mgmt (VLAN 10)", "10.10.10.0/24", "Administración"],
    ["Sede", "snet-personal (VLAN 30)", "10.10.30.0/24", "vm-recepcion"],
    ["Sede", "snet-servidores (VLAN 50)", "10.10.50.0/24", "vm-wazuh"],
    ["Sede", "snet-dmz (VLAN 60)", "10.10.60.0/24", "vm-honeypot"],
  ], { y: 1.55, w: 8.0, anchos: [1.5, 2.9, 1.8, 1.8], tam: 13 });
  await tarjeta(pres, s, 8.9, 1.55, 3.8, 5.1, { titulo: "Objetivos", icono: "FaBullseye", tono: "oscuro",
    texto: "Solo 80, 443 y la VPN abiertos\nMySQL sin IP pública\nTodo cifrado con IPsec\n100 % de pruebas negativas bloqueadas\nTodo al SIEM\nVPN de vuelta en < 60 s" });

  s = contenido(pres, "S1-E1 Diseño de red", "VPN y seguridad perimetral", "JA",
    "VPN IPsec con IKEv2 y AES-256-GCM entre los dos firewalls, con NAT-T y reconexión automática. Los firewalls tienen política drop y registran cada bloqueo; internet solo llega al proxy de la DMZ y el proxy solo al puerto 80 de la app.",
    "S1-E1 · Análisis y Diseño de Red");
  cifra(pres, s, 0.6, 1.5, 3.6, "IKEv2", "strongSwan en cada firewall, NAT-T");
  cifra(pres, s, 4.7, 1.5, 3.8, "AES-256", "GCM para IKE y ESP");
  cifra(pres, s, 9.0, 1.5, 3.7, "drop", "política por defecto en los dos firewalls");
  tabla(pres, s, [
    ["Origen", "Destino", "Puertos", "Acción"],
    ["Internet", "Proxy de la DMZ (DNAT)", "80, 443", "Permitir"],
    ["Proxy", "App 10.20.4.4", "80", "Permitir"],
    ["Sede por la VPN", "Proxy · Wazuh", "443 · 1514, 22", "Permitir"],
    ["Personal o DMZ de la sede", "Servidores o producción", "Todos", "Bloquear y registrar"],
  ], { y: 3.95, h: 2.75, anchos: [3.4, 3.8, 2.2, 2.7], tam: 13 });

  s = contenido(pres, "S1-E1 Diseño de red", "Detección y matriz de componentes", "JA",
    "Seis capas de detección: Suricata en la WAN, bloqueos de nftables, WAF en el proxy, honeypot en la DMZ de la sede, Wazuh como SIEM y Azure Monitor. La matriz une cada componente con su función y el archivo del repositorio que lo configura.",
    "S1-E1 · Análisis y Diseño de Red");
  tabla(pres, s, [
    ["Componente", "Función", "Configuración"],
    ["vm-fw (x2)", "Firewall, NAT, VPN, IDS", "infra/firewall/*.nft"],
    ["vm-proxy", "Proxy inverso, WAF, TLS", "infra/nginx/proxy.conf"],
    ["vm-app", "Aplicación", "infra/nginx/vegaimplant.conf"],
    ["MySQL Flexible", "Base de datos privada", "infra/azure/prod/main.bicep"],
    ["vm-wazuh", "SIEM y respaldo", "infra/cloud-init/wazuh.yaml"],
    ["vm-honeypot", "Señuelo SSH y MySQL", "infra/cloud-init/honeypot.yaml"],
  ], { y: 1.55, w: 8.0, anchos: [2.1, 2.7, 3.2], tam: 13 });
  await tarjeta(pres, s, 8.9, 1.55, 3.8, 5.1, { titulo: "Detección", icono: "FaEye", tono: "oscuro",
    texto: "Suricata (IDS) en la WAN\nnftables: bloqueos fw-\nModSecurity + CRS\nHoneypot OpenCanary\nWazuh (SIEM)\nAzure Monitor" });

  // ---------------------------------------------------------------------------------------- S1-E2
  parte(pres, "S1-E2 Diseño de centro de datos", "02", "Diseño de Centro de Datos Híbrido", "Semestre 1 · Entregable 2 · CE0331", "FC",
    "Empieza el diseño del centro de datos: la nube en Azure, la sede y la continuidad.");

  s = contenido(pres, "S1-E2 Diseño de centro de datos", "Objetivos y arquitectura en la nube", "FC",
    "RPO de 1 hora por la copia horaria a la sede, RTO de 2 horas reconstruyendo desde el código y 5 minutos para volver a la versión anterior. En la nube: VNet con 5 subredes, tres VM de la serie B v2, MySQL privado, Key Vault con RBAC y Blob con versiones.",
    "S1-E2 · Diseño de Centro de Datos Híbrido");
  cifra(pres, s, 0.6, 1.5, 3.8, "1 h", "RPO con la copia horaria a la sede");
  cifra(pres, s, 4.75, 1.5, 3.8, "2 h", "RTO reconstruyendo desde el código");
  cifra(pres, s, 8.9, 1.5, 3.8, "5 min", "volver a la versión anterior");
  tabla(pres, s, [
    ["Recurso", "Diseño"],
    ["Red (VPC) y seguridad (SG, NACL)", "vnet-vega 10.20.0.0/16, 5 subredes, 4 NSG y nftables"],
    ["Cómputo (EC2)", "vm-fw, vm-proxy, vm-app: B2ats_v2, Ubuntu 24.04"],
    ["Datos", "MySQL Flexible privado con TLS; Key Vault; Blob con versiones"],
  ], { y: 3.95, h: 2.75, anchos: [4.0, 8.1], tam: 14 });

  s = contenido(pres, "S1-E2 Diseño de centro de datos", "La sede e integración híbrida", "FC",
    "Para el local de la clínica: rack de 12 U, firewall, conmutador con VLAN, host con RAID 1 y UPS. Cada port group de ESXi es una subred de Azure. Por la VPN viajan el panel, los registros al SIEM, la copia horaria y la administración.",
    "S1-E2 · Diseño de Centro de Datos Híbrido");
  tabla(pres, s, [
    ["Diseño en ESXi", "En Azure"],
    ["vSwitch con NIC teaming", "Red virtual vnet-clinica"],
    ["Port groups VLAN 10, 30, 50, 60", "snet-mgmt, -personal, -servidores, -dmz"],
    ["VM firewall (WAN + troncal)", "vm-fw con 2 NIC"],
    ["VM SIEM y respaldo (4 GB)", "vm-wazuh B2als_v2, 64 GB"],
    ["VM honeypot y recepción", "vm-honeypot y vm-recepcion"],
  ], { y: 1.55, w: 8.0, anchos: [3.6, 4.4], tam: 13 });
  await tarjeta(pres, s, 8.9, 1.55, 3.8, 5.1, { titulo: "Por la VPN", icono: "FaArrowRightArrowLeft", tono: "oscuro",
    texto: "Panel del personal: 443\nRegistros al SIEM: 1514\nCopia horaria: 22\nAdministración: 22\nDNS interno: 53" });

  s = contenido(pres, "S1-E2 Diseño de centro de datos", "Capacidad, continuidad y trazabilidad", "FC",
    "Uso medido el 19/11: lo que más ocupa es Wazuh, 69 % de la RAM y 66 % del disco; la cuota de 6 vCPU por región está completa. Tres copias independientes, y cada componente queda trazado con la evidencia que lo prueba en el Semestre 2.",
    "S1-E2 · Diseño de Centro de Datos Híbrido");
  s.addChart(pres.charts.BAR, [
    { name: "Uso (%)", labels: ["RAM vm-app", "RAM firewall", "RAM vm-wazuh", "Disco vm-wazuh"], values: [34, 48, 69, 66] },
  ], opcionesGrafico({ x: 0.6, y: 1.5, w: 5.6, h: 5.2, barDir: "bar", chartColors: [AZUL], showLegend: false, valAxisMaxVal: 100, valAxisMinVal: 0,
    showTitle: true, title: "Uso medido (%)", dataLabelPosition: "outEnd" }));
  tabla(pres, s, [
    ["Componente", "Continuidad o evidencia"],
    ["MySQL Flexible", "Copias de 7 días, restauración a un punto"],
    ["Blob Storage", "Copia diaria; restauración en 12 s"],
    ["Sede (vm-wazuh)", "Copia cada hora por la VPN"],
    ["VNet y NSG", "Pruebas negativas del S2-E2"],
    ["Key Vault", "10 secretos, leídos por identidad"],
  ], { x: 6.5, y: 1.55, w: 6.2, anchos: [2.4, 3.8], tam: 13 });

  // ---------------------------------------------------------------------------------------- S2-E1 SGSI
  parte(pres, "S2-E1 SGSI", "03", "Planificación e Implementación del SGSI", "Semestre 2 · Entregable 1 · CE0321 a CE0324", "JY",
    "Empieza el sistema de gestión de seguridad de la información según ISO/IEC 27001:2022.");

  s = contenido(pres, "S2-E1 SGSI", "Riesgos: de 11 altos a ninguno", "JY",
    "El alcance cubre la web, el panel, la infraestructura en Azure y la sede; la dirección es el Dr. Vega. 26 activos valorados en C-I-D y 16 riesgos con ISO/IEC 27005: antes de los controles había 11 altos o críticos y después ninguno pasa de 9, el nivel aceptado.",
    "S2-E1 · SGSI");
  s.addChart(pres.charts.BAR, [
    { name: "Inherente", labels: RIESGOS.map((r) => r[0]), values: RIESGOS.map((r) => r[1]) },
    { name: "Residual", labels: RIESGOS.map((r) => r[0]), values: RIESGOS.map((r) => r[2]) },
  ], opcionesGrafico({ x: 0.6, y: 1.45, w: 12.1, h: 5.35, barDir: "col", chartColors: ["8EA2C6", AZUL], showLegend: true, legendPos: "t", legendFontSize: 12,
    showTitle: true, title: "Riesgo inherente y residual (P × I)", valAxisMaxVal: 25, dataLabelPosition: "outEnd", dataLabelFontSize: 9, barGapWidthPct: 60 }));

  s = contenido(pres, "S2-E1 SGSI", "Declaración de Aplicabilidad y políticas", "JY",
    "Los 93 controles de ISO/IEC 27001:2022: 63 implementados, 23 parciales, 5 planificados y 2 que no aplican. Cada política tiene un control técnico que la hace cumplir.",
    "S2-E1 · SGSI");
  const temas = ["5. Organiz.", "6. Personas", "7. Físicos", "8. Tecnológ."];
  s.addChart(pres.charts.BAR, [
    { name: "Implementado", labels: temas, values: [27, 2, 9, 25] },
    { name: "Parcial", labels: temas, values: [9, 3, 3, 8] },
    { name: "Planificado", labels: temas, values: [1, 3, 1, 0] },
    { name: "No aplica", labels: temas, values: [0, 0, 1, 1] },
  ], opcionesGrafico({ x: 0.6, y: 1.45, w: 6.0, h: 5.35, barDir: "bar", barGrouping: "stacked", chartColors: [AZUL, AMARILLO, "8EA2C6", "C9D3E6"],
    showLegend: true, legendPos: "b", legendFontSize: 11, showTitle: true, title: "Controles por tema y estado", showValue: false }));
  tabla(pres, s, [
    ["Política", "Control técnico"],
    ["Control de acceso", "35 políticas por rol"],
    ["Contraseñas", "Propia al primer ingreso y MFA"],
    ["Secretos", "Key Vault y OIDC"],
    ["Copias", "Diaria y horaria; restauración probada"],
    ["Cambios", "Pull request con CI en verde"],
    ["Red", "nftables drop y NSG"],
  ], { x: 6.9, y: 1.55, w: 5.8, anchos: [2.0, 3.8], tam: 13 });

  s = contenido(pres, "S2-E1 SGSI", "Accesos, incidentes, ética y auditoría", "JY",
    "El panel exige contraseña propia y el código de la app autenticadora. Se registraron tres incidentes reales, dos abiertos hasta rotar las credenciales. Seguimos el Código de Ética de la ACM. La auditoría interna encontró seis no conformidades, una mayor, todas con responsable y plazo.",
    "S2-E1 · SGSI");
  await cuadricula(pres, s, [
    ["FaMobileScreen", "Accesos y datos", "MFA obligatorio, 35 políticas por rol, solo llaves SSH, consentimiento firmado"],
    ["FaTriangleExclamation", "Incidentes y continuidad", "3 registrados, 2 abiertos; restauración probada en 12 s"],
    ["FaScaleBalanced", "Ética (ACM)", "Sin datos reales ni credenciales; pruebas solo en lo propio; divulgación responsable"],
    ["FaClipboardCheck", "Auditoría interna", "6 no conformidades (1 mayor), métricas y acta de revisión por la dirección"],
  ]);

  // ---------------------------------------------------------------------------------------- S2-E2
  parte(pres, "S2-E2 Red implementada", "04", "Implementación, Testing y Monitoreo de Red", "Semestre 2 · Entregable 2 · CE0312 y CE0313", "JA",
    "Empieza la red implementada: la VPN funcionando, el perímetro, la detección, el monitoreo y las pruebas.");

  s = contenido(pres, "S2-E2 Red implementada", "Del diseño a la VPN funcionando", "JA",
    "Lo diseñado se implementó tal cual, con dos cambios: firewall Linux por código en lugar de OPNsense y el panel abierto a internet con MFA. El túnel está establecido en los dos extremos; cortado a propósito, volvió solo en 1,4 segundos.",
    "S2-E2 · Red implementada");
  consola(s, [
    "vega: #5, ESTABLISHED, IKEv2",
    "  local  'IP_PUBLICA_SEDE' @ 10.10.0.4[4500]",
    "  remote 'IP_PUBLICA_PROD' @ IP_PUBLICA_PROD[4500]",
    "  redes: INSTALLED, ESP:AES_GCM_16-256",
    "    10.10.0.0/16 <-> 10.20.0.0/16",
  ], { x: 0.6, y: 1.6, w: 7.6, h: 3.0 });
  cifra(pres, s, 8.7, 1.6, 4.0, "1,4 s", "la VPN volvió sola tras un corte");
  cifra(pres, s, 8.7, 3.5, 4.0, "200", "el panel por la VPN desde la recepción");
  s.addText("Cambios respecto al diseño: OPNsense → firewall Linux (nftables, strongSwan, Suricata) configurado por código; panel solo desde la sede → panel desde internet con MFA.",
    { x: 0.6, y: 5.1, w: 12.1, h: 1.2, fontSize: 15, color: C.text1, margin: 0, isTextBox: true, valign: "top" });

  s = contenido(pres, "S2-E2 Red implementada", "Perímetro y detección", "JA",
    "Cada conexión bloqueada queda registrada con su prefijo; arriba, la recepción intentando llegar directo a la app y la app intentando SSH a la recepción. Suricata vio escaneos reales de internet: 116 a archivos .env y 13 al MySQL de la sede. El honeypot está listo en código; su prueba es la única pendiente.",
    "S2-E2 · Red implementada");
  consola(s, [
    "fw-reenvio-bloqueo  SRC=10.10.30.10  DST=10.20.4.4  DPT=80  SYN",
    "fw-reenvio-bloqueo  SRC=10.20.4.4  DST=10.10.30.10  DPT=22  SYN",
  ], { x: 0.6, y: 1.6, w: 12.1, h: 1.4, fontSize: 15 });
  cifra(pres, s, 0.6, 3.5, 2.9, "14", "bloqueos registrados en un día");
  cifra(pres, s, 3.7, 3.5, 2.9, "80", "respuestas 429 en 250 peticiones al panel");
  cifra(pres, s, 6.8, 3.5, 2.9, "116", "escaneos a archivos .env");
  cifra(pres, s, 9.9, 3.5, 2.9, "13", "escaneos al MySQL de la sede");

  s = contenido(pres, "S2-E2 Red implementada", "Monitoreo y pruebas", "JA",
    "Wazuh recibe los registros de las seis máquinas, firewalls incluidos: 3 442 alertas en un día. De 24 pruebas con resultado esperado y obtenido, 23 cumplen; queda la del honeypot.",
    "S2-E2 · Red implementada");
  s.addChart(pres.charts.BAR, [
    { name: "Alertas", labels: ["vm-proxy", "vm-app", "vm-fw-prod", "vm-fw-sede", "vm-wazuh", "vm-recepcion"], values: [1194, 827, 469, 354, 302, 296] },
  ], opcionesGrafico({ x: 0.6, y: 1.45, w: 6.4, h: 5.3, barDir: "bar", chartColors: [AZUL], showLegend: false, showTitle: true, title: "Alertas de Wazuh por agente el 19/11", dataLabelPosition: "outEnd" }));
  tabla(pres, s, [
    ["Prueba", "Obtenido"],
    ["Internet → prod. 22, 3306", "Filtrados"],
    ["Recepción → Wazuh :22", "Bloqueado"],
    ["Proxy → app :22", "Bloqueado"],
    ["App → MySQL", "TLS 1.3"],
    ["Corte de la VPN", "1,4 s"],
    ["Total", "23 de 24 cumplen"],
  ], { x: 7.3, y: 1.55, w: 5.4, anchos: [3.2, 2.2], tam: 13 });

  // ---------------------------------------------------------------------------------------- S2-E3
  parte(pres, "S2-E3 Centro de datos", "05", "Implementación, Gestión y Control de Centro de Datos", "Semestre 2 · Entregable 3 · CE0332 y CE0333", "FC",
    "Empieza el centro de datos implementado: inventario, identidades, continuidad probada y control.");

  s = contenido(pres, "S2-E3 Centro de datos", "Implementado e inventariado", "FC",
    "Todo lo del S1-E2 está en funcionamiento: 27 recursos en producción y 16 en la sede, creados con Bicep y cloud-init y listados el 19/11. La sede reproduce el diseño ESXi en subredes y VM de Azure.",
    "S2-E3 · Centro de datos implementado");
  tabla(pres, s, [
    ["VM", "Red", "Tamaño", "IP", "Uso de RAM"],
    ["vm-fw", "Producción", "B2ats_v2", "10.20.0.4 / 10.20.1.4", "48 %"],
    ["vm-proxy", "Producción (DMZ)", "B2ats_v2", "10.20.3.4", "37 %"],
    ["vm-app", "Producción", "B2ats_v2", "10.20.4.4", "34 %"],
    ["vm-fw", "Sede", "B2ats_v2", "10.10.0.4 / 10.10.1.4", "37 %"],
    ["vm-wazuh", "Sede (servidores)", "B2als_v2", "10.10.50.10", "69 %"],
    ["vm-recepcion", "Sede (personal)", "B2ats_v2", "10.10.30.10", "—"],
  ], { y: 1.55, h: 3.6, anchos: [2.0, 2.9, 1.8, 3.4, 2.0], tam: 13 });
  cifra(pres, s, 0.6, 5.0, 3.8, "43", "recursos de Azure en total");
  cifra(pres, s, 4.75, 5.0, 3.8, "TLS 1.3", "MySQL privado, sin acceso público");
  cifra(pres, s, 8.9, 5.0, 3.8, "10", "secretos en Key Vault");

  s = contenido(pres, "S2-E3 Centro de datos", "Identidades y accesos", "FC",
    "Ninguna clave de la nube en archivos. La VM usa su identidad administrada, GitHub publica con OIDC solo desde main, los servidores solo aceptan llaves y el panel exige MFA.",
    "S2-E3 · Centro de datos implementado");
  await cuadricula(pres, s, [
    ["FaMobileScreen", "Panel", "Contraseña propia y MFA obligatorio; 8 pruebas automáticas"],
    ["FaServer", "vm-app", "Identidad administrada: Key Vault Secrets User y Blob Data Contributor"],
    ["FaGithub", "GitHub Actions", "OIDC solo desde main; Virtual Machine Contributor"],
    ["FaKey", "Administración", "Solo llaves SSH, jump host o run-command; rotación desde Key Vault"],
  ]);

  s = contenido(pres, "S2-E3 Centro de datos", "Continuidad probada, monitoreo y control", "FC",
    "Simulacros del 19/11: la copia de Blob se restauró en 12 segundos en una base aparte con los mismos totales que producción; la VPN volvió en 1,4 segundos; publicar tomó 65. Falta medir perder vm-app entera. Azure Monitor y Wazuh vigilan, y todo cambio entra por pull request con la CI.",
    "S2-E3 · Centro de datos implementado");
  cifra(pres, s, 0.6, 1.5, 2.9, "12 s", "restaurar la base desde Blob");
  cifra(pres, s, 3.7, 1.5, 2.9, "1,4 s", "la VPN volvió sola");
  cifra(pres, s, 6.8, 1.5, 2.9, "65 s", "publicar una versión");
  cifra(pres, s, 9.9, 1.5, 2.9, "1 h", "RPO con la copia horaria");
  await tarjeta(pres, s, 0.6, 4.0, 5.95, 2.6, { titulo: "Monitoreo", icono: "FaBell",
    texto: "Azure Monitor: VM caída y CPU > 80 %. Wazuh: 6 agentes. Presupuesto con avisos al 50 y 80 %." });
  await tarjeta(pres, s, 6.75, 4.0, 5.95, 2.6, { titulo: "Control de cambios", icono: "FaCodeBranch",
    texto: "Pull request, 4 comprobaciones de la CI, merge y publicación registrada; acción volver en segundos." });

  // ---------------------------------------------------------------------------------------- Evidencias y cierre
  pres.addSection({ title: "Evidencias" });
  s = contenido(pres, "Evidencias", "Calidad documental, evidencias y repositorio", "JY",
    "Cada entregable tiene su informe en Word y PDF con referencias IEEE, los anexos en Excel, las salidas reales de los comandos y el código en GitHub, sin credenciales ni datos personales reales.",
    "CE03 · Evidencias");
  await cuadricula(pres, s, [
    ["FaFileWord", "5 informes", "Word y PDF con la plantilla UPeU, índice y referencias IEEE"],
    ["FaFileExcel", "4 anexos en Excel", "Activos, riesgos, SoA y plan y resultados de pruebas"],
    ["FaTerminal", "Evidencias reales", "Salidas de cada VM del 19/11 y scripts de los simulacros"],
    ["FaGithub", "Repositorio", "infra/ con Bicep, reglas y scripts; control de versiones por PR"],
  ]);

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
  // SALIDA=otra/ruta.pptx guarda en otro lugar (por ejemplo, si el archivo está abierto en PowerPoint).
  const ruta = process.env.SALIDA || path.join(AQUI, archivo);
  await pres.writeFile({ fileName: ruta });
  if (applyTheme) await applyTheme(ruta, THEME);
  console.log(archivo, pres.numero, "diapositivas");
}

main().catch((e) => { console.error(e); process.exit(1); });
