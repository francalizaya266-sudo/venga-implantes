// Arma la presentación única de CE03, ordenada por la rúbrica de cada entregable y repartida entre los tres
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
    title: "ENTREGABLE",
    background: { color: AZUL },
    objects: [
      { placeholder: { options: { name: "title", type: "title", x: 0.6, y: 0.35, w: 9.6, h: 0.85, fontSize: 30, bold: true, color: C.background1, valign: "middle", align: "left", margin: 0 }, text: "" } },
      { placeholder: { options: { name: "body", type: "body", x: 0.6, y: 1.2, w: 9.6, h: 0.45, fontSize: 16, color: C.accent2, margin: 0 }, text: "" } },
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

function contenido(pres, seccion, titulo, iniciales, notas, criterio) {
  const C = pres.SchemeColor;
  const slide = pres.addSlide({ masterName: "CONTENIDO", sectionTitle: seccion });
  slide.addText(titulo, { placeholder: "title" });
  if (iniciales) expositor(pres, slide, iniciales);
  if (notas) slide.addNotes(notas);
  // El criterio de la rúbrica que cubre esta diapositiva, al pie, para que el jurado lo ubique.
  if (criterio) {
    slide.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: 6.3, y: 6.93, w: 5.75, h: 0.38, rectRadius: 0.19, fill: { color: C.accent2 }, line: { color: C.accent2 }, objectName: "Criterio de la rúbrica" });
    slide.addText("Rúbrica  ·  " + criterio, { x: 6.3, y: 6.93, w: 5.75, h: 0.38, fontSize: 12, bold: true, color: C.text2, align: "center", valign: "middle", margin: 0, isTextBox: true });
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


// Portada de cada entregable: sus criterios de la rúbrica, el peso y en qué diapositivas se cubre cada uno.
// criterios: [nombre, peso, cantidad de diapositivas (número) o texto]
function entregable(pres, seccion, titulo, subtitulo, iniciales, criterios, notas) {
  const C = pres.SchemeColor;
  pres.addSection({ title: seccion });
  const slide = pres.addSlide({ masterName: "ENTREGABLE", sectionTitle: seccion });
  slide.addText(titulo, { placeholder: "title" });
  slide.addText(subtitulo, { placeholder: "body" });
  const p = PERSONAS[iniciales];
  slide.addShape(pres.shapes.OVAL, { x: 12.15, y: 0.45, w: 0.62, h: 0.62, fill: { color: C.accent2 }, line: { color: C.accent2 } });
  slide.addText(iniciales, { x: 12.15, y: 0.45, w: 0.62, h: 0.62, fontSize: 15, bold: true, color: C.text2, align: "center", valign: "middle", margin: 0, isTextBox: true });
  slide.addText([{ text: p.nombre, options: { bold: true, breakLine: true } }, { text: p.rol }],
    { x: 9.4, y: 0.42, w: 2.6, h: 0.68, fontSize: 11, color: C.background2, align: "right", valign: "middle", margin: 0, isTextBox: true });
  let siguiente = pres.numero + 1;
  const filas = [["Criterio de la rúbrica", "Peso", "Dónde"]];
  for (const [nombre, peso, n] of criterios) {
    let donde = n;
    if (typeof n === "number") {
      donde = n === 1 ? `Diapositiva ${siguiente}` : `Diapositivas ${siguiente} a ${siguiente + n - 1}`;
      siguiente += n;
    }
    filas.push([nombre, peso, donde]);
  }
  const alto = Math.min(0.5, 4.9 / filas.length);
  slide.addTable(filas.map((fila, i) => fila.map((t, j) => ({ text: t, options: i === 0
    ? { bold: true, color: C.text2, fill: { color: C.accent2 }, fontSize: 15 }
    : { color: C.text1, fill: { color: i % 2 ? C.background1 : C.background2 }, fontSize: 15, bold: j === 1, align: j === 1 ? "center" : "left" } }))),
  { x: 0.6, y: 1.95, w: 12.1, colW: [6.6, 1.5, 4.0], rowH: alto, border: { type: "solid", pt: 0.5, color: "8EA2C6" }, margin: [3, 8, 3, 8], valign: "middle" });
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
// Presentación única de CE03, ordenada por la rúbrica de cada entregable.
// ======================================================================================================
async function deckRubrica() {
  const pres = nuevaPresentacion("Infraestructura Tecnológica · CE03");
  const C = pres.SchemeColor;
  const crit = (codigo, nombre, peso) => `${codigo} · ${nombre} (${peso})`;
  let s;

  pres.addSection({ title: "Portada" });
  portada(pres, "Infraestructura Tecnológica del sistema de Clínica Vega Implant", "CE03 · Los cinco entregables, criterio por criterio de la rúbrica",
    "Presentamos los cinco entregables de la línea de Infraestructura siguiendo su rúbrica. Cada entregable empieza con una diapositiva que dice qué criterio se cubre en qué diapositiva, y al pie de cada diapositiva está el criterio y su peso.");
  await agenda(pres, [
    // Alrededor de un minuto por diapositiva: Joselyn 15, Jhoel 18 y Fran 19.
    ["JY", ["Contexto y equivalencia", "S2-E1 · SGSI", "Evidencias y cierre"], 15],
    ["JA", ["S1-E1 · Diseño de red", "S2-E2 · Red implementada"], 18],
    ["FC", ["S1-E2 · Diseño de centro de datos", "S2-E3 · Centro de datos implementado"], 19],
  ], "Jhoel expone lo de red, Fran lo de centro de datos y Joselyn el SGSI, el contexto y el cierre. El círculo amarillo dice quién habla; la etiqueta amarilla al pie, qué criterio de la rúbrica se cubre.");

  // ---------------------------------------------------------------------------------------- Contexto
  pres.addSection({ title: "Contexto" });
  s = contenido(pres, "Contexto", "Una infraestructura para datos de salud", "JY",
    "La clínica guarda historias clínicas y radiografías, datos sensibles según la Ley 29733. La línea pide cinco entregables: dos de diseño en el Semestre 1 y tres de implementación en el Semestre 2, todos desplegados y medidos en Azure.");
  tabla(pres, s, [
    ["Entregable", "Evidencias", "Resultado principal"],
    ["S1-E1 Análisis y Diseño de Red", "CE0311", "Red segmentada con VPN, 12 pruebas planeadas"],
    ["S1-E2 Diseño de Centro de Datos Híbrido", "CE0331", "Nube y sede, RPO 1 h y RTO 2 h"],
    ["S2-E1 Planificación e Implementación del SGSI", "CE0321 a CE0324", "26 activos, 16 riesgos, 93 controles"],
    ["S2-E2 Implementación, Testing y Monitoreo de Red", "CE0312 y CE0313", "23 de 24 pruebas cumplen"],
    ["S2-E3 Implementación, Gestión y Control de Centro de Datos", "CE0332 y CE0333", "Restauración probada en 12 s"],
  ], { y: 1.55, anchos: [5.6, 2.3, 4.2], tam: 13 });

  s = contenido(pres, "Contexto", "Misma arquitectura, otra nube", "JY",
    "La rúbrica habla de VMware ESXi y AWS, y la guía admite cambiar la tecnología si se mantiene la equivalencia técnica. Usamos Azure for Students: la sede es una segunda red de Azure con los mismos segmentos y firewall que tendría el local. Cuando la rúbrica dice AWS o ESXi, esta es su equivalencia.");
  tabla(pres, s, [
    ["En la rúbrica", "En este proyecto"],
    ["Red local en VMware ESXi (vSwitch, port groups, VLAN)", "vnet-clinica: subredes con su NSG y su ruta"],
    ["AWS VPC, subredes y route tables", "Azure VNet vnet-vega, subredes y rt-via-firewall"],
    ["EC2", "Máquinas virtuales Ubuntu 24.04 (serie B v2)"],
    ["Security Groups / NACL", "NSG por subred / nftables con política drop"],
    ["Internet y NAT Gateway / VPN Gateway", "IP pública y NAT del firewall / strongSwan IKEv2"],
    ["IAM / CloudWatch", "RBAC e identidades administradas / Azure Monitor"],
  ], { y: 1.55, anchos: [5.6, 6.5], tam: 14 });

  // ---------------------------------------------------------------------------------------- S1-E1
  const R1 = "CE0311";
  entregable(pres, "S1-E1 Diseño de red", "S1-E1 · Análisis y Diseño de Red", "Semestre 1 · CE0311", "JA", [
    ["Contexto y alcance", "10 %", 1],
    ["Arquitectura lógica (VLAN, subredes, direccionamiento)", "20 %", 2],
    ["Conectividad híbrida (VPN)", "15 %", 1],
    ["Seguridad perimetral (firewall, DMZ, segmentación)", "20 %", 1],
    ["Detección (IDS/IPS y honeypot, planificación)", "10 %", 1],
    ["Flujos y matriz de componentes", "10 %", 2],
    ["Calidad documental y trazabilidad", "8 %", "Diapositiva de evidencias"],
    ["Sustentación", "7 %", "Esta exposición"],
  ], "Rúbrica del diseño de red y dónde se cubre cada criterio.");

  s = contenido(pres, "S1-E1 Diseño de red", "Necesidades, alcance y objetivos", "JA",
    "La red sostiene cuatro procesos críticos: reservas, historia clínica, agenda y caja, y copias. El alcance incluye producción, sede, VPN, perímetro y detección; queda fuera el cableado físico y el Wi-Fi de pacientes. Los seis objetivos tienen meta medible.",
    crit(R1, "Contexto y alcance", "10 %"));
  tabla(pres, s, [
    ["Objetivo", "Meta"],
    ["O1 Exponer solo lo necesario", "Solo 80, 443 y la VPN; SSH solo desde la IP de administración"],
    ["O2 Aislar la base de datos", "Solo snet-app llega a MySQL; sin IP pública"],
    ["O3 Cifrar lo que cruza internet", "Todo por IPsec con AES-256-GCM"],
    ["O4 Segmentar la sede", "100 % de las pruebas negativas bloqueadas"],
    ["O5 Detectar", "Todas las VM y los firewalls envían registros al SIEM"],
    ["O6 Recuperar la VPN sola", "Menos de 60 s tras un corte"],
  ], { y: 1.55, anchos: [4.4, 7.7], tam: 14 });

  s = contenido(pres, "S1-E1 Diseño de red", "Topología: dos redes unidas por una VPN", "JA",
    "Producción en Chile Central y la sede en North Central US. Desde internet solo se llega al firewall de producción; la sede se une por la VPN.",
    crit(R1, "Arquitectura lógica", "20 %"));
  await imagen(s, "topologia-general.png", { x: 0.6, y: 1.4, w: 12.1, h: 5.4 }, "Topología general");

  s = contenido(pres, "S1-E1 Diseño de red", "Segmentos, direccionamiento y ruteo", "JA",
    "Cada segmento equivale a una VLAN y es una subred /24. Los /16 no se solapan, condición para la VPN. Cada subred interna tiene una ruta cuyo siguiente salto es la LAN del firewall: así todo tráfico entre segmentos pasa por nftables.",
    crit(R1, "Arquitectura lógica", "20 %"));
  tabla(pres, s, [
    ["Red", "Segmento", "Prefijo", "Equipo"],
    ["Producción", "snet-dmz", "10.20.3.0/24", "vm-proxy 10.20.3.4"],
    ["Producción", "snet-app", "10.20.4.0/24", "vm-app 10.20.4.4"],
    ["Producción", "snet-mysql (delegada)", "10.20.5.0/24", "MySQL Flexible"],
    ["Sede", "snet-mgmt (VLAN 10)", "10.10.10.0/24", "Administración"],
    ["Sede", "snet-personal (VLAN 30)", "10.10.30.0/24", "vm-recepcion"],
    ["Sede", "snet-servidores (VLAN 50)", "10.10.50.0/24", "vm-wazuh"],
    ["Sede", "snet-dmz (VLAN 60)", "10.10.60.0/24", "vm-honeypot"],
  ], { y: 1.55, w: 8.2, anchos: [1.5, 2.9, 1.8, 2.0], tam: 13 });
  await tarjeta(pres, s, 9.1, 1.55, 3.6, 4.6, { titulo: "Ruteo", icono: "FaRoute", tono: "oscuro",
    texto: "0.0.0.0/0, la propia red y la otra red van a la LAN del firewall.\n\nSolo MySQL responde directo dentro de la VNet." });

  s = contenido(pres, "S1-E1 Diseño de red", "VPN IPsec sitio a sitio", "JA",
    "strongSwan en cada firewall, IKEv2 con AES-256-GCM y NAT-T, porque Azure hace NAT a la IP pública igual que un proveedor de internet. Con close_action start y dpd_action restart, el túnel se levanta solo.",
    crit(R1, "Conectividad híbrida (VPN)", "15 %"));
  cifra(pres, s, 0.6, 1.6, 3.6, "IKEv2", "Clave compartida guardada en Key Vault");
  cifra(pres, s, 4.7, 1.6, 3.8, "AES-256", "GCM para IKE y ESP, DH MODP-2048");
  cifra(pres, s, 9.0, 1.6, 3.7, "< 60 s", "Meta de reconexión automática");
  tabla(pres, s, [
    ["Parámetro", "Valor"],
    ["Redes protegidas", "10.20.0.0/16 ↔ 10.10.0.0/16"],
    ["Encapsulado", "NAT-T, UDP 4500"],
    ["Recuperación", "dpd_delay 30 s · dpd_action restart · close_action start"],
  ], { y: 4.25, h: 2.4, anchos: [3.4, 8.7], tam: 14 });

  s = contenido(pres, "S1-E1 Diseño de red", "Firewall, DMZ y segmentación", "JA",
    "Política drop en los dos firewalls y cada bloqueo registrado con un prefijo. Internet solo llega al proxy de la DMZ; el proxy, solo al puerto 80 de la app; la recepción, solo al panel. Los NSG repiten lo esencial como segunda capa.",
    crit(R1, "Seguridad perimetral", "20 %"));
  tabla(pres, s, [
    ["Origen", "Destino", "Puertos", "Acción"],
    ["Internet", "Proxy (DNAT)", "80, 443", "Permitir"],
    ["Proxy", "App 10.20.4.4", "80", "Permitir"],
    ["Sede por la VPN", "Proxy", "80, 443", "Permitir"],
    ["App y proxy", "Wazuh (sede)", "1514, 1515", "Permitir"],
    ["DMZ de la sede", "Sede o producción", "Todos", "Bloquear y registrar"],
    ["Personal", "Administración o servidores", "Todos", "Bloquear y registrar"],
    ["Cualquier otro", "Cualquier otro", "Todos", "Bloquear y registrar"],
  ], { y: 1.55, anchos: [2.9, 3.9, 2.0, 3.3], tam: 13 });

  s = contenido(pres, "S1-E1 Diseño de red", "Diseño de la detección", "JA",
    "Seis capas: Suricata en la WAN detecta escaneos y exploits, nftables registra bloqueos, ModSecurity filtra la web, el honeypot avisa de cualquier contacto, Wazuh correlaciona y Azure Monitor vigila desde fuera de las VM.",
    crit(R1, "Detección (IDS/IPS y honeypot)", "10 %"));
  await cuadricula(pres, s, [
    ["FaEye", "Suricata", "IDS con reglas ET Open en la WAN de los dos firewalls"],
    ["FaFire", "nftables", "Cada bloqueo registrado con prefijo fw-"],
    ["FaGlobe", "WAF", "ModSecurity + OWASP CRS en el proxy"],
    ["FaBug", "Honeypot", "OpenCanary en la DMZ de la sede"],
    ["FaChartLine", "Wazuh", "SIEM que recibe y correlaciona todo"],
    ["FaBell", "Azure Monitor", "VM caída y CPU alta, por correo"],
  ], 3);

  s = contenido(pres, "S1-E1 Diseño de red", "Flujo de información", "JA",
    "Recorrido de una petición: DNAT en el firewall, TLS y WAF en el proxy, la app consulta MySQL por TLS y lee secretos con su identidad. Los registros y la copia horaria viajan a la sede por la VPN.",
    crit(R1, "Flujos y matriz de componentes", "10 %"));
  await imagen(s, "flujo-informacion.png", { x: 0.6, y: 1.35, w: 12.1, h: 5.45 }, "Flujo de una petición web");

  s = contenido(pres, "S1-E1 Diseño de red", "Matriz de componentes de red", "JA",
    "Cada componente con su función y el archivo del repositorio que lo configura: el diseño es código.",
    crit(R1, "Flujos y matriz de componentes", "10 %"));
  tabla(pres, s, [
    ["Componente", "Función", "Configuración en el repositorio"],
    ["vm-fw producción", "Firewall, NAT, VPN, IDS", "infra/firewall/produccion.nft"],
    ["vm-fw sede", "Firewall, VPN, IDS, DNS, jump host", "infra/firewall/sede.nft"],
    ["vm-proxy", "Proxy inverso, WAF, TLS", "infra/nginx/proxy.conf"],
    ["vm-app", "Servidor de la aplicación", "infra/nginx/vegaimplant.conf"],
    ["MySQL Flexible", "Base de datos privada", "infra/azure/prod/main.bicep"],
    ["vm-wazuh", "SIEM y respaldo", "infra/cloud-init/wazuh.yaml"],
    ["7 NSG y 2 tablas de rutas", "Filtro por subred y paso por el firewall", "infra/azure/*/main.bicep"],
  ], { y: 1.55, anchos: [3.3, 4.2, 4.6], tam: 13 });

  // ---------------------------------------------------------------------------------------- S1-E2
  const R2 = "CE0331";
  entregable(pres, "S1-E2 Diseño de centro de datos", "S1-E2 · Diseño de Centro de Datos Híbrido", "Semestre 1 · CE0331", "FC", [
    ["Contexto y alcance", "8 %", 1],
    ["Arquitectura on-premise (ESXi)", "22 %", 1],
    ["Arquitectura AWS (en Azure)", "22 %", 2],
    ["Integración híbrida", "10 %", 1],
    ["Dimensionamiento y capacidad", "12 %", 1],
    ["Matriz de componentes y trazabilidad", "10 %", 1],
    ["Disponibilidad y continuidad", "8 %", 1],
    ["Calidad documental y sustentación", "8 %", "Evidencias y esta exposición"],
  ], "Rúbrica del diseño de centro de datos.");

  s = contenido(pres, "S1-E2 Diseño de centro de datos", "Objetivos medibles, RTO y RPO", "FC",
    "El RPO de 1 hora sale de la copia horaria a la sede; el RTO de 2 horas, de reconstruir todo desde el código. Volver a la versión anterior de la app debe tomar menos de 5 minutos.",
    crit(R2, "Contexto y alcance", "8 %"));
  cifra(pres, s, 0.6, 1.7, 3.8, "1 h", "RPO: lo máximo que se perdería");
  cifra(pres, s, 4.75, 1.7, 3.8, "2 h", "RTO: reconstruir la aplicación desde el código");
  cifra(pres, s, 8.9, 1.7, 3.8, "5 min", "Volver a la versión anterior");
  tabla(pres, s, [
    ["Otros objetivos", "Meta"],
    ["Recursos creados por código", "100 % (Bicep y cloud-init)"],
    ["Secretos en archivos del repositorio", "0: todos en Key Vault"],
    ["Gasto mensual", "Menos de 40 USD, con avisos al 50 % y 80 %"],
  ], { y: 4.3, h: 2.4, anchos: [5.0, 7.1], tam: 14 });

  s = contenido(pres, "S1-E2 Diseño de centro de datos", "La sede: diseño local y su equivalente", "FC",
    "Para el local de la clínica: rack de 12 U, firewall con dos puertos, conmutador con VLAN, host de virtualización con RAID 1 y UPS. Cada port group de ESXi es una subred de Azure en la implementación.",
    crit(R2, "Arquitectura on-premise (ESXi)", "22 %"));
  tabla(pres, s, [
    ["Diseño en ESXi", "En Azure"],
    ["vSwitch con NIC teaming", "Red virtual vnet-clinica"],
    ["Port group VLAN 10, 30, 50, 60", "snet-mgmt, snet-personal, snet-servidores, snet-dmz"],
    ["VM firewall (WAN + troncal)", "vm-fw con 2 NIC"],
    ["VM SIEM y respaldo (4 GB)", "vm-wazuh B2als_v2, 64 GB"],
    ["VM honeypot y recepción", "vm-honeypot y vm-recepcion"],
  ], { y: 1.55, w: 7.6, anchos: [3.4, 4.2], tam: 13 });
  await tarjeta(pres, s, 8.5, 1.55, 4.2, 4.6, { titulo: "Equipo físico", icono: "FaServer", tono: "oscuro",
    texto: "Rack de pared 12 U\nConmutador 802.1Q de 24 puertos\nHost de 8 núcleos y 32 GB, RAID 1\nUPS de 1 500 VA\nFibra con IP pública" });

  s = contenido(pres, "S1-E2 Diseño de centro de datos", "Arquitectura en la nube", "FC",
    "Dos grupos de recursos en dos regiones. GitHub entra por OIDC a una identidad administrada; la VM de la aplicación lee secretos y escribe copias con su propia identidad.",
    crit(R2, "Arquitectura AWS (en Azure)", "22 %"));
  await imagen(s, "centro-de-datos.png", { x: 0.6, y: 1.35, w: 12.1, h: 5.45 }, "Recursos de Azure");

  s = contenido(pres, "S1-E2 Diseño de centro de datos", "Red, cómputo, datos y seguridad", "FC",
    "VPC, EC2, Security Groups y NACL en su versión de Azure. Serie B v2 porque la carga de una clínica es baja y con picos cortos. MySQL administrado y privado, Key Vault con RBAC y Blob con versiones.",
    crit(R2, "Arquitectura AWS (en Azure)", "22 %"));
  tabla(pres, s, [
    ["Recurso", "Diseño"],
    ["Red (VPC)", "vnet-vega 10.20.0.0/16, 5 subredes, rt-via-firewall"],
    ["Cómputo (EC2)", "vm-fw, vm-proxy, vm-app: B2ats_v2, Ubuntu 24.04"],
    ["Seguridad (SG y NACL)", "4 NSG y nftables con política drop"],
    ["Base de datos", "MySQL Flexible 8.0, B1ms, solo acceso privado, TLS, copias 7 días"],
    ["Secretos y archivos", "Key Vault con RBAC; Blob con versiones y borrado suave 14 días"],
    ["Monitoreo (CloudWatch)", "Azure Monitor: VM caída y CPU > 80 %"],
  ], { y: 1.55, anchos: [3.4, 8.7], tam: 14 });

  s = contenido(pres, "S1-E2 Diseño de centro de datos", "Integración híbrida", "FC",
    "Lo que viaja entre la nube y la sede, siempre por la VPN. El DNS de la sede responde el dominio con la IP privada del proxy.",
    crit(R2, "Integración híbrida", "10 %"));
  tabla(pres, s, [
    ["Flujo", "De → a", "Puerto"],
    ["Panel del personal", "Recepción → proxy", "443"],
    ["Registros al SIEM", "VM de producción → Wazuh", "1514, 1515"],
    ["Copia horaria", "vm-app → Wazuh (usuario respaldo)", "22"],
    ["Administración", "Jump host de la sede → proxy y app", "22"],
    ["DNS interno", "Recepción → Unbound", "53"],
  ], { y: 1.55, anchos: [3.4, 6.2, 2.5], tam: 14 });

  s = contenido(pres, "S1-E2 Diseño de centro de datos", "Dimensionamiento y capacidad", "FC",
    "Medido el 19/11 con datos de prueba. Lo que más ocupa es Wazuh: 69 % de la RAM y 66 % del disco. La cuota de 6 vCPU por región está completa en las dos regiones.",
    crit(R2, "Dimensionamiento y capacidad", "12 %"));
  s.addChart(pres.charts.BAR, [
    { name: "Uso (%)", labels: ["RAM vm-app", "RAM firewall prod.", "RAM vm-wazuh", "Disco vm-wazuh", "Disco vm-app"], values: [34, 48, 69, 66, 17] },
  ], opcionesGrafico({ x: 0.6, y: 1.5, w: 7.8, h: 5.2, barDir: "bar", chartColors: [AZUL], showLegend: false, valAxisMaxVal: 100, valAxisMinVal: 0,
    showTitle: true, title: "Uso medido (%)", dataLabelPosition: "outEnd" }));
  await tarjeta(pres, s, 8.8, 1.55, 3.9, 4.9, { titulo: "Cuota y costo", icono: "FaCoins",
    texto: "6 de 6 vCPU en cada región.\n\n1,5 a 2 USD por día con todo encendido.\n\nBase de datos: 6,6 MB de 32 GB." });

  s = contenido(pres, "S1-E2 Diseño de centro de datos", "Matriz de componentes y trazabilidad", "FC",
    "Cada componente se une con el objetivo que cumple y la evidencia que lo prueba en el Semestre 2.",
    crit(R2, "Matriz de componentes y trazabilidad", "10 %"));
  tabla(pres, s, [
    ["Componente", "Objetivo", "Evidencia en el Semestre 2"],
    ["VNet y subredes", "Segmentación", "Pruebas negativas (S2-E2)"],
    ["MySQL Flexible", "Datos privados con TLS", "TLS 1.3 desde vm-app (S2-E3)"],
    ["Key Vault", "0 secretos en archivos", "10 secretos, leídos por identidad"],
    ["Blob Storage", "RPO", "Restauración en 12 s (S2-E3)"],
    ["Copia horaria a la sede", "RPO 1 h", "Copias de cada hora en vm-wazuh"],
    ["Identidades y OIDC", "Sin claves guardadas", "Publicación desde GitHub en verde"],
    ["Azure Monitor y Wazuh", "Visibilidad", "2 alertas y 6 agentes activos"],
  ], { y: 1.55, anchos: [3.5, 3.6, 5.0], tam: 13 });

  s = contenido(pres, "S1-E2 Diseño de centro de datos", "Disponibilidad y continuidad", "FC",
    "Tres copias independientes: MySQL con restauración a un punto, Blob diario y la sede cada hora. Una sola VM por servicio es una limitación aceptada por presupuesto.",
    crit(R2, "Disponibilidad y continuidad", "8 %"));
  await cuadricula(pres, s, [
    ["FaDatabase", "MySQL Flexible", "Copias de 7 días con restauración a un punto en el tiempo"],
    ["FaCloud", "Blob Storage", "Copia diaria con versiones y borrado suave de 14 días"],
    ["FaHouseMedical", "Sede de la clínica", "Copia cada hora por la VPN; se guardan las últimas 48"],
  ], 3);
  tabla(pres, s, [
    ["Amenaza", "RPO", "RTO"],
    ["Versión defectuosa de la app", "0", "< 5 min"],
    ["Pérdida de vm-app", "0", "< 2 h"],
    ["Pérdida de la base o de la región", "1 h", "< 2 h"],
  ], { y: 4.4, h: 2.3, anchos: [6.5, 2.6, 3.0], tam: 13 });

  // ---------------------------------------------------------------------------------------- S2-E1 SGSI
  const R3 = "CE0321-CE0324";
  entregable(pres, "S2-E1 SGSI", "S2-E1 · Planificación e Implementación del SGSI", "Semestre 2 · CE0321 a CE0324 · ISO/IEC 27001:2022", "JY", [
    ["Contexto, alcance y liderazgo", "10 %", 2],
    ["Inventario y clasificación de información", "10 %", 1],
    ["Evaluación y tratamiento de riesgos", "18 %", 2],
    ["SoA y selección de controles", "12 %", 1],
    ["Políticas y procedimientos", "10 %", 1],
    ["Identidades, accesos y protección de datos", "8 %", 1],
    ["Incidentes, continuidad y recuperación", "8 %", 1],
    ["Ética de la seguridad", "8 %", 1],
    ["Monitoreo, medición y auditoría interna", "9 %", 1],
    ["Calidad documental y sustentación", "7 %", "Evidencias y esta exposición"],
  ], "Rúbrica del SGSI.");

  s = contenido(pres, "S2-E1 SGSI", "Contexto y alcance del SGSI", "JY",
    "El alcance cubre la web, el panel, la API, la infraestructura en Azure y la sede. Quedan fuera los equipos de rayos X, el Wi-Fi de pacientes y la seguridad física de Azure, que se apoya en sus certificaciones. El SGSI sigue el ciclo PHVA.",
    crit(R3, "Contexto, alcance y liderazgo", "10 %"));
  cifra(pres, s, 0.6, 1.6, 3.8, "26", "activos inventariados con valor C-I-D");
  cifra(pres, s, 4.75, 1.6, 3.8, "16", "riesgos evaluados con ISO/IEC 27005");
  cifra(pres, s, 8.9, 1.6, 3.8, "93", "controles declarados en la SoA");
  await imagen(s, "phva.png", { x: 0.6, y: 4.0, w: 12.1, h: 2.7 }, "Ciclo PHVA");

  s = contenido(pres, "S2-E1 SGSI", "Liderazgo: política y roles", "JY",
    "La dirección es el Dr. Vega: aprueba la política, el alcance y el riesgo residual. El equipo técnico se reparte seguridad, aplicación y monitoreo. La política resume siete compromisos.",
    crit(R3, "Contexto, alcance y liderazgo", "10 %"));
  tabla(pres, s, [
    ["Rol", "Persona", "Responsabilidad"],
    ["Dirección", "Dr. Vega", "Aprueba la política y el riesgo residual"],
    ["Seguridad e infraestructura", "Jhoel Apaza", "Riesgos, red, nube, secretos, copias, incidentes"],
    ["Aplicación", "Fran Calizaya", "Desarrollo seguro, permisos, pruebas, cambios"],
    ["Monitoreo y cumplimiento", "Joselyn Yucra", "SIEM, métricas, auditoría, datos personales"],
    ["Usuarios", "Recepción y doctores", "Cuidar su clave y su celular, avisar eventos"],
  ], { y: 1.55, h: 3.3, anchos: [3.4, 2.6, 6.1], tam: 13 });
  s.addText("Política: solo quien atiende ve lo clínico · cuenta propia y segundo factor · ningún secreto en el código · copias diarias y horarias con restauración probada · cambios con revisión y pruebas · incidentes registrados · cumplimiento de la Ley 29733.",
    { x: 0.6, y: 5.1, w: 12.1, h: 1.5, fontSize: 16, italic: true, color: C.text2, margin: 0, valign: "top", isTextBox: true });

  s = contenido(pres, "S2-E1 SGSI", "Inventario y clasificación", "JY",
    "Cada activo se valora de 1 a 3 en confidencialidad, integridad y disponibilidad. Las historias clínicas, las radiografías, las copias y los secretos tienen el valor más alto. El detalle está en el Excel del inventario.",
    crit(R3, "Inventario y clasificación", "10 %"));
  s.addChart(pres.charts.BAR, [
    { name: "Activos", labels: ["Información", "Infraestructura", "Identidad", "Servicio", "Software", "Persona"], values: [9, 8, 3, 3, 2, 1] },
  ], opcionesGrafico({ x: 0.6, y: 1.5, w: 6.4, h: 5.2, barDir: "col", chartColors: [AZUL], showLegend: false, showTitle: true, title: "Activos por tipo", dataLabelPosition: "outEnd" }));
  tabla(pres, s, [
    ["Clase", "Ejemplos"],
    ["Secreto", "Claves de Key Vault, cuentas de Azure y GitHub"],
    ["Confidencial", "Historias clínicas, datos personales, copias"],
    ["Interno", "Agenda, caja, auditoría, servidores"],
    ["Público", "Web y dominio"],
  ], { x: 7.3, y: 1.6, w: 5.4, h: 3.2, anchos: [1.7, 3.7], tam: 13 });

  s = contenido(pres, "S2-E1 SGSI", "Evaluación de riesgos: de 11 altos a ninguno", "JY",
    "Riesgo = probabilidad por impacto, de 1 a 25. Antes de los controles había 11 riesgos altos o críticos; después ninguno pasa de 9, el nivel que acepta la dirección. El mayor que queda es R08: una sola VM por servicio.",
    crit(R3, "Evaluación y tratamiento de riesgos", "18 %"));
  s.addChart(pres.charts.BAR, [
    { name: "Inherente", labels: RIESGOS.map((r) => r[0]), values: RIESGOS.map((r) => r[1]) },
    { name: "Residual", labels: RIESGOS.map((r) => r[0]), values: RIESGOS.map((r) => r[2]) },
  ], opcionesGrafico({ x: 0.6, y: 1.45, w: 12.1, h: 5.35, barDir: "col", chartColors: ["8EA2C6", AZUL], showLegend: true, legendPos: "t", legendFontSize: 12,
    showTitle: true, title: "Riesgo inherente y residual (P × I)", valAxisMaxVal: 25, dataLabelPosition: "outEnd", dataLabelFontSize: 10, barGapWidthPct: 60 }));

  s = contenido(pres, "S2-E1 SGSI", "Tratamiento de los riesgos más altos", "JY",
    "Los dos riesgos críticos eran el robo de contraseñas del panel y la filtración de secretos. El MFA obligatorio y Key Vault con OIDC los bajaron a 8.",
    crit(R3, "Evaluación y tratamiento de riesgos", "18 %"));
  tabla(pres, s, [
    ["Riesgo", "Antes", "Control principal (ISO/IEC 27002)", "Después"],
    ["R01 Robo de contraseña del panel", "20", "MFA obligatorio, límite de intentos (5.17, 8.5)", "8"],
    ["R05 Filtración de secretos", "20", "Key Vault, OIDC, nada en el repositorio (8.24)", "8"],
    ["R02 Ataque a la web", "16", "WAF, consultas preparadas, pruebas (8.28)", "8"],
    ["R03 Acceso directo a MySQL", "15", "Sin acceso público, TLS, 4 usuarios (8.22)", "5"],
    ["R04 Pérdida de datos", "15", "Tres copias, restauración probada (8.13)", "4"],
    ["R06 SSH no autorizado", "15", "Solo llaves, jump host (8.2, 8.20)", "5"],
  ], { y: 1.55, anchos: [3.9, 1.1, 5.9, 1.2], tam: 13 });

  s = contenido(pres, "S2-E1 SGSI", "Declaración de Aplicabilidad", "JY",
    "Los 93 controles de ISO/IEC 27001:2022: 63 implementados, 23 parciales, 5 planificados y 2 que no aplican (7.6 áreas seguras propias y 8.30 desarrollo externalizado).",
    crit(R3, "SoA y selección de controles", "12 %"));
  const temas = ["5. Organizacionales", "6. Personas", "7. Físicos", "8. Tecnológicos"];
  s.addChart(pres.charts.BAR, [
    { name: "Implementado", labels: temas, values: [27, 2, 9, 25] },
    { name: "Parcial", labels: temas, values: [9, 3, 3, 8] },
    { name: "Planificado", labels: temas, values: [1, 3, 1, 0] },
    { name: "No aplica", labels: temas, values: [0, 0, 1, 1] },
  ], opcionesGrafico({ x: 0.6, y: 1.45, w: 8.0, h: 5.35, barDir: "bar", barGrouping: "stacked", chartColors: [AZUL, AMARILLO, "8EA2C6", "C9D3E6"],
    showLegend: true, legendPos: "b", legendFontSize: 12, showTitle: true, title: "Controles por tema y estado", showValue: false }));
  cifra(pres, s, 9.0, 1.7, 3.7, "69 %", "de los 91 controles aplicables ya implementados");
  cifra(pres, s, 9.0, 4.1, 3.7, "2", "no aplican: 7.6 y 8.30");

  s = contenido(pres, "S2-E1 SGSI", "Políticas que se cumplen solas", "JY",
    "Cada política tiene un control técnico que la hace cumplir, no solo un documento. Los procedimientos están en infra/README.md.",
    crit(R3, "Políticas y procedimientos", "10 %"));
  tabla(pres, s, [
    ["Política", "Control técnico"],
    ["Control de acceso", "35 políticas de Laravel por rol"],
    ["Contraseñas y MFA", "Contraseña propia al primer ingreso y MFA obligatorio"],
    ["Secretos", "Key Vault, OIDC e identidades administradas"],
    ["Copias de seguridad", "Cron de vm-app; restauración probada cada trimestre"],
    ["Gestión de cambios", "Pull request con la CI en verde y despliegue registrado"],
    ["Red", "nftables con política drop y NSG"],
    ["Datos de prueba", "Solo datos generados (DemoSeeder)"],
  ], { y: 1.55, anchos: [3.6, 8.5], tam: 13 });

  s = contenido(pres, "S2-E1 SGSI", "Identidades, accesos y protección de datos", "JY",
    "El panel se abre desde internet para trabajar fuera de la clínica, así que exige contraseña propia y el código de la app autenticadora. Lo clínico solo lo ve quien atiende y el secreto del MFA se guarda cifrado.",
    crit(R3, "Identidades, accesos y protección de datos", "8 %"));
  await cuadricula(pres, s, [
    ["FaMobileScreen", "MFA obligatorio", "Contraseña y código de la app; códigos de recuperación"],
    ["FaUsersGear", "Roles", "35 políticas; lo clínico solo para quien atiende"],
    ["FaServer", "Servidores y nube", "Solo llaves SSH; RBAC mínimo; identidades administradas"],
    ["FaUserLock", "Datos personales", "Consentimiento firmado; nada clínico se borra; datos de prueba"],
  ]);

  s = contenido(pres, "S2-E1 SGSI", "Incidentes, continuidad y recuperación", "JY",
    "Procedimiento: detectar, evaluar, contener, recuperar, notificar y aprender. Se registraron tres incidentes reales del despliegue; dos credenciales expuestas siguen abiertas hasta rotarlas. La continuidad se probó con simulacros.",
    crit(R3, "Incidentes, continuidad y recuperación", "8 %"));
  tabla(pres, s, [
    ["ID", "Qué pasó", "Acción", "Estado"],
    ["INC-01", "Clave del tablero de Wazuh mostrada en una consola", "Rotarla y guardarla solo en Key Vault", "Abierto"],
    ["INC-02", "Contraseña del registrador visible en una captura", "Cambiarla y activar MFA", "Abierto"],
    ["INC-03", "El DNAT del honeypot capturaba el SSH de la VPN", "DNAT solo para la IP de la WAN", "Cerrado"],
  ], { y: 1.55, h: 2.5, anchos: [1.3, 4.9, 4.4, 1.5], tam: 12 });
  cifra(pres, s, 0.6, 4.4, 3.8, "12 s", "restaurar la base desde Blob");
  cifra(pres, s, 4.75, 4.4, 3.8, "1,4 s", "la VPN volvió sola tras un corte");
  cifra(pres, s, 8.9, 4.4, 3.8, "1 h", "RPO con la copia horaria a la sede");

  s = contenido(pres, "S2-E1 SGSI", "Ética de la seguridad (ACM)", "JY",
    "Seguimos el Código de Ética de la ACM: privacidad, confidencialidad, acceso solo autorizado y sistemas robustos. La declaración del equipo va firmada en el informe.",
    crit(R3, "Ética de la seguridad", "8 %"));
  await cuadricula(pres, s, [
    ["FaUserLock", "Confidencialidad", "Ningún dato real, credencial ni llave en informes o capturas"],
    ["FaFlask", "Alcance ético de las pruebas", "Solo sobre infraestructura propia y con datos generados"],
    ["FaFileSignature", "Consentimiento", "El paciente firma su consentimiento informado en el sistema"],
    ["FaBullhorn", "Divulgación responsable", "Fallas de terceros se reportan en privado antes de publicarlas"],
  ]);

  s = contenido(pres, "S2-E1 SGSI", "Métricas y auditoría interna", "JY",
    "La auditoría del 19/11 comparó la SoA con la configuración real y encontró seis no conformidades; la mayor son dos credenciales sin rotar. Todas tienen responsable y plazo, y la revisión por la dirección queda en un acta.",
    crit(R3, "Monitoreo, medición y auditoría interna", "9 %"));
  cifra(pres, s, 0.6, 1.6, 3.6, "6/6", "agentes de Wazuh activos");
  cifra(pres, s, 0.6, 3.9, 3.6, "6", "no conformidades, una mayor");
  tabla(pres, s, [
    ["Hallazgo", "Tipo", "Plazo"],
    ["Dos credenciales expuestas sin rotar", "Mayor", "20/11"],
    ["La rama main no exige revisión ni CI", "Menor", "26/11"],
    ["Key Vault con red pública", "Menor", "26/11"],
    ["Vigilancia de /up desactivada", "Menor", "20/11"],
    ["WAF en modo detección", "Menor", "03/12"],
    ["Suricata solo detecta, no bloquea", "Observación", "10/12"],
  ], { x: 4.8, y: 1.55, w: 7.9, anchos: [4.9, 1.7, 1.3], tam: 13 });

  // ---------------------------------------------------------------------------------------- S2-E2
  const R4 = "CE0312-CE0313";
  entregable(pres, "S2-E2 Red implementada", "S2-E2 · Implementación, Testing y Monitoreo de Red", "Semestre 2 · CE0312 y CE0313", "JA", [
    ["Trazabilidad con el diseño de red", "6 %", 1],
    ["Conectividad híbrida (VPN y enrutamiento)", "15 %", 1],
    ["Seguridad perimetral (firewall, DMZ, segmentación)", "22 %", 2],
    ["IDS/IPS, honeypot y detección", "18 %", 1],
    ["Monitoreo, registros y visibilidad", "15 %", 1],
    ["Plan y resultados de pruebas", "12 %", 2],
    ["Calidad documental, evidencias y repositorio", "6 %", "Diapositiva de evidencias"],
    ["Sustentación técnica", "6 %", "Esta exposición"],
  ], "Rúbrica de la red implementada.");

  s = contenido(pres, "S2-E2 Red implementada", "Del diseño a lo implementado", "JA",
    "Casi todo se implementó tal cual. Dos cambios: el firewall pasó de OPNsense a Linux configurado por código, y el panel se abrió a internet con MFA porque el personal necesita entrar fuera de la clínica.",
    crit(R4, "Trazabilidad con el diseño", "6 %"));
  tabla(pres, s, [
    ["Diseño (S1-E1)", "Implementado", "Cambio"],
    ["Firewall OPNsense", "Linux: nftables, strongSwan, Suricata, Unbound", "Mismas funciones, por código"],
    ["Segmentos y ruteo", "Subredes y rt-via-firewall", "Ninguno"],
    ["VPN IPsec", "strongSwan IKEv2, AES-256-GCM", "Ninguno"],
    ["DMZ con WAF", "nginx + ModSecurity + CRS", "Se sumó límite de peticiones"],
    ["Panel solo desde la sede", "Panel desde internet con MFA", "Aprobado el 19/11"],
    ["Honeypot", "OpenCanary 22 y 3306", "Se alterna con recepción"],
  ], { y: 1.55, anchos: [3.2, 5.1, 3.8], tam: 13 });

  s = contenido(pres, "S2-E2 Red implementada", "VPN establecida y con tráfico real", "JA",
    "swanctl --list-sas muestra el túnel ESTABLISHED en los dos extremos con AES-GCM de 256 bits y NAT-T. Se cortó a propósito a las 11:26:34 y a las 11:26:35 ya respondía el ping entre las LAN.",
    crit(R4, "Conectividad híbrida (VPN y enrutamiento)", "15 %"));
  consola(s, [
    "vega: #5, ESTABLISHED, IKEv2",
    "  local  'IP_PUBLICA_SEDE' @ 10.10.0.4[4500]",
    "  remote 'IP_PUBLICA_PROD' @ IP_PUBLICA_PROD[4500]",
    "  redes: INSTALLED, TUNNEL-in-UDP, ESP:AES_GCM_16-256",
    "    local 10.10.0.0/16 · remote 10.20.0.0/16",
  ], { x: 0.6, y: 1.6, w: 7.6, h: 3.2 });
  cifra(pres, s, 8.7, 1.6, 4.0, "1,4 s", "recuperación tras un corte");
  cifra(pres, s, 8.7, 3.6, 4.0, "4500", "UDP: NAT-T, como con un proveedor real");
  s.addText("Enrutamiento: desde la recepción el dominio resuelve a 10.20.3.4 (DNS de la sede) y el panel responde 200 por la VPN.",
    { x: 0.6, y: 5.3, w: 12.1, h: 0.9, fontSize: 16, color: C.text1, margin: 0, isTextBox: true });

  s = contenido(pres, "S2-E2 Red implementada", "Segmentación: cada bloqueo deja rastro", "JA",
    "Los firewalls registran cada conexión bloqueada con un prefijo que Wazuh cuenta. Arriba, la recepción intentó llegar directo a la app; abajo, la app intentó SSH a la recepción. Las dos fueron bloqueadas.",
    crit(R4, "Seguridad perimetral", "22 %"));
  consola(s, [
    "fw-reenvio-bloqueo  SRC=10.10.30.10  DST=10.20.4.4  DPT=80  SYN",
    " ",
    "fw-reenvio-bloqueo  SRC=10.20.4.4  DST=10.10.30.10  DPT=22  SYN",
  ], { x: 0.6, y: 1.6, w: 12.1, h: 1.8, fontSize: 15 });
  cifra(pres, s, 0.6, 3.8, 3.8, "14", "bloqueos registrados en producción en el día");
  cifra(pres, s, 4.75, 3.8, 3.8, "6", "fw-personal-bloqueo: recepción hacia servidores");
  cifra(pres, s, 8.9, 3.8, 3.8, "Filtrados", "22, 3306 y 8080 desde internet");

  s = contenido(pres, "S2-E2 Red implementada", "DMZ: TLS, cabeceras y WAF", "JA",
    "El proxy termina el HTTPS con Let's Encrypt, añade cabeceras de seguridad, limita las peticiones al panel y pasa todo por ModSecurity. El WAF sigue en detección hasta revisar sus 615 eventos.",
    crit(R4, "Seguridad perimetral", "22 %"));
  await cuadricula(pres, s, [
    ["FaLock", "HTTPS", "Let's Encrypt para la raíz y www, renovación automática"],
    ["FaListCheck", "Cabeceras", "HSTS, X-Frame-Options, nosniff, Referrer-Policy"],
    ["FaGaugeHigh", "Límite por IP", "80 respuestas 429 en 250 peticiones seguidas al panel"],
    ["FaShieldHalved", "ModSecurity + CRS", "615 eventos en detección; /.env responde 403"],
  ]);

  s = contenido(pres, "S2-E2 Red implementada", "IDS y honeypot: escaneos de internet", "JA",
    "Lo importante de Suricata son los escaneos reales: bots buscando archivos .env, GraphQL y MySQL expuesto. Ninguno llegó a un servicio. El honeypot está listo en código; en esta ronda la VM de laboratorio activa era la de recepción.",
    crit(R4, "IDS/IPS, honeypot y detección", "18 %"));
  s.addChart(pres.charts.BAR, [
    { name: "Alertas", labels: ["Archivo .env (prod.)", "GraphQL (prod.)", "MySQL 3306 (sede)", "DNS nulo (sede)"], values: [116, 15, 13, 12] },
  ], opcionesGrafico({ x: 0.6, y: 1.45, w: 7.6, h: 5.3, barDir: "bar", chartColors: [AZUL], showLegend: false, showTitle: true, title: "Escaneos detectados por Suricata el 19/11", dataLabelPosition: "outEnd" }));
  await tarjeta(pres, s, 8.6, 1.55, 4.1, 4.9, { titulo: "Honeypot", icono: "FaBug", tono: "oscuro",
    texto: "OpenCanary con SSH y MySQL falsos en la DMZ de la sede.\n\nSe activa con desplegar.sh sede honeypot; su prueba es la única pendiente." });

  s = contenido(pres, "S2-E2 Red implementada", "Monitoreo: Wazuh centraliza los registros", "JA",
    "Seis agentes activos, firewalls incluidos. El proxy y la app generan más alertas porque reciben el tráfico de internet. Las 82 de nivel 10 son ráfagas de errores 400 desde una misma IP: escaneos automáticos.",
    crit(R4, "Monitoreo, registros y visibilidad", "15 %"));
  s.addChart(pres.charts.BAR, [
    { name: "Alertas", labels: ["vm-proxy", "vm-app", "vm-fw-prod", "vm-fw-sede", "vm-wazuh", "vm-recepcion"], values: [1194, 827, 469, 354, 302, 296] },
  ], opcionesGrafico({ x: 0.6, y: 1.45, w: 7.6, h: 5.3, barDir: "bar", chartColors: [AZUL], showLegend: false, showTitle: true, title: "Alertas por agente el 19/11", dataLabelPosition: "outEnd" }));
  tabla(pres, s, [
    ["Regla", "Nivel", "Alertas"],
    ["Web server 400", "5", "1 320"],
    ["Archivo .env (Suricata)", "3", "115"],
    ["Varios 400 de una IP", "10", "82"],
    ["URL sospechosa", "6", "28"],
  ], { x: 8.5, y: 1.6, w: 4.2, h: 3.0, anchos: [2.3, 0.8, 1.1], tam: 12 });

  s = contenido(pres, "S2-E2 Red implementada", "Plan y resultados: 24 pruebas", "JA",
    "Diez positivas y diez negativas de conectividad y segmentación, una de recuperación y tres de detección. Cumplen 23; queda la del honeypot. El plan completo está en el Excel de pruebas.",
    crit(R4, "Plan y resultados de pruebas", "12 %"));
  s.addChart(pres.charts.BAR, [
    { name: "Pruebas", labels: ["Positivas", "Negativas", "Recuperación", "Detección (1 pendiente)"], values: [10, 10, 1, 3] },
  ], opcionesGrafico({ x: 0.6, y: 1.45, w: 6.6, h: 5.3, barDir: "col", chartColors: [AZUL], showLegend: false, showTitle: true, title: "Pruebas por tipo", dataLabelPosition: "outEnd" }));
  tabla(pres, s, [
    ["Desde → hacia", "Obtenido"],
    ["Internet → prod. 22, 3306", "Filtrados"],
    ["Recepción → Wazuh :22", "Bloqueado"],
    ["Recepción → app :80", "Bloqueado"],
    ["Proxy → app :22", "Bloqueado"],
    ["App → MySQL", "TLS 1.3"],
    ["App → sede :22 (copia)", "Abierto"],
  ], { x: 7.5, y: 1.6, w: 5.2, anchos: [3.3, 1.9], tam: 13 });

  s = contenido(pres, "S2-E2 Red implementada", "Incidencias y limitaciones", "JA",
    "Cinco problemas reales del despliegue y cómo se resolvieron. Limitaciones: Suricata y el WAF solo detectan, el honeypot no estuvo activo y hay una VM por servicio.",
    crit(R4, "Plan y resultados de pruebas", "12 %"));
  tabla(pres, s, [
    ["Incidencia", "Solución"],
    ["Las subredes no salían a internet", "Dos reglas en el NSG de la LAN del firewall"],
    ["El SSH de la VPN terminaba en el honeypot", "DNAT solo para la IP de la WAN"],
    ["El agente de Wazuh borró el servidor", "Versión fija 4.12.0 y no instalarlo en vm-wazuh"],
    ["La web cargaba recursos con http://", "REMOTE_ADDR real hacia PHP"],
    ["Puertos de configuración inicial abiertos", "Quitados del firewall y del NSG"],
  ], { y: 1.55, anchos: [5.6, 6.5], tam: 14 });

  // ---------------------------------------------------------------------------------------- S2-E3
  const R5 = "CE0332-CE0333";
  entregable(pres, "S2-E3 Centro de datos", "S2-E3 · Gestión y Control del Centro de Datos", "Semestre 2 · CE0332 y CE0333 · Implementación, Gestión y Control de Centro de Datos", "FC", [
    ["Trazabilidad con el diseño de centro de datos", "6 %", 1],
    ["Implementación on-premise (ESXi y VMs)", "18 %", 1],
    ["Implementación AWS (VPC, EC2, SG, NACL) en Azure", "18 %", 1],
    ["Identidades y accesos", "14 %", 1],
    ["Inventario final con evidencia", "12 %", 1],
    ["Continuidad: backup, restore y recuperación probada", "14 %", 2],
    ["Monitoreo y control operativo", "10 %", 2],
    ["Calidad documental, control de versiones y sustentación", "8 %", "Evidencias y esta exposición"],
  ], "Rúbrica del centro de datos implementado.");

  s = contenido(pres, "S2-E3 Centro de datos", "Del diseño a lo implementado", "FC",
    "Lo diseñado en el S1-E2 está en funcionamiento y verificado con comandos el 19/11.",
    crit(R5, "Trazabilidad con el diseño", "6 %"));
  tabla(pres, s, [
    ["Diseño (S1-E2)", "Implementado y verificado"],
    ["3 VM de producción B2ats_v2", "vm-fw, vm-proxy y vm-app en ejecución"],
    ["MySQL privado con TLS", "Acceso público deshabilitado; TLS 1.3 desde vm-app"],
    ["Key Vault con RBAC", "10 secretos; la VM los lee con su identidad"],
    ["Blob con versiones", "Versiones y borrado suave de 14 días activos"],
    ["OIDC para GitHub", "2 credenciales federadas para main; publicación en verde"],
    ["RPO 1 h", "Copias de 8:15, 9:15 y 10:15 en la sede"],
  ], { y: 1.55, anchos: [4.4, 7.7], tam: 13 });

  s = contenido(pres, "S2-E3 Centro de datos", "Implementación de la sede", "FC",
    "La sede reproduce en Azure el diseño local: cada port group de ESXi es una subred con su NSG y las VM del host son VM de Azure con los mismos papeles.",
    crit(R5, "Implementación on-premise (ESXi y VMs)", "18 %"));
  tabla(pres, s, [
    ["Diseño en ESXi", "Implementación", "Estado el 19/11"],
    ["VM firewall", "vm-fw con 2 NIC (10.10.0.4 y 10.10.1.4)", "nftables, strongSwan, Suricata, Unbound activos"],
    ["VLAN 10 administración", "snet-mgmt", "El firewall hace de jump host"],
    ["VLAN 30 personal", "snet-personal con vm-recepcion", "Abre el panel por la VPN"],
    ["VLAN 50 servidores", "snet-servidores con vm-wazuh", "SIEM activo y copias horarias"],
    ["VLAN 60 DMZ", "snet-dmz con vm-honeypot", "Definida en código; se alterna con recepción"],
  ], { y: 1.55, anchos: [2.9, 4.4, 4.8], tam: 13 });

  s = contenido(pres, "S2-E3 Centro de datos", "Implementación en la nube y uso medido", "FC",
    "VPC, EC2, Security Groups y NACL en Azure: la VNet con 5 subredes, las VM, 4 NSG y nftables. Uso medido el 19/11: las VM pequeñas van bien y el punto a vigilar es el disco de Wazuh.",
    crit(R5, "Implementación AWS (en Azure)", "18 %"));
  tabla(pres, s, [
    ["VM", "Tamaño", "IP", "RAM usada", "Disco"],
    ["vm-fw (prod.)", "B2ats_v2", "10.20.0.4 / 10.20.1.4", "422 de 887 MB", "4,4 de 29 GB"],
    ["vm-proxy", "B2ats_v2", "10.20.3.4", "330 de 887 MB", "4,3 de 29 GB"],
    ["vm-app", "B2ats_v2", "10.20.4.4", "300 de 887 MB", "4,8 de 29 GB"],
    ["vm-fw (sede)", "B2ats_v2", "10.10.0.4 / 10.10.1.4", "329 de 887 MB", "4,4 de 29 GB"],
    ["vm-wazuh", "B2als_v2", "10.10.50.10", "2 697 de 3 906 MB", "41 de 61 GB"],
  ], { y: 1.55, anchos: [2.2, 1.7, 3.2, 2.7, 2.3], tam: 13 });

  s = contenido(pres, "S2-E3 Centro de datos", "Identidades y accesos", "FC",
    "Ninguna clave de la nube en archivos. La propia auditoría lo comprobó: la cuenta del operador no puede leer los datos de Blob, solo la VM con su identidad.",
    crit(R5, "Identidades y accesos", "14 %"));
  await cuadricula(pres, s, [
    ["FaMobileScreen", "Panel", "Contraseña propia y MFA obligatorio; 8 pruebas automáticas"],
    ["FaServer", "vm-app", "Identidad administrada: Key Vault Secrets User y Blob Data Contributor"],
    ["FaGithub", "GitHub Actions", "OIDC solo desde main; Virtual Machine Contributor"],
    ["FaKey", "Administración", "Solo llaves SSH, jump host o run-command; rotación desde Key Vault"],
  ]);

  s = contenido(pres, "S2-E3 Centro de datos", "Inventario final con evidencia", "FC",
    "27 recursos en producción y 16 en la sede, listados con az resource list el 19/11. Todos están en Bicep: si se pierde uno, se vuelve a crear con un comando.",
    crit(R5, "Inventario final con evidencia", "12 %"));
  tabla(pres, s, [
    ["Tipo", "Producción (rg-vega-prod)", "Sede (rg-vega-clinica)"],
    ["Máquinas virtuales", "vm-fw, vm-proxy, vm-app", "vm-fw, vm-wazuh, vm-recepcion"],
    ["Red", "vnet-vega, 4 NSG, rutas, IP pública", "vnet-clinica, 3 NSG, rutas, IP pública"],
    ["Datos", "MySQL Flexible, Blob, Key Vault", "Copias horarias en vm-wazuh"],
    ["Monitoreo", "Log Analytics, 2 alertas, grupo de acción", "Wazuh"],
    ["Identidad", "id-github-despliegue (OIDC)", "—"],
    ["Total", "27 recursos", "16 recursos"],
  ], { y: 1.55, anchos: [2.6, 5.0, 4.5], tam: 14 });

  s = contenido(pres, "S2-E3 Centro de datos", "Simulacro: restaurar desde Blob", "FC",
    "La copia de Blob se restauró en una base aparte, sin tocar producción. Los totales coinciden en las cinco tablas comparadas y se restauraron las 70 tablas. Al terminar, la base del simulacro se borró.",
    crit(R5, "Continuidad: backup, restore y recuperación", "14 %"));
  consola(s, [
    "copia elegida: respaldo_2026-11-19_065405.sql.gz",
    "inicio 11:24:13 · fin 11:24:25 · duracion_s: 12",
    "patients      produccion=268  restaurada=268",
    "appointments  produccion=874  restaurada=874",
    "payments      produccion=733  restaurada=733",
    "tablas restauradas: 70 · base de simulacro borrada",
  ], { x: 0.6, y: 1.6, w: 8.0, h: 3.6 });
  cifra(pres, s, 9.0, 1.6, 3.7, "12 s", "de la descarga a la base restaurada");
  cifra(pres, s, 9.0, 3.6, 3.7, "70", "tablas restauradas, mismos totales");
  s.addText("La sede, además, recibe una copia de la base cada hora: 8:15, 9:15 y 10:15 el 19/11.",
    { x: 0.6, y: 5.6, w: 12.1, h: 0.7, fontSize: 16, color: C.text1, margin: 0, isTextBox: true });

  s = contenido(pres, "S2-E3 Centro de datos", "RTO y RPO: planeado contra medido", "FC",
    "Cuatro de cinco escenarios medidos y cumplen. Falta el más caro, perder vm-app entera: no se hizo sobre producción y se programa con la clínica.",
    crit(R5, "Continuidad: backup, restore y recuperación", "14 %"));
  tabla(pres, s, [
    ["Escenario", "RTO planeado", "Medido", "Cumple"],
    ["Restaurar la base desde Blob", "1 h", "12 s", "Sí"],
    ["Copia horaria a la sede (RPO)", "1 h", "1 h", "Sí"],
    ["Corte de la VPN", "< 1 min", "1,4 s", "Sí"],
    ["Publicar o volver una versión", "< 5 min", "65 s", "Sí"],
    ["Perder vm-app entera", "2 h", "No medido", "Pendiente"],
  ], { y: 1.55, anchos: [5.2, 2.4, 2.4, 2.1], tam: 14 });

  s = contenido(pres, "S2-E3 Centro de datos", "Monitoreo y capacidad", "FC",
    "Azure Monitor avisa por correo si vm-app se cae o se satura; Wazuh muestra la seguridad; el presupuesto avisa al 50 y 80 %. La alerta de capacidad a vigilar es el disco de Wazuh, al 66 %.",
    crit(R5, "Monitoreo y control operativo", "10 %"));
  s.addChart(pres.charts.BAR, [
    { name: "RAM (%)", labels: ["vm-fw prod.", "vm-proxy", "vm-app", "vm-fw sede", "vm-wazuh"], values: [48, 37, 34, 37, 69] },
    { name: "Disco (%)", labels: ["vm-fw prod.", "vm-proxy", "vm-app", "vm-fw sede", "vm-wazuh"], values: [16, 16, 17, 16, 66] },
  ], opcionesGrafico({ x: 0.6, y: 1.45, w: 8.0, h: 5.35, barDir: "col", chartColors: [AZUL, AMARILLO], showLegend: true, legendPos: "t", legendFontSize: 12,
    valAxisMaxVal: 100, showTitle: true, title: "Uso de RAM y disco el 19/11 (%)", dataLabelPosition: "outEnd" }));
  await tarjeta(pres, s, 9.0, 1.55, 3.7, 4.9, { titulo: "Alertas", icono: "FaBell", tono: "oscuro",
    texto: "VM caída (sev. 1)\nCPU > 80 % (sev. 2)\nPresupuesto 50 % y 80 %\nWazuh nivel 10 o más" });

  s = contenido(pres, "S2-E3 Centro de datos", "Control de cambios", "FC",
    "Todo cambio entra por pull request con las cuatro comprobaciones de la CI, se fusiona y se publica con un workflow que queda registrado; la acción volver deja la versión anterior en segundos.",
    crit(R5, "Monitoreo y control operativo", "10 %"));
  await imagen(s, "despliegue.png", { x: 0.6, y: 1.35, w: 12.1, h: 2.4 }, "De un cambio a producción");
  tabla(pres, s, [
    ["PR", "Cambio"],
    ["#13", "Despliegue en Azure alineado con el E1"],
    ["#16", "Firewall Linux automático en lugar de OPNsense"],
    ["#18", "HTTPS, Wazuh, copias y SSH cerrado"],
    ["#24", "Panel con MFA obligatorio y acceso desde internet"],
  ], { y: 4.0, h: 2.7, anchos: [1.2, 10.9], tam: 13 });

  // ---------------------------------------------------------------------------------------- Evidencias y cierre
  pres.addSection({ title: "Evidencias" });
  s = contenido(pres, "Evidencias", "Calidad documental, evidencias y repositorio", "JY",
    "Este criterio está en todas las rúbricas. Cada entregable tiene su informe en Word y PDF con referencias IEEE, los anexos en Excel, las salidas reales de los comandos y el código en GitHub, sin credenciales ni datos personales reales.",
    "Calidad documental y trazabilidad · los cinco entregables");
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
  const pres = await deckRubrica();
  const ruta = path.join(AQUI, archivo);
  await pres.writeFile({ fileName: ruta });
  if (applyTheme) await applyTheme(ruta, THEME);
  console.log(archivo, pres.numero, "diapositivas");
}

main().catch((e) => { console.error(e); process.exit(1); });
