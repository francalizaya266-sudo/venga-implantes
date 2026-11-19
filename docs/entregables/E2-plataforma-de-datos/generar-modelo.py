"""Genera, desde el código, el diccionario de datos y los diagramas entidad-relación del E2.

Fuentes (nada se escribe a mano):
  - sql/ddl.sql: tablas, columnas, tipos, nulos, valores por defecto, llaves e índices tal como los dejó MySQL 8;
  - backend/database/migrations: el comentario que antecede a cada tabla y a cada columna;
  - backend/app/Models y backend/app/Enums: los valores permitidos de cada columna con enum (casts del modelo);
  - glosario_columnas.py: el nombre de la columna en castellano, cuando la migración no la comenta.

Uso, desde la raíz del repositorio:  python3 docs/entregables/E2-plataforma-de-datos/generar-modelo.py
Escribe anexos/E2-diccionario.md, diagramas/er-*.mmd y evidencias/resumen-del-modelo.txt.
"""

from __future__ import annotations

import re
import sys
from collections import Counter, defaultdict
from pathlib import Path

AQUI = Path(__file__).resolve().parent
sys.dont_write_bytecode = True  # sin __pycache__ junto a los documentos
sys.path.insert(0, str(AQUI))
from glosario_columnas import GLOSARIO, POR_TABLA  # noqa: E402
RAIZ = AQUI.parents[2]
BACKEND = RAIZ / "backend"

# Cada tabla en un dominio del sistema, en el orden en que se presentan.
DOMINIOS: dict[str, list[str]] = {
    "Pacientes, cuentas y avisos": [
        "accounts", "patients", "remembered_devices", "magic_links", "referrals", "message_templates",
        "whatsapp_messages",
    ],
    "Catálogo, doctores y agenda": [
        "treatments", "doctors", "doctor_treatment", "doctor_schedules", "schedule_blocks", "campaigns",
        "appointments", "appointment_events", "reschedule_requests", "reschedule_request_options",
        "waitlist_entries", "desk_notes",
    ],
    "Atención clínica": [
        "medical_backgrounds", "consultations", "icd10_codes", "consultation_diagnoses", "consultation_treatments",
        "odontogram_conditions", "odontograms", "odontogram_entries", "prescriptions", "prescription_items",
        "consent_templates", "consents", "clinical_files", "lab_orders", "clinical_templates", "text_snippets",
        "text_snippet_usages",
    ],
    "Presupuestos y planes de tratamiento": [
        "treatment_packages", "treatment_package_items", "treatment_plans", "treatment_plan_items",
        "consultation_plan_items", "treatment_plan_signatures", "treatment_plan_installments",
    ],
    "Caja": ["charges", "payments", "daily_closings", "expenses"],
    "Inventario": [
        "suppliers", "supplies", "supply_batches", "purchase_orders", "purchase_order_items", "treatment_supplies",
        "stock_movements",
    ],
    "Web pública": ["faqs", "reviews", "smile_cases"],
    "Acceso, auditoría y sistema": [
        "users", "password_reset_tokens", "sessions", "settings", "audit_logs", "payment_audit_logs",
        "notifications", "cache", "cache_locks", "jobs", "job_batches", "failed_jobs", "migrations",
    ],
}

# Cada diagrama ER lógico: su dirección (la que deja la letra más grande en la hoja) y sus tablas. Los dominios
# grandes van en dos diagramas para que se lean en una hoja; "sistema" no lleva diagrama: casi no tiene relaciones.
DIAGRAMAS: dict[str, tuple[str, list[str]]] = {
    "er-pacientes": ("TB", DOMINIOS["Pacientes, cuentas y avisos"]),
    "er-catalogo": ("LR", ["treatments", "doctors", "doctor_treatment", "doctor_schedules", "schedule_blocks",
                           "campaigns"]),
    "er-citas": ("TB", ["appointments", "appointment_events", "reschedule_requests", "reschedule_request_options",
                        "waitlist_entries", "desk_notes"]),
    "er-atencion": ("TB", ["medical_backgrounds", "consultations", "icd10_codes", "consultation_diagnoses",
                           "consultation_treatments", "odontogram_conditions", "odontograms", "odontogram_entries"]),
    "er-documentos": ("LR", ["prescriptions", "prescription_items", "consent_templates", "consents", "clinical_files",
                             "lab_orders", "clinical_templates", "text_snippets", "text_snippet_usages"]),
    "er-planes": ("LR", DOMINIOS["Presupuestos y planes de tratamiento"]),
    "er-caja": ("LR", DOMINIOS["Caja"]),
    "er-inventario": ("LR", DOMINIOS["Inventario"]),
    "er-web": ("LR", DOMINIOS["Web pública"]),
}

