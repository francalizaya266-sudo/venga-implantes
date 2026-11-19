// Arma las cinco presentaciones de CE03 (una por informe), repartidas entre los tres integrantes, con los colores
// de la UPeU (azul y amarillo). Los datos salen de los informes y de evidencias/ (19/11/2026).
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
    showValue: true, dataLabelFontSize: 12, dataLabelColor: "1A1A1A", titleColor: AZUL, titleFontSize: 14,
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
// 1. S1-E1 Análisis y Diseño de Red (CE0311)
// ======================================================================================================
async function deckRed() {
  const pres = nuevaPresentacion("Análisis y Diseño de Red");
  const C = pres.SchemeColor;
  pres.addSection({ title: "Portada" });
  portada(pres, "Análisis y Diseño de Red", "Semestre 1 · Entregable 1 · CE0311",
    "Presentación del diseño de la red del sistema de Clínica Vega Implant. Joselyn abre con el contexto, Jhoel explica la topología y la seguridad, y Fran cierra con la VPN, los flujos y las pruebas planeadas.");
  await agenda(pres, [
    ["JY", ["El problema y los procesos críticos", "Objetivos medibles", "Equivalencia ESXi y AWS → Azure"]],
    ["JA", ["Topología general", "Direccionamiento y ruteo", "Seguridad perimetral en capas"]],
    ["FC", ["VPN IPsec", "Flujo de información", "Plan de pruebas y hoja de ruta"]],
  ], "Cada uno expone tres temas. El círculo amarillo arriba a la derecha indica quién habla en cada diapositiva.");

  pres.addSection({ title: "Contexto" });
  let s = contenido(pres, "Contexto", "Una red para datos de salud", "JY",
    "La clínica guarda historias clínicas y radiografías, que la Ley 29733 trata como datos sensibles. La red tiene que publicar la web sin exponer la base de datos, unir la sede con el centro de datos por un canal cifrado y detectar ataques.");
  const procesos = [
    ["FaCalendarCheck", "Reservas en línea", "Pacientes desde internet, a cualquier hora"],
    ["FaUserDoctor", "Historia clínica", "Odontograma y radiografías de hasta 25 MB"],
    ["FaCashRegister", "Agenda y caja", "Recepción, en la clínica o fuera"],
    ["FaDatabase", "Copias de seguridad", "Canal privado hacia la sede"],
  ];
  for (let i = 0; i < procesos.length; i++) {
    const [ico, t, d] = procesos[i];
    await tarjeta(pres, s, 0.6 + (i % 2) * 6.15, 1.55 + Math.floor(i / 2) * 2.6, 5.95, 2.35, { titulo: t, texto: d, icono: ico });
  }

  s = contenido(pres, "Contexto", "Seis objetivos que se pueden medir", "JY",
    "Cada objetivo tiene un indicador y una meta. En el Semestre 2 se comprobaron con pruebas: por ejemplo, la VPN volvió en 1,4 segundos frente a una meta de 60.");
  tabla(pres, s, [
    ["N.º", "Objetivo", "Meta"],
    ["O1", "Exponer solo lo necesario", "Solo 80, 443 y la VPN; SSH solo desde la IP de administración"],
    ["O2", "Aislar la base de datos", "Solo snet-app llega a MySQL; sin IP pública"],
    ["O3", "Cifrar lo que cruza internet", "Todo por IPsec con AES-256-GCM"],
    ["O4", "Segmentar la sede", "100 % de las pruebas negativas bloqueadas"],
    ["O5", "Detectar", "Todas las VM y los firewalls envían registros al SIEM"],
    ["O6", "Recuperar la VPN sola", "Menos de 60 s tras un corte"],
  ], { y: 1.55, anchos: [0.9, 4.4, 6.8], tam: 15 });

  s = contenido(pres, "Contexto", "Misma arquitectura, otra nube", "JY",
    "La guía propone VMware ESXi y AWS y admite cambiar la tecnología si se mantiene la equivalencia. El equipo usa Azure for Students: la sede es una segunda red de Azure con los mismos segmentos y firewall que tendría el local.");
  tabla(pres, s, [
    ["Guía de la línea", "En este proyecto"],
    ["Red local en VMware ESXi", "vnet-clinica (Azure North Central US)"],
    ["VLAN y port groups", "Subredes con su NSG y su ruta"],
    ["AWS VPC", "Azure VNet vnet-vega"],
    ["Security Groups / NACL", "NSG por subred / nftables con política drop"],
    ["Internet y NAT Gateway", "IP pública y NAT del firewall"],
    ["VPN Gateway", "strongSwan IKEv2 en cada firewall"],
    ["CloudWatch", "Azure Monitor y Log Analytics"],
  ], { y: 1.55, anchos: [5.2, 6.9], tam: 15 });

  pres.addSection({ title: "Topología y seguridad" });
  s = contenido(pres, "Topología y seguridad", "Dos redes unidas por una VPN", "JA",
    "Desde internet solo se llega al firewall de producción, que deja pasar la web al proxy de la DMZ. El proxy habla con la aplicación y la aplicación con MySQL. La sede se une por la VPN: desde ahí el personal entra al panel y el SIEM recibe los registros.");
  await imagen(s, "topologia-general.png", { x: 0.6, y: 1.4, w: 12.1, h: 5.45 }, "Topología general: producción y sede unidas por la VPN");

  s = contenido(pres, "Topología y seguridad", "Cada segmento es una subred /24", "JA",
    "Los /16 no se solapan, condición para la VPN, y dejan espacio para 256 subredes. Cada subred interna tiene una ruta cuyo siguiente salto es la LAN del firewall: así todo tráfico entre segmentos pasa por nftables.");
  tabla(pres, s, [
    ["Red", "Segmento", "Prefijo", "Equipo"],
    ["Producción", "snet-dmz", "10.20.3.0/24", "vm-proxy 10.20.3.4"],
    ["Producción", "snet-app", "10.20.4.0/24", "vm-app 10.20.4.4"],
    ["Producción", "snet-mysql (delegada)", "10.20.5.0/24", "MySQL Flexible"],
    ["Sede", "snet-mgmt (VLAN 10)", "10.10.10.0/24", "Administración"],
    ["Sede", "snet-personal (VLAN 30)", "10.10.30.0/24", "vm-recepcion"],
    ["Sede", "snet-servidores (VLAN 50)", "10.10.50.0/24", "vm-wazuh"],
    ["Sede", "snet-dmz (VLAN 60)", "10.10.60.0/24", "vm-honeypot"],
  ], { y: 1.55, w: 8.2, anchos: [1.5, 2.9, 1.8, 2.0], tam: 14 });
  await tarjeta(pres, s, 9.1, 1.55, 3.6, 4.6, { titulo: "Ruteo", icono: "FaRoute", tono: "oscuro",
    texto: "0.0.0.0/0, la propia red y la otra red van a la LAN del firewall.\n\nSolo MySQL responde directo dentro de la VNet." });

  s = contenido(pres, "Topología y seguridad", "Seguridad en capas", "JA",
    "Si una capa falla, la siguiente sigue bloqueando. Los NSG repiten lo esencial de las reglas de nftables; el WAF filtra la aplicación; Suricata y el honeypot detectan; y Wazuh correlaciona todo.");
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

  pres.addSection({ title: "VPN, flujos y pruebas" });
  s = contenido(pres, "VPN, flujos y pruebas", "VPN IPsec sitio a sitio", "FC",
    "strongSwan en cada firewall, IKEv2 con AES-256-GCM. Pasa por NAT-T porque Azure hace NAT a la IP pública, igual que un proveedor de internet. Con close_action start y dpd_action restart, el túnel se levanta solo.");
  cifra(pres, s, 0.6, 1.6, 3.6, "IKEv2", "Negociación con clave compartida guardada en Key Vault");
  cifra(pres, s, 4.7, 1.6, 3.8, "AES-256", "GCM para IKE y ESP, DH MODP-2048");
  cifra(pres, s, 9.0, 1.6, 3.7, "< 60 s", "Meta de reconexión automática tras un corte");
  tabla(pres, s, [
    ["Parámetro", "Valor"],
    ["Redes protegidas", "10.20.0.0/16 ↔ 10.10.0.0/16"],
    ["Encapsulado", "NAT-T, UDP 4500"],
    ["Recuperación", "dpd_delay 30 s · dpd_action restart · close_action start"],
  ], { y: 4.25, anchos: [3.4, 8.7], tam: 15 });

  s = contenido(pres, "VPN, flujos y pruebas", "Recorrido de una petición", "FC",
    "El paciente abre el dominio; el firewall hace DNAT al proxy; el proxy termina el TLS, aplica el WAF y reenvía; la app consulta MySQL por TLS y lee secretos con su identidad. Los registros viajan a Wazuh por la VPN.");
  await imagen(s, "flujo-informacion.png", { x: 0.6, y: 1.35, w: 12.1, h: 5.5 }, "Flujo de una petición web y de los registros");

  s = contenido(pres, "VPN, flujos y pruebas", "Doce pruebas planeadas", "FC",
    "Cada regla tiene una prueba con resultado esperado. En el Semestre 2 se ejecutaron y se compararon con este diseño (S2-E2).");
  tabla(pres, s, [
    ["ID", "Desde → hacia", "Esperado"],
    ["PC-01", "Internet → https://vegaimplantes.me", "200"],
    ["PC-02", "Internet → producción 22, 3306, 8080", "Filtrados"],
    ["PC-04", "Recepción → panel por la VPN", "200"],
    ["PC-05", "Recepción → Wazuh :22", "Bloqueado"],
    ["PC-07", "Honeypot → recepción :22", "Bloqueado"],
    ["PC-08", "Proxy → app :22", "Bloqueado"],
    ["PC-10", "App → MySQL 3306", "Permitido con TLS"],
    ["PC-12", "Corte de la VPN", "Vuelve sola en < 60 s"],
  ], { y: 1.5, anchos: [1.3, 6.8, 4.0], tam: 14 });

  cierre(pres, "Hoja de ruta", [
    ["Diseño", "Red y centro de datos documentados (Semestre 1)."],
    ["Código y despliegue", "Bicep, cloud-init y reglas: toda la red con un comando."],
    ["Mejoras", "Suricata como IPS y WAF en bloqueo, tras medir falsos positivos."],
  ], "Cerramos con la hoja de ruta: del diseño al código y al despliegue, y las mejoras pendientes. Abrimos a preguntas.");
  return pres;
}

