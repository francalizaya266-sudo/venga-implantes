--[[
Filtro de pandoc para los entregables en Word.

1. Resalta en amarillo cada [PENDIENTE …] (estilo de carácter "Pendiente" de plantilla-upeu.docx),
   esté escrito como texto o como código en línea.
2. Cambia un diagrama Mermaid por su imagen exportada cuando va precedido de
   <!-- figura: ruta/al/diagrama.png -->. En GitHub se sigue viendo el diagrama Mermaid.
3. Un párrafo en cursiva que empieza con "Figura N." o "Tabla N." toma el estilo de leyenda.
4. "Resumen ejecutivo" y los anexos sin número no se numeran.
]]

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
    local ruta = b.t == 'RawBlock' and b.format == 'html' and b.text:match('^<!%-%-%s*figura:%s*(%S+)%s*%-%->$')
    if ruta then
      local siguiente = bloques[i + 1]
      if siguiente and siguiente.t == 'CodeBlock' then
        i = i + 1
      end
      salida:insert(pandoc.Para({ pandoc.Image({}, ruta, '', { width = '16cm' }) }))
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
}
