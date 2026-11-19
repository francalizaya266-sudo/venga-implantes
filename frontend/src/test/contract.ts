import openApiText from '../../../docs/api/openapi.json?raw';

/**
 * El contrato de la API del lado de la web: lo que responde la API de mentira de una prueba tiene que cumplir
 * docs/api/openapi.json, el mismo documento con que las pruebas del backend validan las respuestas de Laravel. Así
 * una prueba de la web no puede pasar con un dato que la API nunca envía.
 */

type Schema = Record<string, unknown>;

interface Operation {
  responses: Record<string, { content?: { 'application/json'?: { schema: Schema } } }>;
}

interface OpenApi {
  paths: Record<string, Record<string, Operation>>;
  components: Record<string, Record<string, Schema>>;
}

const openApi = JSON.parse(openApiText) as OpenApi;

const ERROR: Schema = { $ref: '#/components/schemas/Error' };

function operation(path: string, method: string): Operation | undefined {
  for (const [template, operations] of Object.entries(openApi.paths)) {
    const pattern = template
      .split(/\{[^}]+\}/)
      .map((part) => part.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))
      .join('[^/]+');

    if (new RegExp(`^${pattern}$`).test(path)) {
      return operations[method.toLowerCase()];
    }
  }

  return undefined;
}

function typeOf(value: unknown): string {
  if (value === null) {
    return 'null';
  }
  if (Array.isArray(value)) {
    return 'array';
  }
  if (typeof value === 'number') {
    return Number.isInteger(value) ? 'integer' : 'number';
  }

  return typeof value;
}

function resolve(reference: string): Schema {
  return reference
    .slice(2)
    .split('/')
    .reduce<Schema>((schema, key) => schema[key] as Schema, openApi as unknown as Schema);
}

/** Lo que no cumple el esquema, con dónde está ("cuerpo.data.treatments[0].price: …"). Vacío si cumple. */
function schemaErrors(schema: Schema, value: unknown, at: string): string[] {
  if (typeof schema.$ref === 'string') {
    return schemaErrors(resolve(schema.$ref), value, at);
  }

  const options = (schema.oneOf ?? schema.anyOf) as Schema[] | undefined;
  if (options) {
    const failures = options.map((option) => schemaErrors(option, value, at));
    const matches = failures.filter((errors) => errors.length === 0).length;

    if (matches === 1 || (matches > 1 && schema.anyOf)) {
      return [];
    }

    return matches > 1
      ? [`${at}: cumple más de una opción de oneOf.`]
      : [`${at}: no cumple ninguna de sus opciones:`, ...failures.flat().map((error) => `  ${error}`)];
  }

  if ('const' in schema && value !== schema.const) {
    return [`${at}: debe ser ${JSON.stringify(schema.const)} y es ${JSON.stringify(value)}.`];
  }

  if (Array.isArray(schema.enum) && !schema.enum.includes(value)) {
    return [`${at}: ${JSON.stringify(value)} no es una de ${JSON.stringify(schema.enum)}.`];
  }

  if (schema.type !== undefined) {
    const types = ([] as unknown[]).concat(schema.type);
    const type = typeOf(value);

    if (!types.includes(type) && !(type === 'integer' && types.includes('number'))) {
      return [`${at}: es ${type} y debe ser ${types.join(' o ')}.`];
    }
  }

  if (Array.isArray(value)) {
    const size = [
      ...(typeof schema.minItems === 'number' && value.length < schema.minItems
        ? [`${at}: tiene ${value.length} elementos y debe tener al menos ${schema.minItems}.`]
        : []),
      ...(typeof schema.maxItems === 'number' && value.length > schema.maxItems
        ? [`${at}: tiene ${value.length} elementos y debe tener como mucho ${schema.maxItems}.`]
        : []),
    ];

    return [
      ...size,
      ...value.flatMap((item, index) =>
        schema.items ? schemaErrors(schema.items as Schema, item, `${at}[${index}]`) : [],
      ),
    ];
  }

  if (typeof value === 'number') {
    if (typeof schema.minimum === 'number' && value < schema.minimum) {
      return [`${at}: ${value} es menor que ${schema.minimum}.`];
    }

    return typeof schema.maximum === 'number' && value > schema.maximum
      ? [`${at}: ${value} es mayor que ${schema.maximum}.`]
      : [];
  }

  if (typeof value === 'string' && typeof schema.pattern === 'string' && !new RegExp(schema.pattern, 'u').test(value)) {
    return [`${at}: "${value}" no tiene la forma ${schema.pattern}.`];
  }

  if (typeof value === 'object' && value !== null) {
    const properties = (schema.properties ?? {}) as Record<string, Schema>;
    const present = value as Record<string, unknown>;
    const missing = ((schema.required ?? []) as string[])
      .filter((key) => !(key in present))
      .map((key) => `${at}.${key}: falta.`);

    return [
      ...missing,
      ...Object.entries(present).flatMap(([key, item]) => {
        if (properties[key]) {
          return schemaErrors(properties[key], item, `${at}.${key}`);
        }

        return schema.additionalProperties === false
          ? [`${at}.${key}: no está documentado.`]
          : typeof schema.additionalProperties === 'object'
            ? schemaErrors(schema.additionalProperties as Schema, item, `${at}.${key}`)
            : [];
      }),
    ];
  }

  return [];
}

/**
 * Lo que una respuesta de mentira ("GET /api/v1/home", 200, su cuerpo) no cumple del documento. Un error del
 * servidor (500 o más) puede salir de cualquier ruta: se compara con el error general.
 */
export function contractErrors(method: string, url: string, status: number, body: unknown): string[] {
  const path = new URL(url, 'http://localhost').pathname;

  if (!path.startsWith('/api/v1/')) {
    return [];
  }

  const where = `${method} ${path} → ${status}`;
  const found = operation(path.slice('/api/v1'.length), method);
  const documented = found?.responses[String(status)];

  if (!found && status < 400) {
    return [`${where}: la ruta no está en docs/api/openapi.json.`];
  }

  if (found && !documented && status < 500) {
    return [`${where}: ese estado no está documentado en docs/api/openapi.json.`];
  }

  const schema = documented?.content?.['application/json']?.schema ?? ERROR;

  return schemaErrors(schema, body, 'cuerpo').map((error) => `${where}: ${error}`);
}