// ======================================================================================================
// 2. S1-E2 Análisis y Diseño de Centro de Datos Híbrido (CE0331)
// ======================================================================================================
async function deckCentroDeDatos() {
  const pres = nuevaPresentacion("Análisis y Diseño de Centro de Datos Híbrido");
  const C = pres.SchemeColor;
  pres.addSection({ title: "Portada" });
  portada(pres, "Análisis y Diseño de Centro de Datos Híbrido", "Semestre 1 · Entregable 2 · CE0331",
    "Diseño del centro de datos híbrido: la nube en Azure y la sede de la clínica. Joselyn presenta objetivos y continuidad, Jhoel la arquitectura en la nube, y Fran la sede y la capacidad.");
  await agenda(pres, [
    ["JY", ["Objetivos, RTO y RPO", "Disponibilidad y continuidad"]],
    ["JA", ["Arquitectura en Azure", "Cómputo, datos y secretos", "Identidades sin claves"]],
    ["FC", ["Diseño de la sede (ESXi)", "Integración híbrida", "Dimensionamiento y costo"]],
  ], "Reparto de temas del segundo entregable del Semestre 1.");

  pres.addSection({ title: "Objetivos" });
  let s = contenido(pres, "Objetivos", "Metas de continuidad desde el diseño", "JY",
    "El RPO de 1 hora sale de la copia horaria a la sede; el RTO de 2 horas, de reconstruir todo desde el código. Volver a la versión anterior de la app debe tomar menos de 5 minutos.");
  cifra(pres, s, 0.6, 1.7, 3.8, "1 h", "RPO: lo máximo que se perdería, gracias a la copia horaria a la sede");
  cifra(pres, s, 4.75, 1.7, 3.8, "2 h", "RTO: reconstruir la aplicación entera desde el código");
  cifra(pres, s, 8.9, 1.7, 3.8, "5 min", "Volver a la versión anterior de la aplicación");
  tabla(pres, s, [
    ["Otros objetivos", "Meta"],
    ["Recursos creados por código", "100 % (Bicep y cloud-init)"],
    ["Secretos en archivos del repositorio", "0: todos en Key Vault"],
    ["Gasto mensual", "Menos de 40 USD, con avisos al 50 % y 80 %"],
  ], { y: 4.3, anchos: [5.0, 7.1], tam: 15 });

  pres.addSection({ title: "Nube" });
  s = contenido(pres, "Nube", "Arquitectura en Azure", "JA",
    "Dos grupos de recursos en dos regiones. GitHub entra por OIDC a una identidad administrada; la VM de la aplicación lee secretos y escribe copias con su propia identidad.");
  await imagen(s, "centro-de-datos.png", { x: 0.6, y: 1.35, w: 12.1, h: 5.5 }, "Recursos de Azure, identidades y permisos");

  s = contenido(pres, "Nube", "Cómputo, datos y secretos", "JA",
    "Tres VM de la serie B v2 porque la carga de una clínica es baja y con picos cortos. MySQL administrado y privado; Key Vault con RBAC; Blob con versiones y borrado suave.");
  tabla(pres, s, [
    ["Recurso", "Diseño"],
    ["vm-fw · vm-proxy · vm-app", "Standard_B2ats_v2, 2 vCPU, 1 GiB + 2 GiB de swap, Ubuntu 24.04"],
    ["MySQL Flexible", "8.0, B1ms, 32 GB, copias de 7 días, solo acceso privado, TLS obligatorio"],
    ["Key Vault", "RBAC, borrado suave de 7 días"],
    ["Blob Storage", "Solo HTTPS, sin acceso público, versiones y borrado suave de 14 días"],
    ["Azure Monitor", "VM caída y CPU > 80 %, aviso por correo"],
  ], { y: 1.55, anchos: [3.6, 8.5], tam: 15 });

  s = contenido(pres, "Nube", "Identidades sin claves guardadas", "JA",
    "Ninguna clave de la nube vive en un archivo. La VM y GitHub usan identidades administradas; MySQL tiene cuatro usuarios, cada uno con lo mínimo.");
  const ids = [
    ["FaServer", "vm-app", "Lee secretos de Key Vault y escribe copias en Blob con su identidad administrada"],
    ["FaGithub", "GitHub Actions", "Token OIDC aceptado solo para la rama main; publica en las VM"],
    ["FaUserShield", "Operador", "Key Vault Secrets Officer para cargar los secretos"],
    ["FaDatabase", "MySQL", "4 usuarios: aplicación, despliegue, reportes y respaldo"],
  ];
  for (let i = 0; i < ids.length; i++) {
    const [ico, t, d] = ids[i];
    await tarjeta(pres, s, 0.6 + (i % 2) * 6.15, 1.55 + Math.floor(i / 2) * 2.6, 5.95, 2.35, { titulo: t, texto: d, icono: ico });
  }

  pres.addSection({ title: "Sede y capacidad" });
  s = contenido(pres, "Sede y capacidad", "La sede: del ESXi a Azure", "FC",
    "Para cuando la clínica lleve la sede a su local, el diseño físico es un rack de 12 U, un firewall con dos puertos, un conmutador con VLAN, un host con RAID 1 y una UPS. En el proyecto, cada port group es una subred de Azure.");
  tabla(pres, s, [
    ["Diseño en ESXi", "En Azure"],
    ["vSwitch con NIC teaming", "Red virtual vnet-clinica"],
    ["Port group VLAN 10, 30, 50, 60", "snet-mgmt, snet-personal, snet-servidores, snet-dmz"],
    ["VM firewall (WAN + troncal)", "vm-fw con 2 NIC"],
    ["VM SIEM y respaldo (4 GB)", "vm-wazuh B2als_v2, 64 GB"],
    ["VM honeypot y recepción", "vm-honeypot y vm-recepcion"],
  ], { y: 1.55, w: 7.6, anchos: [3.4, 4.2], tam: 14 });
  await tarjeta(pres, s, 8.5, 1.55, 4.2, 4.6, { titulo: "Equipo físico", icono: "FaServer", tono: "oscuro",
    texto: "Rack de pared 12 U\nConmutador 802.1Q de 24 puertos\nHost de 8 núcleos y 32 GB, RAID 1\nUPS de 1 500 VA\nFibra con IP pública" });

  s = contenido(pres, "Sede y capacidad", "Lo que viaja por la VPN", "FC",
    "El personal entra al panel, los agentes mandan registros al SIEM y la aplicación manda una copia de la base cada hora. El DNS de la sede responde el dominio con la IP privada del proxy.");
  tabla(pres, s, [
    ["Flujo", "De → a", "Puerto"],
    ["Panel del personal", "Recepción → proxy", "443"],
    ["Registros al SIEM", "VM de producción → Wazuh", "1514, 1515"],
    ["Copia horaria", "vm-app → Wazuh (usuario respaldo)", "22"],
    ["Administración", "Jump host de la sede → proxy y app", "22"],
    ["DNS interno", "Recepción → Unbound", "53"],
  ], { y: 1.55, anchos: [3.4, 6.2, 2.5], tam: 15 });

  s = contenido(pres, "Sede y capacidad", "Uso medido frente a capacidad", "FC",
    "Medido el 19/11 con datos de prueba. Lo que más ocupa es Wazuh: 2,7 de 3,9 GB de RAM y 41 de 61 GB de disco. La base de datos ocupa 6,6 MB de 32 GB.");
  s.addChart(pres.charts.BAR, [
    { name: "Uso (%)", labels: ["RAM vm-app", "RAM firewall prod.", "RAM vm-wazuh", "Disco vm-wazuh", "Disco vm-app"], values: [34, 48, 69, 66, 17] },
  ], opcionesGrafico({ x: 0.6, y: 1.5, w: 7.8, h: 5.2, barDir: "bar", chartColors: [AZUL], showLegend: false, valAxisMaxVal: 100, valAxisMinVal: 0,
    showTitle: true, title: "Uso medido (%)", dataLabelPosition: "outEnd" }));
  await tarjeta(pres, s, 8.8, 1.55, 3.9, 4.9, { titulo: "Cuota y costo", icono: "FaCoins",
    texto: "6 de 6 vCPU usadas en cada región.\n\n1,5 a 2 USD por día con todo encendido.\n\n10 a 25 USD en total apagando entre sesiones." });

  pres.addSection({ title: "Continuidad" });
  s = contenido(pres, "Continuidad", "Tres copias independientes", "JY",
    "Si falla una, quedan dos. MySQL guarda 7 días con restauración a un punto; Blob la copia diaria con versiones; y la sede una copia cada hora por la VPN.");
  await imagen(s, "continuidad.png", { x: 0.6, y: 1.35, w: 12.1, h: 3.4 }, "Tres niveles de copia");
  tabla(pres, s, [
    ["Amenaza", "RPO", "RTO"],
    ["Versión defectuosa de la app", "0", "< 5 min"],
    ["Pérdida de vm-app", "0", "< 2 h"],
    ["Pérdida de la base o de la región", "1 h", "< 2 h"],
  ], { y: 4.9, anchos: [6.5, 2.6, 3.0], tam: 14 });

  cierre(pres, "En resumen", [
    ["Nube privada por defecto", "Base de datos, secretos y copias sin acceso público."],
    ["Sin claves guardadas", "Identidades administradas y OIDC en lugar de contraseñas."],
    ["Continuidad medible", "RPO de 1 h y RTO de 2 h, a comprobar en el Semestre 2."],
  ], "Resumen del diseño y paso a preguntas.");
  return pres;
}