# El núcleo del modelo para la vista conceptual: las entidades por las que pasa el trabajo de todos los días.
NUCLEO = [
    "accounts", "patients", "doctors", "treatments", "appointments", "consultations", "treatment_plans",
    "treatment_plan_items", "charges", "payments",
]

# Métodos de Blueprint que reciben un nombre de columna pero no crean una.
NO_SON_COLUMNAS = {
    "index", "unique", "primary", "foreign", "rawIndex", "fullText", "spatialIndex", "dropColumn", "dropIndex",
    "dropUnique", "dropForeign", "dropConstrainedForeignId", "dropPrimary", "renameColumn", "renameIndex",
    "foreignIdFor",
}
AL_BORRAR = {"RESTRICT": "restringe", "SET NULL": "pone NULL", "CASCADE": "en cascada", "NO ACTION": "restringe"}
LARAVEL = {
    "id": "Identificador.",
    "created_at": "Cuándo se creó (Laravel).",
    "updated_at": "Cuándo se modificó por última vez (Laravel).",
    "deleted_at": "Borrado lógico (SoftDeletes): la fila queda y se oculta.",
    "remember_token": "Sesión recordada del panel (Laravel).",
}


class Tabla:
    def __init__(self, nombre: str) -> None:
        self.nombre = nombre
        self.columnas: list[dict[str, str | bool]] = []
        self.primaria: list[str] = []
        self.unicas: list[list[str]] = []
        self.indices: list[tuple[str, list[str]]] = []
        self.foraneas: list[dict[str, str]] = []


def leer_ddl(ruta: Path) -> dict[str, Tabla]:
    tablas: dict[str, Tabla] = {}
    actual: Tabla | None = None
    for linea in ruta.read_text(encoding="utf-8").splitlines():
        if m := re.match(r"CREATE TABLE `(\w+)` \(", linea):
            actual = tablas.setdefault(m.group(1), Tabla(m.group(1)))
        elif actual and linea.startswith(")"):
            actual = None
        elif actual and (m := re.match(r"  `(\w+)` (.+?),?$", linea)):
            resto = m.group(2)
            tipo = re.sub(r" (CHARACTER SET|COLLATE) \w+", "", resto.split(" NOT NULL")[0].split(" DEFAULT")[0])
            tipo = tipo.replace(" NULL", "").strip()
            defecto = re.search(r"DEFAULT ('(?:[^']|'')*'|\S+)", resto)
            actual.columnas.append({
                "nombre": m.group(1),
                "tipo": tipo,
                "nulo": "NOT NULL" not in resto,
                "defecto": defecto.group(1).strip("'") if defecto and defecto.group(1) != "NULL" else "",
                "auto": "AUTO_INCREMENT" in resto,
            })
        elif actual and (m := re.match(r"  PRIMARY KEY \((.+)\)", linea)):
            actual.primaria = re.findall(r"`(\w+)`", m.group(1))
        elif actual and (m := re.match(r"  UNIQUE KEY `\w+` \((.+)\)", linea)):
            actual.unicas.append(re.findall(r"`(\w+)`", m.group(1)))
        elif actual and (m := re.match(r"  KEY `(\w+)` \((.+)\)", linea)):
            actual.indices.append((m.group(1), re.findall(r"`(\w+)`|\((\(.+\))\)", m.group(2))))
        elif actual and (m := re.match(
            r"  CONSTRAINT `\w+` FOREIGN KEY \(`(\w+)`\) REFERENCES `(\w+)` \(`(\w+)`\)(?: ON DELETE (SET NULL|CASCADE|RESTRICT|NO ACTION))?",
            linea,
        )):
            actual.foraneas.append({
                "columna": m.group(1), "tabla": m.group(2), "ref": m.group(3), "al_borrar": m.group(4) or "RESTRICT",
            })
    return tablas


