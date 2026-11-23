import type {
  AccountData,
  AppointmentCard,
  BookedAppointment,
  BookingDays,
  BookingDevice,
  BookingOptions,
  BookingTreatment,
  ChangeData,
  HealthData,
  HomeData,
  PageKey,
  PageMeta,
  PageResponse,
  PageTexts,
  PickerDay,
  Site,
  Slot,
  Treatment,
} from '@/api/types';

/** Datos de ejemplo con la forma que entrega la API, para las pruebas. Cada prueba cambia solo lo que le importa. */

const PAGES: PageKey[] = [
  'servicios',
  'equipo',
  'contacto',
  'preguntas',
  'promociones',
  'casos',
  'opiniones',
  'cuenta',
  'reservar',
];

function pageTexts(page: PageKey): PageTexts {
  return {
    kicker: null,
    icon: 'heart',
    title: ['Página de ', page, ''],
    lead: null,
    photo_url: null,
    closing: { title: `Cierre de ${page}`, text: null, whatsapp: false },
  };
}

export function site(changes: Partial<Site> = {}): Site {
  return {
    clinic: {
      name: 'Clínica Vega',
      tagline: 'Dentistas en Juliaca',
      district: 'Juliaca',
      logo_url: null,
      address: 'Jr. Apurímac 648, Juliaca',
      directions_url: 'https://maps.example/como-llegar',
      map_embed_url: null,
      whatsapp: { number: '51912345678', display: '912 345 678' },
      phone: null,
      call_href: 'tel:+51912345678',
      email: null,
      socials: [],
      reviews_url: null,
      google_rating: null,
      hours: {
        lines: [{ days: 'Lunes a sábado', hours: ['9:00 a. m. – 7:00 p. m.'] }],
        note: null,
        status: 'Abierto ahora',
        is_open: true,
      },
    },
    home: {
      tagline: null,
      title: { strong: 'Atención dental', thin: '', accent: 'sin esperas' },
      intro: null,
      about_html: null,
      hero_image_url: null,
    },
    sections: { promotions: false, cases: false, reviews: false, faq: false },
    pages: Object.fromEntries(PAGES.map((page) => [page, pageTexts(page)])) as Record<PageKey, PageTexts>,
    closing: { title: 'Tu salud bucal, en buenas manos', text: null, whatsapp: false },
    team_values: [],
    how_to_pay: 'Pagas en el consultorio con Yape, Plin, tarjeta o efectivo.',
    year: 2026,
    ...changes,
  };
}

export function meta(changes: Partial<PageMeta> = {}): PageMeta {
  return {
    title: 'Clínica Vega · Dentistas en Juliaca',
    og_title: 'Clínica Vega',
    description: null,
    image: 'http://localhost/imagen-al-compartir.jpg',
    noindex: false,
    json_ld: [],
    ...changes,
  };
}

/** La respuesta de una página: sus datos y lo que dice de sí misma. */
export function page<T>(data: T, changes: Partial<PageMeta> = {}): PageResponse<T> {
  return { data, meta: meta(changes) };
}

export function appointment(changes: Partial<AppointmentCard> = {}): AppointmentCard {
  return {
    id: 7,
    kicker: 'Tu cita',
    status: 'Por confirmar',
    tone: null,
    when: 'Miércoles 4 de noviembre, 10:00 a. m.',
    what: 'Limpieza dental · Dra. Vega',
    guest_notice: false,
    ask_health: null,
    pending_request: null,
    can_change: true,
    can_confirm: true,
    can_cancel: true,
    cancel_min_hours: 4,
    cancel_message: 'Hola, necesito cancelar la cita de Rosa Quispe del miércoles 4 de noviembre a las 10:00 a. m.',
    ...changes,
  };
}

export function day(date: string, label: string): PickerDay {
  const [, , number] = date.split('-');

  return { date, weekday: label.slice(0, 3), number: Number(number), month: 'nov', label };
}

export function slot(time: string, label: string, suggested = true): Slot {
  return { time, label, doctor_id: 1, suggested };
}

export function bookingTreatment(changes: Partial<BookingTreatment> = {}): BookingTreatment {
  return {
    id: 3,
    slug: 'limpieza-dental',
    name: 'Limpieza dental',
    summary: 'Deja tus dientes sin sarro.',
    art: 'limpieza',
    is_evaluation: false,
    duration_minutes: 45,
    sessions_text: null,
    label: 'Limpieza dental',
    price: { was: null, now: 'Desde S/ 120' },
    price_line: 'Precio: desde S/ 120.',
    ...changes,
  };
}

export function bookingOptions(changes: Partial<BookingOptions> = {}): BookingOptions {
  return {
    treatments: [bookingTreatment()],
    initial_treatment_id: null,
    preferred_doctor_id: null,
    campaign: null,
    promo_code: '',
    promo_problem: null,
    campaign_slug: null,
    origin: null,
    device: null,
    relationships: [{ value: 'child', label: 'Hijo o hija' }],
    ...changes,
  };
}