// ======================================================================================================
// 3. S2-E1 SGSI (CE0321–CE0324)
// ======================================================================================================
async function deckSgsi() {
  const pres = nuevaPresentacion("Planificación e Implementación del SGSI");
  const C = pres.SchemeColor;
  pres.addSection({ title: "Portada" });
  portada(pres, "Planificación e Implementación del SGSI", "Semestre 2 · Entregable 1 · CE0321 a CE0324",
    "El sistema de gestión de seguridad de la información de la clínica, según ISO/IEC 27001:2022. Joselyn presenta contexto, activos y auditoría; Jhoel riesgos y controles; Fran accesos, incidentes y ética.");
  await agenda(pres, [
    ["JY", ["Contexto, alcance y PHVA", "Inventario de activos", "Auditoría interna y métricas"]],
    ["JA", ["Evaluación de riesgos", "Tratamiento", "Declaración de Aplicabilidad"]],
    ["FC", ["Políticas, accesos y MFA", "Incidentes y continuidad", "Ética de la seguridad"]],
  ], "Reparto de temas del SGSI.");

  pres.addSection({ title: "Planificar" });
  let s = contenido(pres, "Planificar", "Qué protege el SGSI", "JY",
    "El alcance cubre la web, el panel, la API, la infraestructura en Azure y la sede. Quedan fuera los equipos de rayos X, el Wi-Fi de pacientes y la seguridad física de Azure, que se apoya en sus certificaciones.");
  cifra(pres, s, 0.6, 1.6, 3.8, "26", "activos inventariados con valor C-I-D");
  cifra(pres, s, 4.75, 1.6, 3.8, "16", "riesgos evaluados con ISO/IEC 27005");
  cifra(pres, s, 8.9, 1.6, 3.8, "93", "controles del anexo A declarados en la SoA");
  await imagen(s, "phva.png", { x: 0.6, y: 4.0, w: 12.1, h: 2.7 }, "Ciclo PHVA del SGSI");

  s = contenido(pres, "Planificar", "Inventario y clasificación", "JY",
    "Cada activo se valora de 1 a 3 en confidencialidad, integridad y disponibilidad. Las historias clínicas, las radiografías, las copias y los secretos tienen el valor más alto.");
  s.addChart(pres.charts.BAR, [
    { name: "Activos", labels: ["Información", "Infraestructura", "Identidad", "Servicio", "Software", "Persona"], values: [9, 8, 3, 3, 2, 1] },
  ], opcionesGrafico({ x: 0.6, y: 1.5, w: 6.4, h: 5.2, barDir: "col", chartColors: [AZUL], showLegend: false, showTitle: true, title: "Activos por tipo", dataLabelPosition: "outEnd" }));
  tabla(pres, s, [
    ["Clase", "Ejemplos"],
    ["Secreto", "Claves de Key Vault, cuentas de Azure y GitHub"],
    ["Confidencial", "Historias clínicas, datos personales, copias"],
    ["Interno", "Agenda, caja, auditoría, servidores"],
    ["Público", "Web y dominio"],
  ], { x: 7.3, y: 1.6, w: 5.4, anchos: [1.7, 3.7], tam: 14 });

  pres.addSection({ title: "Riesgos y controles" });
  s = contenido(pres, "Riesgos y controles", "De 11 riesgos altos a ninguno", "JA",
    "Riesgo = probabilidad por impacto, de 1 a 25. Antes de los controles había 11 riesgos altos o críticos; después ninguno pasa de 9, el nivel que acepta la dirección. El mayor que queda es R08: una sola VM por servicio.");
  const riesgos = [["R01", 20, 8], ["R02", 16, 8], ["R03", 15, 5], ["R04", 15, 4], ["R05", 20, 8], ["R06", 15, 5], ["R07", 12, 4], ["R08", 9, 9],
    ["R09", 9, 4], ["R10", 12, 6], ["R11", 12, 6], ["R12", 8, 3], ["R13", 12, 6], ["R14", 6, 2], ["R15", 10, 5], ["R16", 8, 4]];
  s.addChart(pres.charts.BAR, [
    { name: "Inherente", labels: riesgos.map((r) => r[0]), values: riesgos.map((r) => r[1]) },
    { name: "Residual", labels: riesgos.map((r) => r[0]), values: riesgos.map((r) => r[2]) },
  ], opcionesGrafico({ x: 0.6, y: 1.45, w: 12.1, h: 5.35, barDir: "col", chartColors: ["8EA2C6", AZUL], showLegend: true, legendPos: "t", legendFontSize: 12,
    showTitle: true, title: "Riesgo inherente y residual (P × I)", valAxisMaxVal: 25, dataLabelPosition: "outEnd", dataLabelFontSize: 10, barGapWidthPct: 60 }));

  s = contenido(pres, "Riesgos y controles", "Los riesgos más altos y su tratamiento", "JA",
    "Los dos riesgos críticos eran el robo de contraseñas del panel y la filtración de secretos. El MFA obligatorio y Key Vault con OIDC los bajaron a 8.");
  tabla(pres, s, [
    ["Riesgo", "Antes", "Control principal", "Después"],
    ["R01 Robo de contraseña del panel", "20", "MFA obligatorio, límite de intentos", "8"],
    ["R05 Filtración de secretos", "20", "Key Vault, OIDC, ningún secreto en el repo", "8"],
    ["R02 Ataque a la web", "16", "WAF, consultas preparadas, 1 105 pruebas", "8"],
    ["R03 Acceso directo a MySQL", "15", "Sin acceso público, TLS, 4 usuarios mínimos", "5"],
    ["R04 Pérdida de datos", "15", "Tres copias, restauración probada", "4"],
    ["R06 SSH no autorizado", "15", "Solo llaves, jump host, IP de administración", "5"],
  ], { y: 1.55, anchos: [4.0, 1.1, 5.8, 1.2], tam: 14 });

  s = contenido(pres, "Riesgos y controles", "Declaración de Aplicabilidad", "JA",
    "Los 93 controles de ISO/IEC 27001:2022: 63 implementados, 23 parciales, 5 planificados y 2 que no aplican (áreas seguras propias y desarrollo externalizado).");
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

  pres.addSection({ title: "Hacer" });
  s = contenido(pres, "Hacer", "Accesos: el panel pide dos factores", "FC",
    "El panel se abre desde internet para que el doctor o la recepción trabajen fuera de la clínica. Por eso cada ingreso pide la contraseña propia y el código de la app autenticadora. Si se pierde el celular, hay códigos de recuperación.");
  const acc = [
    ["FaMobileScreen", "MFA obligatorio", "Contraseña y código de la app; quien no lo tiene solo ve la pantalla para activarlo"],
    ["FaKey", "Contraseña propia", "La temporal del administrador se cambia en el primer ingreso"],
    ["FaUsersGear", "35 políticas por rol", "Lo clínico solo para quien atiende"],
    ["FaClockRotateLeft", "Auditoría", "Cambios, impresiones y activación del MFA quedan registrados"],
  ];
  for (let i = 0; i < acc.length; i++) {
    const [ico, t, d] = acc[i];
    await tarjeta(pres, s, 0.6 + (i % 2) * 6.15, 1.55 + Math.floor(i / 2) * 2.6, 5.95, 2.35, { titulo: t, texto: d, icono: ico });
  }

  s = contenido(pres, "Hacer", "Incidentes: registrar y aprender", "FC",
    "Procedimiento: detectar, evaluar, contener, recuperar, notificar y aprender. Se registraron tres incidentes reales del despliegue; dos credenciales expuestas siguen abiertas hasta rotarlas.");
  tabla(pres, s, [
    ["ID", "Qué pasó", "Acción", "Estado"],
    ["INC-01", "Clave del tablero de Wazuh mostrada en una consola", "Rotarla y guardarla solo en Key Vault", "Abierto"],
    ["INC-02", "Contraseña del registrador visible en una captura", "Cambiarla y activar MFA", "Abierto"],
    ["INC-03", "El DNAT del honeypot capturaba el SSH de la VPN", "DNAT solo para la IP de la WAN", "Cerrado"],
  ], { y: 1.55, h: 2.5, anchos: [1.3, 4.9, 4.4, 1.5], tam: 13 });
  cifra(pres, s, 0.6, 4.4, 3.8, "12 s", "restaurar la base desde Blob (simulacro del 19/11)");
  cifra(pres, s, 4.75, 4.4, 3.8, "1,4 s", "la VPN volvió sola tras un corte");
  cifra(pres, s, 8.9, 4.4, 3.8, "1 h", "RPO con la copia horaria a la sede");

  s = contenido(pres, "Hacer", "Ética de la seguridad (ACM)", "FC",
    "Seguimos el Código de Ética de la ACM: privacidad, confidencialidad, acceso solo autorizado y sistemas robustos. La declaración del equipo va firmada en el informe.");
  const etica = [
    ["FaUserLock", "Confidencialidad", "Ningún dato real, credencial ni llave en informes o capturas"],
    ["FaFlask", "Pruebas éticas", "Solo sobre infraestructura propia y con datos generados"],
    ["FaFileSignature", "Consentimiento", "El paciente firma su consentimiento informado en el sistema"],
    ["FaBullhorn", "Divulgación responsable", "Fallas de terceros se reportan en privado antes de publicarlas"],
  ];
  for (let i = 0; i < etica.length; i++) {
    const [ico, t, d] = etica[i];
    await tarjeta(pres, s, 0.6 + (i % 2) * 6.15, 1.55 + Math.floor(i / 2) * 2.6, 5.95, 2.35, { titulo: t, texto: d, icono: ico });
  }

  pres.addSection({ title: "Verificar y actuar" });
  s = contenido(pres, "Verificar y actuar", "Auditoría interna: 6 no conformidades", "JY",
    "La auditoría del 19/11 comparó la SoA con la configuración real. La mayor es la de las credenciales expuestas. Todas tienen responsable y plazo.");
  tabla(pres, s, [
    ["ID", "Hallazgo", "Tipo", "Plazo"],
    ["NC-04", "Dos credenciales expuestas sin rotar", "Mayor", "20/11"],
    ["NC-01", "La rama main no exige revisión ni CI", "Menor", "26/11"],
    ["NC-03", "Key Vault con red pública y sin protección de purga", "Menor", "26/11"],
    ["NC-05", "Vigilancia de /up desactivada", "Menor", "20/11"],
    ["NC-02", "WAF en modo detección", "Menor", "03/12"],
    ["NC-06", "Suricata solo detecta, no bloquea", "Observación", "10/12"],
  ], { y: 1.55, anchos: [1.3, 7.3, 1.9, 1.6], tam: 15 });

  cierre(pres, "Qué sigue", [
    ["Cerrar la no conformidad mayor", "Rotar las dos credenciales expuestas esta semana."],
    ["Firmar", "Política, riesgo residual y acta por el Dr. Vega; ética por el equipo."],
    ["Revisar en marzo de 2027", "Métricas, auditoría y nuevas amenazas."],
  ], "Cerramos con los próximos pasos del ciclo PHVA y abrimos a preguntas.");
  return pres;
}

