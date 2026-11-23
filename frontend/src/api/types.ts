/** Las respuestas de la API (backend/routes/api.php), tal como las arma Laravel. */

export interface PageMeta {
  title: string;
  og_title: string;
  description: string | null;
  image: string;
  noindex: boolean;
  json_ld: Record<string, unknown>[];
}

export interface PageResponse<T> {
  data: T;
  meta: PageMeta;
}

export interface DataResponse<T> {
  data: T;
}

export interface MessageResponse {
  message: string | null;
}

/** El título de una página en sus tres partes: antes, lo que va en azul y después. */
export type TitleParts = [string, string, string];

export interface Closing {
  title: string;
  text: string | null;
  whatsapp: boolean;
}

export interface PageTexts {
  kicker: string | null;
  icon: string;
  title: TitleParts;
  lead: string | null;
  photo_url: string | null;
  closing: Closing;
}

export type PageKey =
  'servicios' | 'equipo' | 'contacto' | 'preguntas' | 'promociones' | 'casos' | 'opiniones' | 'cuenta' | 'reservar';

export interface GoogleRating {
  rating: number;
  count: number | null;
  url: string | null;
}

export interface Clinic {
  name: string;
  tagline: string | null;
  district: string | null;
  logo_url: string | null;
  address: string | null;
  directions_url: string | null;
  map_embed_url: string | null;
  whatsapp: { number: string; display: string } | null;
  phone: { display: string; href: string } | null;
  call_href: string | null;
  email: string | null;
  socials: { network: string; url: string }[];
  reviews_url: string | null;
  google_rating: GoogleRating | null;
  hours: {
    lines: { days: string; hours: string[] }[];
    note: string | null;
    status: string | null;
    is_open: boolean;
  };
}

export interface Site {
  clinic: Clinic;
  home: {
    tagline: string | null;
    title: { strong: string; thin: string; accent: string };
    intro: string | null;
    about_html: string | null;
    hero_image_url: string | null;
  };
  sections: { promotions: boolean; cases: boolean; reviews: boolean; faq: boolean };
  pages: Record<PageKey, PageTexts>;
  closing: Closing;
  team_values: { icon: string; title: string; text: string | null }[];
  how_to_pay: string;
  year: number;
}

export interface Treatment {
  id: number;
  slug: string;
  name: string;
  summary: string | null;
  art: string;
  is_evaluation: boolean;
  duration_minutes: number;
  sessions_text: string | null;
  price: string | null;
}

export interface Doctor {
  id: number;
  slug: string;
  name: string;
  specialty: string | null;
  cop_number: string | null;
  rne_number: string | null;
  photo_url: string | null;
  initials: string;
  bio_short: string | null;
  bio_html: string | null;
  education: string[];
  accepts_online_booking: boolean;
  treatments?: { slug: string; name: string }[];
}

export interface Campaign {
  slug: string;
  name: string;
  summary: string | null;
  image_url: string | null;
  until: string | null;
  discount: string | null;
  discount_beside_name: string | null;
  treatment?: { slug: string; name: string } | null;
}

export interface SmileCase {
  id: number;
  title: string;
  before_url: string;
  after_url: string;
  description_html: string | null;
  treatment: { slug: string; name: string } | null;
}

export interface Review {
  id: number;
  rating: number;
  body: string;
  author: string;
  source: string;
  url: string | null;
  date: string | null;
}

export interface Faq {
  id: number;
  question: string;
  answer_html: string;
}

export interface NextFree {
  title: string;
  date: string;
  day: string;
  times: { time: string; label: string }[];
  treatment: { slug: string; name: string; duration_minutes: number };
  promo: string | null;
}

export interface AppointmentCard {
  id: number;
  kicker: string;
  status: string;
  tone: 'ok' | 'bad' | 'quiet' | null;
  when: string;
  what: string;
  guest_notice: boolean;
  ask_health: { patient_id: number; text: string } | null;
  pending_request: string | null;
  can_change: boolean;
  can_confirm: boolean;
  can_cancel: boolean;
  cancel_min_hours: number;
  cancel_message: string;
}

export interface Welcome {
  name: string | null;
  appointments: AppointmentCard[];
  more: number;
  sessions: {
    patient_id: number;
    kicker: string;
    title: string;
    done: number;
    total: number;
    note: string;
    requested: boolean;
    button: string;
  }[];
  checkups: { patient_id: number; kicker: string; title: string; note: string; url: string; button: string }[];
}

export interface HomeData {
  welcome: Welcome | null;
  can_book: boolean;
  next_free: NextFree | null;
  campaigns: Campaign[];
  treatments: Treatment[];
  treatments_count: number;
  shows_prices: boolean;
  specialty: Treatment | null;
  doctor: Doctor | null;
  other_doctors: number;
  cases: SmileCase[];
  reviews: Review[];
  faqs: Faq[];
  /** La línea bajo los botones de la portada: dónde queda, qué días atiende y desde cuánto cuesta la evaluación. */
  facts: { address: string | null; days: string | null; price: string | null };
}

export interface TreatmentsData {
  treatments: Treatment[];
  evaluation: { slug: string } | null;
}

export interface TreatmentData {
  treatment: Treatment & { description_html: string | null; image_url: string | null };
  campaign: (Campaign & { price: string; until_lower: string }) | null;
  booking: { treatment: string; promo: string | null; direct: boolean } | null;
  next_free: NextFree | null;
  doctors: Doctor[];
  faqs: Faq[];
  cases: SmileCase[];
  reviews: Review[];
  others: Treatment[];
}

