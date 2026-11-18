--[==[
Filtro de pandoc para los entregables en Word.

1. Resalta en amarillo cada [PENDIENTE …] (estilo de carácter "Pendiente" de plantilla-upeu.docx),
   esté escrito como texto o como código en línea.
2. Cambia un diagrama Mermaid por su imagen exportada cuando va precedido de
   <!-- figura: ruta/al/diagrama.png --> (o <!-- figura: ruta.png 12cm --> para otro ancho; por defecto,
   16 cm). En GitHub se sigue viendo el diagrama Mermaid. Toda imagen sola en su párrafo va centrada.
3. Un párrafo en cursiva que empieza con "Figura N." o "Tabla N." toma el estilo de leyenda.
4. "Resumen ejecutivo" y los anexos sin número no se numeran.
5. El ancho de cada columna de una tabla sale de su contenido: ninguna es más angosta que su palabra más
   larga (hasta 30 letras) y el espacio que sobra va a las columnas con más texto. Entre los párrafos
   [[HORIZONTAL]] y [[VERTICAL]] (hojas horizontales, ver ajustar-docx.py) la línea es más larga.
]==]

local function pendiente(texto)
  return pandoc.Span({ pandoc.Str(texto) }, { ['custom-style'] = 'Pendiente' })
end

local function marcar_pendientes(inlines)
  local salida = pandoc.List()
  local abierto = nil
  for _, el in ipairs(inlines) do
    if abierto then
      abierto:insert(el)
      if el.t == 'Str' and el.text:find('%]') then
        salida:insert(pandoc.Span(abierto, { ['custom-style'] = 'Pendiente' }))
        abierto = nil
      end
    elseif el.t == 'Code' and el.text:find('^%[PENDIENTE') then
      salida:insert(pendiente(el.text))
    elseif el.t == 'Str' and el.text:find('%[PENDIENTE') then
      local antes, desde = el.text:match('^(.-)(%[PENDIENTE.*)$')
      if antes ~= '' then
        salida:insert(pandoc.Str(antes))
      end
      if desde:find('%]') then
        salida:insert(pendiente(desde))
      else
        abierto = pandoc.List({ pandoc.Str(desde) })
      end
    else
      salida:insert(el)
    end
  end
  if abierto then
    salida:extend(abierto)
  end
  return salida
end

local function es_leyenda(bloque, prefijo)
  if bloque.t ~= 'Para' or #bloque.content ~= 1 or bloque.content[1].t ~= 'Emph' then
    return false
  end
  local primero = bloque.content[1].content[1]
  return primero and primero.t == 'Str' and primero.text == prefijo
end

local function leyenda(bloque, estilo)
  return pandoc.Div({ pandoc.Para(bloque.content[1].content) }, { ['custom-style'] = estilo })
end

function Blocks(bloques)
  local salida = pandoc.List()
  local i = 1
  while i <= #bloques do
    local b = bloques[i]
    local ruta, ancho
    if b.t == 'RawBlock' and b.format == 'html' then
      ruta, ancho = b.text:match('^<!%-%-%s*figura:%s*(%S+)%s*(%S*)%s*%-%->$')
    end
    if ruta then
      local siguiente = bloques[i + 1]
      if siguiente and siguiente.t == 'CodeBlock' then
        i = i + 1
      end
      local imagen = pandoc.Image({}, ruta, '', { width = ancho ~= '' and ancho or '16cm' })
      salida:insert(pandoc.Div({ pandoc.Para({ imagen }) }, { ['custom-style'] = 'Figure' }))
    elseif b.t == 'Para' and #b.content == 1 and b.content[1].t == 'Image' then
      salida:insert(pandoc.Div({ b }, { ['custom-style'] = 'Figure' }))
    elseif es_leyenda(b, 'Figura') then
      salida:insert(leyenda(b, 'Image Caption'))
    elseif es_leyenda(b, 'Tabla') then
      salida:insert(leyenda(b, 'Table Caption'))
    else
      salida:insert(b)
    end
    i = i + 1
  end
  return salida
end

-- Letras que entran en el ancho útil de la hoja A4: 16 cm en vertical y 24,7 cm en horizontal.
local LETRAS_VERTICAL = 95
local LETRAS_HORIZONTAL = 147

local function filas(tabla)
  local todas = pandoc.List()
  todas:extend(tabla.head.rows)
  for _, cuerpo in ipairs(tabla.bodies) do
    todas:extend(cuerpo.body)
  end
  return todas
end

-- Largo de una palabra en letras: las mayúsculas y los dígitos son más anchos (RF-WEB-01, HU-01).
local function largo(palabra)
  local total = 0
  for _, codigo in utf8.codes(palabra) do
    local letra = utf8.char(codigo)
    total = total + ((letra:match('%u') or letra:match('%d')) and 1.3 or 1)
  end
  return total
end

local function ajustar_tabla(tabla, letras_por_linea)
  local columnas = #tabla.colspecs
  local minimo, texto, celdas = {}, {}, {}
  for j = 1, columnas do
    minimo[j], texto[j], celdas[j] = 4, 0, 0
  end
  for _, fila in ipairs(filas(tabla)) do
    for j, celda in ipairs(fila.cells) do
      if j <= columnas then
        local contenido = pandoc.utils.stringify(celda.contents)
        for palabra in contenido:gmatch('%S+') do
          minimo[j] = math.max(minimo[j], math.min(largo(palabra), 30) + 2)
        end
        texto[j] = texto[j] + (utf8.len(contenido) or #contenido)
        celdas[j] = celdas[j] + 1
      end
    end
  end
  local suma_minimos, extra = 0, {}
  for j = 1, columnas do
    suma_minimos = suma_minimos + minimo[j]
    extra[j] = math.max((celdas[j] > 0 and texto[j] / celdas[j] or 0) - minimo[j], 0)
  end
  local suma_extra = 0
  for j = 1, columnas do
    suma_extra = suma_extra + extra[j]
  end
  local sobra = letras_por_linea - suma_minimos
  for j = 1, columnas do
    local ancho
    if sobra <= 0 or suma_extra == 0 then
      ancho = minimo[j] / suma_minimos
    else
      ancho = (minimo[j] + sobra * extra[j] / suma_extra) / letras_por_linea
    end
    tabla.colspecs[j] = { tabla.colspecs[j][1], ancho }
  end
  return tabla
end

-- Recorre el documento en orden para saber si cada tabla cae en una hoja vertical u horizontal.
function Pandoc(doc)
  local letras = LETRAS_VERTICAL
  for i, bloque in ipairs(doc.blocks) do
    local marca = bloque.t == 'Para' and pandoc.utils.stringify(bloque) or ''
    if marca == '[[HORIZONTAL]]' then
      letras = LETRAS_HORIZONTAL
    elseif marca == '[[VERTICAL]]' then
      letras = LETRAS_VERTICAL
    elseif bloque.t == 'Table' then
      doc.blocks[i] = ajustar_tabla(bloque, letras)
    else
      doc.blocks[i] = pandoc.walk_block(bloque, {
        Table = function(tabla)
          return ajustar_tabla(tabla, letras)
        end,
      })
    end
  end
  return doc
end

function Inlines(inlines)
  return marcar_pendientes(inlines)
end

function Header(h)
  local texto = pandoc.utils.stringify(h.content)
  if h.level == 1 and (texto == 'Resumen ejecutivo' or texto:find('^Anexo')) then
    h.classes:insert('unnumbered')
  end
  return h
end

return {
  { Inlines = Inlines },
  { Blocks = Blocks, Header = Header },
  { Pandoc = Pandoc },
}
