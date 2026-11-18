import type { Clinic } from '@/api/types';

/** El enlace para escribirle al consultorio por WhatsApp con el mensaje ya empezado, o null si no tiene WhatsApp. */
export function whatsappUrl(clinic: Clinic, text = 'Hola, quisiera información.'): string | null {
  return clinic.whatsapp ? `https://wa.me/${clinic.whatsapp.number}?text=${encodeURIComponent(text)}` : null;
}