export interface PromotionsData {
  campaigns: Campaign[];
}

export interface PromotionPackage {
  items: string[];
  normal_price: string | null;
  price: string;
  savings: string | null;
  for_two: boolean;
  payment_options: string[];
  description: string | null;
}

export interface PromotionData {
  campaign: Campaign & {
    description_html: string | null;
    promo_code: string | null;
    has_slots_left: boolean;
    whatsapp_text: string;
    price: { normal: string; now: string } | null;
    package: PromotionPackage | null;
  };
  by_evaluation: boolean;
  booking: Record<string, string> | null;
}

export interface PromotionEndedData {
  ended: string | null;
  campaigns: Campaign[];
}

export interface TeamData {
  doctors: Doctor[];
}

export interface CasesData {
  cases: SmileCase[];
  treatments: { slug: string; name: string }[];
  selected: string | null;
}

export interface ReviewsData {
  reviews: Review[];
}

export interface FaqTopic {
  id: string;
  name: string;
  treatment: { slug: string; art: string } | null;
  faqs: Faq[];
}

export interface FaqsData {
  topics: FaqTopic[];
}

export interface ContactData {
  heading: string;
}

export interface PrivacyData {
  clinic: string;
  address: string | null;
  whatsapp: string | null;
}

export interface Slot {
  time: string;
  label: string;
  doctor_id: number;
  suggested: boolean;
}

export interface PickerDay {
  date: string;
  weekday: string;
  number: number;
  month: string;
  label: string;
}

export interface SlotPicker {
  days?: PickerDay[];
  date: string | null;
  day: string | null;
  slots: Slot[];
}

export interface BookingTreatment extends Omit<Treatment, 'price'> {
  label: string;
  price: { was: string | null; now: string } | null;
  price_line: string | null;
}

export interface FamilyMember {
  id: number;
  name: string;
  first_name: string;
  is_self: boolean;
  in_treatment: boolean;
}

export interface BookingDevice {
  phone: string;
  family: FamilyMember[];
  has_holder: boolean;
  patient_id: number | null;
  privacy_accepted: boolean;
  promotions_accepted: boolean;
}

export interface BookingCampaign {
  name: string;
  discount: string | null;
  treatment: { id: number; name: string } | null;
}

export interface BookingOptions {
  treatments: BookingTreatment[];
  initial_treatment_id: number | null;
  preferred_doctor_id: number | null;
  campaign: BookingCampaign | null;
  promo_code: string;
  promo_problem: string | null;
  campaign_slug: string | null;
  origin: string | null;
  device: BookingDevice | null;
  relationships: { value: string; label: string }[];
}

export interface BookingDays extends SlotPicker {
  doctors: { id: number; name: string }[];
  doctor_id: number | null;
  days: PickerDay[];
}

export interface BookedAppointment {
  /** La dirección propia de "¡Tu cita quedó reservada!" (firmada): recargarla no la pierde. */
  page_url: string;
  treatment: string;
  when: string;
  who: string;
  price_line: string | null;
  phone: string;
  remembered: boolean;
  calendar_url: string;
  whatsapp_message: string;
  origins: { value: string; label: string }[];
  origin_url: string | null;
  /** Escribió un código que no es de ninguna promoción: la cita quedó reservada igual y recepción lo revisa. */
  promo_notice: string | null;
}

export interface PlanCard {
  id: number;
  title: string;
  state: string;
  tone: 'ok' | 'bad' | null;
  who: string;
  included_in: string | null;
  progress: {
    percent: number;
    total: string;
    paid: string;
    remaining: string;
    owed: { label: string; amount: string; late: boolean } | null;
    next: { amount: string; day: string; label: string } | null;
  } | null;
  payment: string | null;
  items_summary: string;
  items: { id: number; label: string; state: string; done: boolean }[];
}

/** Avisos de la familia: si este celular los muestra, en cuántos se activaron y si además van por WhatsApp. */
export interface NoticesState {
  key: string;
  this_phone: boolean;
  phones: number;
  whatsapp: boolean;
}

export type AccountData =
  | { guest: true }
  | {
      guest: false;
      holder: { first_name: string; initials: string } | null;
      several: boolean;
      phone: string;
      elevated: boolean;
      notices: NoticesState | null;
      upcoming: AppointmentCard[];
      plans: PlanCard[] | null;
      balances: { name: string; text: string; owed: boolean }[] | null;
      past: { id: number; date: string; name: string; treatment: string; status: string }[] | null;
      family:
        | {
            id: number;
            name: string;
            relationship: string;
            document: string | null;
            birth_date: string | null;
            email: string | null;
          }[]
        | null;
    };

export interface HealthData {
  patient_id: number;
  title: string;
  lead: string;
  questions: { choice: string; detail: string; question: string; hint: string; label: string; placeholder: string }[];
  conditions_question: string;
  conditions: { key: string; label: string }[];
  also: { key: string; label: string }[];
  notes_hint: string;
}

export type LinkData =
  { kind: 'expired' } | { kind: 'access' } | { kind: 'appointment'; title: string; appointment: AppointmentCard };

export interface ChangeData extends SlotPicker {
  appointment: { what: string; now: string; doctor: string };
  can_change: boolean;
  automatic: boolean;
  fixed_date: boolean;
  max_options: number;
  pending_request: string | null;
  days: PickerDay[];
}