def leer_comentarios() -> tuple[dict[str, str], dict[tuple[str, str], str]]:
    """Comentario de cada tabla (el que antecede a Schema::create) y de cada columna, en orden de migración."""
    de_tabla: dict[str, str] = {}
    de_columna: dict[tuple[str, str], str] = {}
    for archivo in sorted((BACKEND / "database/migrations").glob("*.php")):
        texto = archivo.read_text(encoding="utf-8")
        # El PHPDoc de up(), en una línea o en varias.
        bloque_up = re.search(r"/\*\*((?:(?!\*/).)*)\*/\s*\n\s*public function up", texto, re.S)
        doc_up = ""
        if bloque_up:
            doc_up = " ".join(l.strip().lstrip("*").strip() for l in bloque_up.group(1).splitlines()).strip()
            doc_up = "" if doc_up in {"Run the migrations."} else doc_up
        creadas = re.findall(r"Schema::create\('(\w+)'", texto)
        actual = None
        pendiente: list[str] = []
        del_bloque = ""
        agregadas: list[str] = []
        for linea in texto.splitlines():
            limpia = linea.strip()
            if limpia.startswith("//"):
                pendiente.append(limpia.lstrip("/").strip())
                continue
            if m := re.search(r"Schema::(create|table)\('(\w+)'", limpia):
                actual = m.group(2)
                if m.group(1) == "create":
                    texto_tabla = " ".join(pendiente) or (doc_up if len(creadas) == 1 else "")
                    if texto_tabla:
                        de_tabla[actual] = texto_tabla
                    del_bloque = ""
                else:
                    del_bloque = " ".join(pendiente)
                agregadas = []
                pendiente = []
                continue
            if actual and (m := re.match(r"\$table->(\w+)\('(\w+)'", limpia)) and m.group(1) not in NO_SON_COLUMNAS:
                columna = m.group(2)
                agregadas.append(columna)
                al_lado = re.search(r";\s*//\s*(.+)$", limpia)
                partes = pendiente + ([al_lado.group(1)] if al_lado else [])
                if comentario := re.search(r"->comment\('((?:[^'\\]|\\.)*)'\)", limpia):
                    partes.append(comentario.group(1))
                if partes:
                    de_columna[(actual, columna)] = " ".join(partes)
            if limpia.startswith("});"):
                # El comentario de un Schema::table que agrega una sola columna es el de esa columna.
                if actual and del_bloque and len(agregadas) == 1:
                    de_columna.setdefault((actual, agregadas[0]), del_bloque)
                actual = None
                del_bloque = ""
            pendiente = []
    return de_tabla, de_columna


def plural(nombre: str) -> str:
    serpiente = re.sub(r"(?<!^)(?=[A-Z])", "_", nombre).lower()
    if re.search(r"[^aeiou]y$", serpiente):
        return serpiente[:-1] + "ies"
    if re.search(r"(s|x|ch|sh)$", serpiente):
        return serpiente + "es"
    return serpiente + "s"


def leer_enums() -> dict[tuple[str, str], str]:
    """Valores de cada columna con enum: "pending (Pendiente), …", leídos de los casts de cada modelo."""
    valores: dict[str, str] = {}
    for archivo in (BACKEND / "app/Enums").glob("*.php"):
        texto = archivo.read_text(encoding="utf-8")
        casos = re.findall(r"case (\w+) = '([^']*)';", texto)
        etiquetas: dict[str, str] = {}
        if m := re.search(r"function (?:getLabel|label)\(\)[^{]*\{(.+?)\n    \}", texto, re.S):
            for brazo in re.finditer(r"((?:self::\w+,?\s*)+)=> '([^']*)'", m.group(1)):
                for caso in re.findall(r"self::(\w+)", brazo.group(1)):
                    etiquetas[caso] = brazo.group(2)
        valores[archivo.stem] = ", ".join(
            f"`{valor}` ({etiquetas[caso]})" if caso in etiquetas else f"`{valor}`" for caso, valor in casos
        )
    por_columna: dict[tuple[str, str], str] = {}
    for archivo in (BACKEND / "app/Models").glob("*.php"):
        texto = archivo.read_text(encoding="utf-8")
        tabla = plural(archivo.stem)
        for columna, enum in re.findall(r"'(\w+)' => (\w+)::class", texto):
            if enum in valores:
                por_columna[(tabla, columna)] = valores[enum]
    return por_columna