// ======================================================================================================
// 4. S2-E2 Implementación, Testing y Monitoreo de Red (CE0312–CE0313)
// ======================================================================================================
async function deckRedImplementada() {
  const pres = nuevaPresentacion("Implementación, Testing y Monitoreo de Red");
  const C = pres.SchemeColor;
  pres.addSection({ title: "Portada" });
  portada(pres, "Implementación, Testing y Monitoreo de Red", "Semestre 2 · Entregable 2 · CE0312 y CE0313",
    "La red diseñada en el Semestre 1, funcionando en Azure. Jhoel presenta la implementación y la VPN, Fran las pruebas, y Joselyn la detección y el monitoreo.");
  await agenda(pres, [
    ["JA", ["Resultados en cifras", "Del diseño a lo implementado", "VPN funcionando"]],
    ["FC", ["Segmentación y pruebas", "DMZ y WAF"]],
    ["JY", ["Suricata y honeypot", "Wazuh y registros", "Incidencias y lecciones"]],
  ], "Reparto del segundo entregable del Semestre 2.");

  pres.addSection({ title: "Implementación" });
  let s = contenido(pres, "Implementación", "La red funciona y está probada", "JA",
    "Cifras del 19/11/2026: 24 pruebas con resultado obtenido, 23 cumplen; la VPN se recuperó sola en 1,4 segundos; y Wazuh registró 3 442 alertas en el día, la mayoría escaneos automáticos de internet.");
  cifra(pres, s, 0.6, 1.8, 2.9, "23/24", "pruebas cumplen");
  cifra(pres, s, 3.7, 1.8, 2.9, "1,4 s", "la VPN volvió sola");
  cifra(pres, s, 6.8, 1.8, 2.9, "6", "agentes de Wazuh activos");
  cifra(pres, s, 9.9, 1.8, 2.9, "3 442", "alertas en el SIEM en un día");
  await tarjeta(pres, s, 0.6, 4.3, 12.1, 2.2, { titulo: "Un solo comando", icono: "FaTerminal",
    texto: "bash infra/desplegar.sh todo crea producción, la sede, la VPN y los usuarios de la base en unos 40 minutos, sin pasos manuales. Las reglas se cambian por pull request y se aplican con desplegar.sh reglas." });

  s = contenido(pres, "Implementación", "Del diseño a lo implementado", "JA",
    "Casi todo se implementó tal cual. Dos cambios: el firewall pasó de OPNsense a Linux configurado por código, y el panel se abrió a internet con MFA porque el personal necesita entrar fuera de la clínica.");
  tabla(pres, s, [
    ["Diseño (S1-E1)", "Implementado", "Cambio"],
    ["Firewall OPNsense", "Linux: nftables, strongSwan, Suricata, Unbound", "Mismas funciones, por código"],
    ["Segmentos y ruteo", "Subredes y rt-via-firewall", "Ninguno"],
    ["VPN IPsec", "strongSwan IKEv2, AES-256-GCM", "Ninguno"],
    ["DMZ con WAF", "nginx + ModSecurity + CRS", "Se sumó límite de peticiones"],
    ["Panel solo desde la sede", "Panel desde internet con MFA", "Aprobado el 19/11"],
    ["Honeypot", "OpenCanary 22 y 3306", "Se alterna con recepción"],
  ], { y: 1.55, anchos: [3.2, 5.1, 3.8], tam: 14 });

  s = contenido(pres, "Implementación", "VPN establecida y con tráfico real", "JA",
    "swanctl --list-sas muestra el túnel ESTABLISHED en los dos extremos, con AES-GCM de 256 bits y NAT-T. Se cortó a propósito a las 11:26:34 y a las 11:26:35 ya respondía el ping entre las LAN.");
  s.addText([
    { text: "vega: #5, ESTABLISHED, IKEv2", options: { breakLine: true } },
    { text: "  local  'IP_PUBLICA_SEDE' @ 10.10.0.4[4500]", options: { breakLine: true } },
    { text: "  remote 'IP_PUBLICA_PROD' @ IP_PUBLICA_PROD[4500]", options: { breakLine: true } },
    { text: "  redes: INSTALLED, TUNNEL-in-UDP, ESP:AES_GCM_16-256", options: { breakLine: true } },
    { text: "    in  4 020 012 bytes · out 317 158 bytes", options: { breakLine: true } },
    { text: "    local 10.10.0.0/16 · remote 10.20.0.0/16" },
  ], { x: 0.6, y: 1.6, w: 7.6, h: 3.4, fontFace: "Courier New", fontSize: 14, color: "FFFFFF", fill: { color: "1A1A1A" }, margin: 14, valign: "middle", isTextBox: true, objectName: "Salida swanctl" });
  cifra(pres, s, 8.7, 1.6, 4.0, "1,4 s", "recuperación tras swanctl --terminate");
  cifra(pres, s, 8.7, 3.6, 4.0, "4500", "UDP: NAT-T, como con un proveedor real");
  s.addText("Desde la recepción, el dominio resuelve a 10.20.3.4 (DNS de la sede) y el panel responde 200 por la VPN.",
    { x: 0.6, y: 5.4, w: 12.1, h: 0.9, fontSize: 15, color: C.text1, margin: 0, isTextBox: true });

  pres.addSection({ title: "Pruebas" });
  s = contenido(pres, "Pruebas", "24 pruebas, esperado contra obtenido", "FC",
    "Diez positivas y diez negativas de conectividad y segmentación, una de recuperación y tres de detección. Solo queda pendiente la del honeypot, porque en esta ronda la VM de laboratorio activa era la de recepción.");
  s.addChart(pres.charts.BAR, [
    { name: "Pruebas", labels: ["Positivas", "Negativas", "Recuperación", "Detección (1 pendiente)"], values: [10, 10, 1, 3] },
  ], opcionesGrafico({ x: 0.6, y: 1.45, w: 6.6, h: 5.3, barDir: "col", chartColors: [AZUL], showLegend: false,
    showTitle: true, title: "Pruebas por tipo", dataLabelPosition: "outEnd" }));
  tabla(pres, s, [
    ["Desde → hacia", "Obtenido"],
    ["Internet → prod. 22, 3306", "Filtrados"],
    ["Recepción → Wazuh :22", "Bloqueado"],
    ["Recepción → app :80", "Bloqueado"],
    ["Proxy → app :22", "Bloqueado"],
    ["App → MySQL", "TLS 1.3"],
    ["App → sede :22 (copia)", "Abierto"],
  ], { x: 7.5, y: 1.6, w: 5.2, anchos: [3.3, 1.9], tam: 14 });

  s = contenido(pres, "Pruebas", "Cada bloqueo deja rastro", "FC",
    "Los firewalls registran cada conexión que bloquean con un prefijo que Wazuh cuenta. Arriba, la recepción intentó llegar directo a la app; abajo, la app intentó SSH a la recepción. Las dos fueron bloqueadas.");
  s.addText([
    { text: "fw-reenvio-bloqueo  SRC=10.10.30.10  DST=10.20.4.4  DPT=80  SYN", options: { breakLine: true } },
    { text: " ", options: { breakLine: true } },
    { text: "fw-reenvio-bloqueo  SRC=10.20.4.4  DST=10.10.30.10  DPT=22  SYN" },
  ], { x: 0.6, y: 1.6, w: 12.1, h: 1.8, fontFace: "Courier New", fontSize: 15, color: "FFFFFF", fill: { color: "1A1A1A" }, margin: 14, valign: "middle", isTextBox: true, objectName: "Registro de bloqueos" });
  cifra(pres, s, 0.6, 3.8, 3.8, "14", "bloqueos registrados en producción en el día");
  cifra(pres, s, 4.75, 3.8, 3.8, "6", "fw-personal-bloqueo: recepción hacia servidores");
  cifra(pres, s, 8.9, 3.8, 3.8, "80", "respuestas 429 en 250 peticiones seguidas al panel");

  s = contenido(pres, "Pruebas", "DMZ: TLS, cabeceras y WAF", "FC",
    "El proxy termina el HTTPS con Let's Encrypt y renovación automática, añade cabeceras de seguridad, limita las peticiones al panel y pasa todo por ModSecurity. El WAF sigue en detección hasta revisar sus 615 eventos.");
  const dmz = [
    ["FaLock", "HTTPS", "Let's Encrypt para la raíz y www, vence el 17/02/2027"],
    ["FaListCheck", "Cabeceras", "HSTS, X-Frame-Options, nosniff, Referrer-Policy"],
    ["FaGaugeHigh", "Límite por IP", "10 por segundo con ráfaga de 40: responde 429"],
    ["FaShieldHalved", "ModSecurity + CRS", "615 eventos en detección; /.env responde 403"],
  ];
  for (let i = 0; i < dmz.length; i++) {
    const [ico, t, d] = dmz[i];
    await tarjeta(pres, s, 0.6 + (i % 2) * 6.15, 1.55 + Math.floor(i / 2) * 2.6, 5.95, 2.35, { titulo: t, texto: d, icono: ico });
  }

  pres.addSection({ title: "Detección y monitoreo" });
  s = contenido(pres, "Detección y monitoreo", "Suricata ve los escaneos de internet", "JY",
    "La mayoría de alertas de Suricata son ruido de stream que se puede filtrar. Las importantes son escaneos reales: bots buscando archivos .env, GraphQL y MySQL expuesto. Ninguno llegó a un servicio.");
  s.addChart(pres.charts.BAR, [
    { name: "Alertas", labels: ["Archivo .env (prod.)", "GraphQL (prod.)", "MySQL 3306 (sede)", "DNS nulo (sede)"], values: [116, 15, 13, 12] },
  ], opcionesGrafico({ x: 0.6, y: 1.45, w: 7.6, h: 5.3, barDir: "bar", chartColors: [AZUL], showLegend: false, showTitle: true, title: "Escaneos detectados el 19/11", dataLabelPosition: "outEnd" }));
  await tarjeta(pres, s, 8.6, 1.55, 4.1, 4.9, { titulo: "Honeypot", icono: "FaBug", tono: "oscuro",
    texto: "OpenCanary con SSH y MySQL falsos en la DMZ de la sede.\n\nPendiente: activarlo con desplegar.sh sede honeypot y repetir la prueba." });

  s = contenido(pres, "Detección y monitoreo", "Wazuh centraliza los registros", "JY",
    "Seis agentes activos, firewalls incluidos. El proxy y la app generan más alertas porque reciben el tráfico de internet. Las 82 alertas de nivel 10 son ráfagas de errores 400 desde una misma IP: escaneos automáticos.");
  s.addChart(pres.charts.BAR, [
    { name: "Alertas", labels: ["vm-proxy", "vm-app", "vm-fw-prod", "vm-fw-sede", "vm-wazuh", "vm-recepcion"], values: [1194, 827, 469, 354, 302, 296] },
  ], opcionesGrafico({ x: 0.6, y: 1.45, w: 7.6, h: 5.3, barDir: "bar", chartColors: [AZUL], showLegend: false, showTitle: true, title: "Alertas por agente el 19/11", dataLabelPosition: "outEnd" }));
  tabla(pres, s, [
    ["Regla", "Nivel", "Alertas"],
    ["Web server 400", "5", "1 320"],
    ["Archivo .env (Suricata)", "3", "115"],
    ["Varios 400 de una IP", "10", "82"],
    ["URL sospechosa", "6", "28"],
  ], { x: 8.5, y: 1.6, w: 4.2, anchos: [2.3, 0.8, 1.1], tam: 13 });

  s = contenido(pres, "Detección y monitoreo", "Lo que aprendimos en el despliegue", "JY",
    "Cinco problemas reales y cómo se resolvieron. El más instructivo: los NSG de la LAN del firewall ven el tráfico reenviado con su origen original y lo descartaban.");
  tabla(pres, s, [
    ["Incidencia", "Solución"],
    ["Las subredes no salían a internet", "Dos reglas en el NSG de la LAN del firewall"],
    ["El SSH de la VPN terminaba en el honeypot", "DNAT solo para la IP de la WAN"],
    ["El agente de Wazuh borró el servidor", "Versión fija 4.12.0 y no instalarlo en vm-wazuh"],
    ["La web cargaba recursos con http://", "REMOTE_ADDR real hacia PHP"],
    ["Puertos de configuración inicial abiertos", "Quitados del firewall y del NSG"],
  ], { y: 1.55, anchos: [5.6, 6.5], tam: 15 });

  cierre(pres, "Qué sigue", [
    ["Honeypot", "Activarlo y completar la prueba PR-24."],
    ["Bloquear, no solo detectar", "Suricata como IPS y el WAF en modo On."],
    ["Filtrar el ruido", "Umbrales para las alertas de stream."],
  ], "Próximos pasos de la red y preguntas.");
  return pres;
}

