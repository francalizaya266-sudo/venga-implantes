/**
 * Imágenes 3D de la web (backend/public/img/3d): la portada, los tres pasos y una por tipo de tratamiento.
 * La API dice cuál le toca a cada tratamiento ("art"); aquí se arma su dirección y sus tamaños.
 */

/** Sube cuando se reemplaza alguna imagen: el celular las guarda un mes. */
const VERSION = 3;

/** Imágenes que dicen una especialidad: son las únicas que tienen el tamaño grande. */
const SPECIALTIES = ['implante', 'brackets', 'endodoncia', 'corona', 'encia', 'ninos'];

/** Dirección de una imagen: "portada", "sonrisa", "implante"… */
export function artUrl(name: string): string {
  return `/img/3d/${name}.webp?v=${VERSION}`;
}

export function hasLargeArt(art: string): boolean {
  return SPECIALTIES.includes(art);
}