def celda(texto: str) -> str:
    return texto.replace("|", "\\|").replace("\n", " ")


def tipo_corto(tipo: str) -> str:
    return re.sub(r"\W.*", "", tipo) or "dato"


def diagrama(tablas: dict[str, Tabla], nombres: list[str], con_atributos: bool, direccion: str) -> str:
    """erDiagram de Mermaid: cada llave foránea es una relación; sin las que van a users (quién creó o anuló).
    Se dibuja con el layout ELK de Mermaid 11 (@mermaid-js/layout-elk); sin él, Mermaid usa su layout de siempre."""
    dentro = set(nombres)
    lineas = ["---", "config:", "  layout: elk", "---", "erDiagram", f"  direction {direccion}"]
    if con_atributos:
        for nombre in nombres:
            t = tablas[nombre]
            foraneas = {f["columna"] for f in t.foraneas}
            unicas = {c for u in t.unicas for c in u}
            lineas.append(f"  {nombre} {{")
            for c in t.columnas:
                marcas = [m for m, si in (("PK", c["nombre"] in t.primaria), ("FK", c["nombre"] in foraneas),
                                          ("UK", c["nombre"] in unicas)) if si]
                if marcas:
                    lineas.append(f"    {tipo_corto(str(c['tipo']))} {c['nombre']} {', '.join(marcas)}")
            lineas.append("  }")
    for nombre in nombres:
        t = tablas[nombre]
        unicas = {u[0] for u in t.unicas if len(u) == 1}
        for f in t.foraneas:
            if f["tabla"] == "users" or (f["tabla"] not in dentro and not con_atributos):
                continue
            nula = next(c["nulo"] for c in t.columnas if c["nombre"] == f["columna"])
            padre = "|o" if nula else "||"
            hijo = "o|" if f["columna"] in unicas else "o{"
            # Sin etiqueta: la llave foránea ya está entre los atributos, y la etiqueta ensancha el dibujo.
            lineas.append(f'  {f["tabla"]} {padre}--{hijo} {nombre} : ""')
    return "\n".join(lineas) + "\n"