// ======================================================================================================
// 5. S2-E3 Implementación, Gestión y Control de Centro de Datos (CE0332–CE0333)
// ======================================================================================================
async function deckCentroImplementado() {
  const pres = nuevaPresentacion("Implementación, Gestión y Control de Centro de Datos");
  const C = pres.SchemeColor;
  pres.addSection({ title: "Portada" });
  portada(pres, "Implementación, Gestión y Control de Centro de Datos", "Semestre 2 · Entregable 3 · CE0332 y CE0333",
    "El centro de datos en funcionamiento. Jhoel presenta lo implementado y la capacidad, Fran las identidades y los simulacros de recuperación, y Joselyn el monitoreo y el control de cambios.");
  await agenda(pres, [
    ["JA", ["Lo implementado", "Inventario", "Capacidad medida"]],
    ["FC", ["Identidades y accesos", "Simulacro de restauración", "RTO y RPO medidos"]],
    ["JY", ["Monitoreo", "Control de cambios", "Limitaciones"]],
  ], "Reparto del tercer entregable del Semestre 2.");

  pres.addSection({ title: "Implementación" });
  let s = contenido(pres, "Implementación", "Centro de datos en funcionamiento", "JA",
    "43 recursos de Azure creados desde el repositorio. Tres simulacros el 19/11: restaurar la base en 12 segundos, recuperar la VPN en 1,4 y publicar la app en 65.");
  cifra(pres, s, 0.6, 1.8, 2.9, "43", "recursos de Azure creados por código");
  cifra(pres, s, 3.7, 1.8, 2.9, "12 s", "restaurar la base desde Blob");
  cifra(pres, s, 6.8, 1.8, 2.9, "1,4 s", "recuperar la VPN tras un corte");
  cifra(pres, s, 9.9, 1.8, 2.9, "65 s", "publicar una versión de la app");
  await tarjeta(pres, s, 0.6, 4.3, 12.1, 2.2, { titulo: "Todo desde el repositorio", icono: "FaCodeBranch",
    texto: "27 recursos en producción (Chile Central) y 16 en la sede (North Central US), definidos en Bicep y cloud-init. Si se pierde uno, se vuelve a crear con infra/desplegar.sh." });

  s = contenido(pres, "Implementación", "Inventario final", "JA",
    "27 recursos en producción (Chile Central) y 16 en la sede (North Central US). Todos están en Bicep: si se borran, se vuelven a crear con un comando.");
  tabla(pres, s, [
    ["Tipo", "Producción (rg-vega-prod)", "Sede (rg-vega-clinica)"],
    ["Máquinas virtuales", "vm-fw, vm-proxy, vm-app", "vm-fw, vm-wazuh, vm-recepcion"],
    ["Red", "vnet-vega, 4 NSG, rutas, IP pública", "vnet-clinica, 3 NSG, rutas, IP pública"],
    ["Datos", "MySQL Flexible, Blob, Key Vault", "Copias horarias en vm-wazuh"],
    ["Monitoreo", "Log Analytics, 2 alertas, grupo de acción", "Wazuh"],
    ["Identidad", "id-github-despliegue (OIDC)", "—"],
  ], { y: 1.55, anchos: [2.6, 5.0, 4.5], tam: 15 });

  s = contenido(pres, "Implementación", "Capacidad medida", "JA",
    "La RAM de las VM pequeñas va bien; los firewalls usan swap por Suricata. Lo que hay que vigilar es el disco de Wazuh: 66 % lleno por los índices de alertas.");
  s.addChart(pres.charts.BAR, [
    { name: "RAM (%)", labels: ["vm-fw prod.", "vm-proxy", "vm-app", "vm-fw sede", "vm-wazuh"], values: [48, 37, 34, 37, 69] },
    { name: "Disco (%)", labels: ["vm-fw prod.", "vm-proxy", "vm-app", "vm-fw sede", "vm-wazuh"], values: [16, 16, 17, 16, 66] },
  ], opcionesGrafico({ x: 0.6, y: 1.45, w: 8.0, h: 5.35, barDir: "col", chartColors: [AZUL, AMARILLO], showLegend: true, legendPos: "t", legendFontSize: 12,
    valAxisMaxVal: 100, showTitle: true, title: "Uso de RAM y disco el 19/11 (%)", dataLabelPosition: "outEnd" }));
  await tarjeta(pres, s, 9.0, 1.55, 3.7, 4.9, { titulo: "Acción", icono: "FaHardDrive", tono: "oscuro",
    texto: "Retención de índices de 30 días en Wazuh o ampliar su disco en línea.\n\nSi Suricata pasa a IPS, firewalls de 4 GiB." });

  pres.addSection({ title: "Accesos y continuidad" });
  s = contenido(pres, "Accesos y continuidad", "Identidades y accesos", "FC",
    "Ninguna clave de la nube en archivos. La propia auditoría lo comprobó: la cuenta del operador no puede leer los datos de Blob, solo la VM con su identidad.");
  const ac = [
    ["FaMobileScreen", "Panel", "Contraseña propia y MFA obligatorio; 8 pruebas automáticas"],
    ["FaServer", "vm-app", "Identidad administrada para Key Vault y Blob"],
    ["FaGithub", "GitHub", "OIDC solo desde main; Virtual Machine Contributor"],
    ["FaKey", "Servidores", "Solo llaves SSH, jump host o run-command"],
  ];
  for (let i = 0; i < ac.length; i++) {
    const [ico, t, d] = ac[i];
    await tarjeta(pres, s, 0.6 + (i % 2) * 6.15, 1.55 + Math.floor(i / 2) * 2.6, 5.95, 2.35, { titulo: t, texto: d, icono: ico });
  }

  s = contenido(pres, "Accesos y continuidad", "Simulacro: restaurar desde Blob", "FC",
    "La copia de Blob se restauró en una base aparte, sin tocar producción. Los totales coinciden con producción en las cinco tablas comparadas, y se restauraron las 70 tablas. Al terminar, la base del simulacro se borró.");
  s.addText([
    { text: "copia elegida: respaldo_2026-11-19_065405.sql.gz", options: { breakLine: true } },
    { text: "inicio 11:24:13 · fin 11:24:25 · duracion_s: 12", options: { breakLine: true } },
    { text: "patients      produccion=268  restaurada=268", options: { breakLine: true } },
    { text: "appointments  produccion=874  restaurada=874", options: { breakLine: true } },
    { text: "payments      produccion=733  restaurada=733", options: { breakLine: true } },
    { text: "tablas restauradas: 70 · base de simulacro borrada" },
  ], { x: 0.6, y: 1.6, w: 8.0, h: 3.6, fontFace: "Courier New", fontSize: 14, color: "FFFFFF", fill: { color: "1A1A1A" }, margin: 14, valign: "middle", isTextBox: true, objectName: "Salida del simulacro" });
  cifra(pres, s, 9.0, 1.6, 3.7, "12 s", "de la descarga a la base restaurada");
  cifra(pres, s, 9.0, 3.6, 3.7, "70", "tablas restauradas, mismos totales");
  s.addText("Además, la sede recibe una copia de la base cada hora: 8:15, 9:15 y 10:15 el 19/11.",
    { x: 0.6, y: 5.6, w: 12.1, h: 0.7, fontSize: 15, color: C.text1, margin: 0, isTextBox: true });

  s = contenido(pres, "Accesos y continuidad", "RTO y RPO: planeado contra medido", "FC",
    "Cuatro de cinco escenarios se midieron y cumplen. Falta el más caro: perder vm-app entera. No se hizo sobre producción y se programa con la clínica.");
  tabla(pres, s, [
    ["Escenario", "RTO planeado", "Medido", "Cumple"],
    ["Restaurar la base desde Blob", "1 h", "12 s", "Sí"],
    ["Copia horaria a la sede (RPO)", "1 h", "1 h", "Sí"],
    ["Corte de la VPN", "< 1 min", "1,4 s", "Sí"],
    ["Publicar o volver una versión", "< 5 min", "65 s", "Sí"],
    ["Perder vm-app entera", "2 h", "No medido", "Pendiente"],
  ], { y: 1.55, anchos: [5.2, 2.4, 2.4, 2.1], tam: 15 });

  pres.addSection({ title: "Control" });
  s = contenido(pres, "Control", "Monitoreo", "JY",
    "Azure Monitor avisa por correo si vm-app se cae o se satura; Wazuh muestra la seguridad; el presupuesto avisa al 50 y 80 %. La vigilancia de /up desde GitHub está lista pero desactivada.");
  const mon = [
    ["FaBell", "Azure Monitor", "VM caída (sev. 1) y CPU > 80 % (sev. 2), por correo"],
    ["FaChartLine", "Wazuh", "6 agentes y 3 442 alertas en el día"],
    ["FaWallet", "Presupuesto", "40 USD con avisos al 50 % y 80 %"],
    ["FaHeartPulse", "Vigilar /up", "Cada 15 min desde GitHub; falta activarlo"],
  ];
  for (let i = 0; i < mon.length; i++) {
    const [ico, t, d] = mon[i];
    await tarjeta(pres, s, 0.6 + (i % 2) * 6.15, 1.55 + Math.floor(i / 2) * 2.6, 5.95, 2.35, { titulo: t, texto: d, icono: ico });
  }

  s = contenido(pres, "Control", "Control de cambios", "JY",
    "Todo cambio entra por pull request con las cuatro comprobaciones de la CI, se fusiona y se publica con un workflow que queda registrado. Siete cambios de infraestructura el 19/11.");
  await imagen(s, "despliegue.png", { x: 0.6, y: 1.35, w: 12.1, h: 2.4 }, "De un cambio a producción");
  tabla(pres, s, [
    ["PR", "Cambio"],
    ["#13", "Despliegue en Azure alineado con el E1"],
    ["#16", "Firewall Linux automático en lugar de OPNsense"],
    ["#18", "HTTPS, Wazuh, copias y SSH cerrado"],
    ["#24", "Panel con MFA obligatorio y acceso desde internet"],
  ], { y: 4.0, anchos: [1.2, 10.9], tam: 14 });

  cierre(pres, "Limitaciones y próximos pasos", [
    ["Una VM por servicio", "Riesgo aceptado por presupuesto; reconstrucción en minutos."],
    ["Simulacro completo", "Medir el RTO de perder vm-app en una ventana acordada."],
    ["Endurecer", "Proteger main, Key Vault privado y vigilancia de /up activa."],
  ], "Limitaciones y lo que falta. Abrimos a preguntas.");
  return pres;
}

// ======================================================================================================
async function main() {
  const decks = [
    ["CE0311-S1-E1-Diseno-de-Red.pptx", deckRed],
    ["CE0331-S1-E2-Diseno-de-Centro-de-Datos-Hibrido.pptx", deckCentroDeDatos],
    ["CE0321-CE0324-S2-E1-SGSI.pptx", deckSgsi],
    ["CE0312-CE0313-S2-E2-Red-Implementada.pptx", deckRedImplementada],
    ["CE0332-CE0333-S2-E3-Centro-de-Datos-Implementado.pptx", deckCentroImplementado],
  ];
  const solo = process.env.SOLO || "";
  const applyTheme = APPLY_THEME ? require(APPLY_THEME).applyTheme : null;
  for (const [archivo, armar] of decks) {
    if (!archivo.includes(solo)) continue;
    const pres = await armar();
    const ruta = path.join(AQUI, archivo);
    await pres.writeFile({ fileName: ruta });
    if (applyTheme) await applyTheme(ruta, THEME);
    console.log(archivo);
  }
}

main().catch((e) => { console.error(e); process.exit(1); });