/** Los días con horarios de un tratamiento: dos días, y los horarios del primero. */
export function bookingDays(changes: Partial<BookingDays> = {}): BookingDays {
  return {
    doctors: [{ id: 1, name: 'Dra. Vega' }],
    doctor_id: null,
    days: [day('2026-11-04', 'Miércoles 4 de noviembre'), day('2026-11-05', 'Jueves 5 de noviembre')],
    date: '2026-11-04',
    day: 'Miércoles 4 de noviembre',
    slots: [slot('09:00', '9:00 a. m.'), slot('15:00', '3:00 p. m.')],
    ...changes,
  };
}

/** El celular recordado de una familia: la titular y su hijo, que está en tratamiento. */
export function bookingDevice(changes: Partial<BookingDevice> = {}): BookingDevice {
  return {
    phone: '987 654 321',
    family: [
      { id: 21, name: 'Rosa Quispe', first_name: 'Rosa', is_self: true, in_treatment: false },
      { id: 22, name: 'Luis Quispe', first_name: 'Luis', is_self: false, in_treatment: true },
    ],
    has_holder: true,
    patient_id: 21,
    privacy_accepted: true,
    promotions_accepted: false,
    ...changes,
  };
}

export function bookedAppointment(changes: Partial<BookedAppointment> = {}): BookedAppointment {
  return {
    page_url: '/reservar/cita/9?signature=abc',
    treatment: 'Limpieza dental',
    when: 'Miércoles 4 de noviembre, 9:00 a. m.',
    who: 'Rosa Quispe · con Dra. Vega',
    price_line: 'Precio: desde S/ 120.',
    phone: '987 654 321',
    remembered: false,
    calendar_url: '/reservar/cita/9/calendario?signature=abc',
    whatsapp_message: 'Hola, acabo de reservar una cita para el miércoles 4 de noviembre a las 9:00 a. m.',
    origins: [
      { value: 'facebook', label: 'Facebook' },
      { value: 'recommendation', label: 'Recomendación' },
    ],
    origin_url: '/api/v1/booking/appointments/9/origin?signature=abc',
    promo_notice: null,
    ...changes,
  };
}

/** Cambiar el horario de una cita: al instante, con dos días libres. */
export function changeData(changes: Partial<ChangeData> = {}): ChangeData {
  return {
    appointment: { what: 'Limpieza dental', now: 'Miércoles 4 de noviembre, 10:00 a. m.', doctor: 'Dra. Vega' },
    can_change: true,
    automatic: true,
    fixed_date: false,
    max_options: 3,
    pending_request: null,
    days: [day('2026-11-05', 'Jueves 5 de noviembre'), day('2026-11-06', 'Viernes 6 de noviembre')],
    date: '2026-11-05',
    day: 'Jueves 5 de noviembre',
    slots: [slot('09:00', '9:00 a. m.'), slot('11:30', '11:30 a. m.'), slot('15:00', '3:00 p. m.')],
    ...changes,
  };
}

export function healthData(changes: Partial<HealthData> = {}): HealthData {
  return {
    patient_id: 12,
    title: 'Antes de tu cita, Rosa',
    lead: 'Cuéntanos tu salud en dos minutos.',
    questions: [
      {
        choice: 'has_allergies',
        detail: 'allergies',
        question: '¿Tienes alguna alergia?',
        hint: 'A medicamentos, al látex o a la anestesia.',
        label: '¿A qué?',
        placeholder: 'Por ejemplo: penicilina',
      },
      {
        choice: 'takes_medication',
        detail: 'medication',
        question: '¿Tomas algún medicamento?',
        hint: 'Los de todos los días.',
        label: '¿Cuál?',
        placeholder: 'Por ejemplo: aspirina',
      },
    ],
    conditions_question: '¿Tienes alguna de estas condiciones?',
    conditions: [
      { key: 'diabetes', label: 'Diabetes' },
      { key: 'hypertension', label: 'Presión alta' },
    ],
    also: [{ key: 'is_pregnant', label: 'Estoy embarazada' }],
    notes_hint: 'Operaciones recientes, miedo al dentista…',
    ...changes,
  };
}

/** Mi cuenta desde un celular recordado, antes de verificar por WhatsApp: sin montos ni datos personales. */
export function account(
  changes: Partial<Extract<AccountData, { guest: false }>> = {},
): Extract<AccountData, { guest: false }> {
  return {
    guest: false,
    holder: { first_name: 'Rosa', initials: 'RQ' },
    several: false,
    phone: '987 654 321',
    elevated: false,
    upcoming: [],
    plans: null,
    balances: null,
    past: null,
    family: null,
    ...changes,
  };
}

export function treatment(changes: Partial<Treatment> = {}): Treatment {
  return {
    id: 3,
    slug: 'limpieza-dental',
    name: 'Limpieza dental',
    summary: 'Deja tus dientes sin sarro.',
    art: 'limpieza',
    is_evaluation: false,
    duration_minutes: 45,
    sessions_text: null,
    price: 'Desde S/ 120',
    ...changes,
  };
}

/** El inicio de un consultorio que recién empieza: se puede reservar y hay un tratamiento publicado. */
export function homeData(changes: Partial<HomeData> = {}): HomeData {
  return {
    welcome: null,
    can_book: true,
    next_free: null,
    campaigns: [],
    treatments: [treatment()],
    treatments_count: 1,
    shows_prices: true,
    specialty: null,
    doctor: null,
    other_doctors: 0,
    cases: [],
    reviews: [],
    faqs: [],
    ...changes,
  };
}