def main() -> None:
    tablas = leer_ddl(AQUI / "sql/ddl.sql")
    de_tabla, de_columna = leer_comentarios()
    enums = leer_enums()

    repartidas = [t for nombres in DOMINIOS.values() for t in nombres]
    faltan = sorted(set(tablas) - set(repartidas))
    sobran = sorted(set(repartidas) - set(tablas))
    if faltan or sobran or len(repartidas) != len(set(repartidas)):
        raise SystemExit(f"Revisa DOMINIOS: sin dominio {faltan}, no existen {sobran}")

    md = [
        "# Anexo A. Diccionario de datos",
        "",
        "Generado con `python3 docs/entregables/E2-plataforma-de-datos/generar-modelo.py` desde `sql/ddl.sql` "
        "(lo que MySQL 8 tiene de verdad), los comentarios de las migraciones y los enums de los modelos. "
        "En **Llave**, PK es la llave primaria, UK un valor único y FK una llave foránea con su acción al borrar "
        "la fila referida. Los nulos se marcan con **Sí**. Los dominios siguen el orden de la tabla 1 del "
        "documento.",
        "",
    ]
    numero = 0
    for dominio, nombres in DOMINIOS.items():
        md += [f"## {dominio} {{-}}", ""]
        for nombre in nombres:
            t = tablas[nombre]
            numero += 1
            foraneas = {f["columna"]: f for f in t.foraneas}
            unicas = {c for u in t.unicas for c in u}
            md += [f"### `{nombre}` {{-}}", ""]
            if nombre in de_tabla:
                md += [de_tabla[nombre], ""]
            md += [
                f"*Tabla A{numero}. Columnas de `{nombre}`.*",
                "",
                "| Columna | Tipo | Nulo | Por defecto | Llave | Descripción |",
                "| --- | --- | :-: | --- | --- | --- |",
            ]
            for c in t.columnas:
                col = str(c["nombre"])
                llave = []
                if col in t.primaria:
                    llave.append("PK")
                if col in unicas:
                    llave.append("UK")
                if col in foraneas:
                    f = foraneas[col]
                    llave.append(f"FK → `{f['tabla']}` ({AL_BORRAR[f['al_borrar']]})")
                texto = de_columna.get(
                    (nombre, col), POR_TABLA.get(f"{nombre}.{col}", LARAVEL.get(col, GLOSARIO.get(col, "")))
                )
                if (nombre, col) in enums:
                    texto = (texto + " " if texto else "") + "Valores: " + enums[(nombre, col)] + "."
                md.append(
                    f"| `{col}` | {celda(str(c['tipo']))} | {'Sí' if c['nulo'] else ''} | "
                    f"{celda(str(c['defecto']))} | {' '.join(llave)} | {celda(texto)} |"
                )
            if len(t.unicas) and any(len(u) > 1 for u in t.unicas):
                compuestas = "; ".join("(" + ", ".join(u) + ")" for u in t.unicas if len(u) > 1)
                md += ["", f"Únicos compuestos: {compuestas}."]
            md.append("")
    (AQUI / "anexos/E2-diccionario.md").write_text("\n".join(md), encoding="utf-8")

    for viejo in (AQUI / "diagramas").glob("er-*.mmd"):
        viejo.unlink()
    for archivo, (direccion, nombres) in DIAGRAMAS.items():
        (AQUI / f"diagramas/{archivo}.mmd").write_text(diagrama(tablas, nombres, True, direccion), encoding="utf-8")
    (AQUI / "diagramas/er-nucleo.mmd").write_text(diagrama(tablas, NUCLEO, False, "TB"), encoding="utf-8")

    acciones = Counter(f["al_borrar"] for t in tablas.values() for f in t.foraneas)
    columnas = sum(len(t.columnas) for t in tablas.values())
    con_texto = sum(
        1 for t in tablas.values() for c in t.columnas
        if (t.nombre, c["nombre"]) in de_columna or (t.nombre, c["nombre"]) in enums
    )
    traducidas = sum(
        1 for t in tablas.values() for c in t.columnas
        if (t.nombre, c["nombre"]) not in de_columna and (t.nombre, c["nombre"]) not in enums
        and (f"{t.nombre}.{c['nombre']}" in POR_TABLA or c["nombre"] in LARAVEL or c["nombre"] in GLOSARIO)
    )
    sin_texto = [
        f"{t.nombre}.{c['nombre']}" for t in tablas.values() for c in t.columnas
        if (t.nombre, c["nombre"]) not in de_columna and (t.nombre, c["nombre"]) not in enums
        and f"{t.nombre}.{c['nombre']}" not in POR_TABLA and c["nombre"] not in LARAVEL and c["nombre"] not in GLOSARIO
    ]
    hacia = Counter(f["tabla"] for t in tablas.values() for f in t.foraneas)
    resumen = [
        f"Tablas: {len(tablas)}; columnas: {columnas}; con comentario en la migración o enum: {con_texto}; "
        f"descritas con el glosario: {traducidas}; sin descripción: {len(sin_texto)} {sin_texto}",
        f"Llaves foráneas: {sum(acciones.values())} ({', '.join(f'{k}: {v}' for k, v in sorted(acciones.items()))})",
        f"Únicos: {sum(len(t.unicas) for t in tablas.values())}; índices (sin PK ni únicos): "
        f"{sum(len(t.indices) for t in tablas.values())}",
        f"Tablas con comentario: {sum(1 for t in tablas if t in de_tabla)}",
        "Tablas más referidas: " + ", ".join(f"{k} {v}" for k, v in hacia.most_common(8)),
        "",
        "Por dominio (tablas, columnas, llaves foráneas):",
    ]
    for dominio, nombres in DOMINIOS.items():
        resumen.append(
            f"  {dominio}: {len(nombres)}, {sum(len(tablas[n].columnas) for n in nombres)}, "
            f"{sum(len(tablas[n].foraneas) for n in nombres)}"
        )
    (AQUI / "evidencias/resumen-del-modelo.txt").write_text("\n".join(resumen) + "\n", encoding="utf-8")
    print("\n".join(resumen))


if __name__ == "__main__":
    main()
