/** Las direcciones de la web. Son las mismas rutas con nombre de Laravel (backend/routes/web.php). */

type Params = Record<string, string | number | null | undefined>;

/** Añade los parámetros que tengan valor, en el orden dado: withQuery('/reservar', { tratamiento: 'limpieza' }). */
export function withQuery(path: string, params: Params = {}): string {
  const search = new URLSearchParams();

  for (const [key, value] of Object.entries(params)) {
    if (value !== null && value !== undefined && value !== '') {
      search.set(key, String(value));
    }
  }

  const query = search.toString();

  return query ? `${path}?${query}` : path;
}

export const paths = {
  home: '/',
  treatments: '/tratamientos',
  treatment: (slug: string) => `/tratamientos/${slug}`,
  promotions: '/promociones',
  promotion: (slug: string) => `/promociones/${slug}`,
  team: '/equipo',
  cases: '/antes-y-despues',
  reviews: '/opiniones',
  faq: '/preguntas-frecuentes',
  contact: '/contacto',
  privacy: '/privacidad',
  booking: (params: Params = {}) => withQuery('/reservar', params),
  account: '/mi-cuenta',
  accountChange: (appointmentId: number) => `/mi-cuenta/citas/${appointmentId}/cambiar`,
  accountHealth: (patientId: number) => `/mi-cuenta/pacientes/${patientId}/salud`,
  link: (token: string) => `/c/${token}`,
  linkChange: (token: string) => `/c/${token}/cambiar`,
} as const;
