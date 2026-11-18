import { artUrl, hasLargeArt } from '@/lib/art';

/**
 * La imagen de un tratamiento, al tamaño en que se muestra (en píxeles de CSS). Va en tres tamaños y cada
 * pantalla baja el que le queda justo. Es un adorno junto al nombre: va sin texto alternativo.
 */
export function TreatmentArt({ art, size, lazy = true }: { art: string; size: number; lazy?: boolean }) {
  return (
    <img
      src={artUrl(`${art}-192`)}
      srcSet={`${artUrl(`${art}-192`)} 192w, ${artUrl(`${art}-320`)} 320w, ${artUrl(art)} 640w`}
      sizes={`${size}px`}
      width={size}
      height={size}
      alt=""
      loading={lazy ? 'lazy' : undefined}
      decoding="async"
    />
  );
}

/** La imagen de la franja "Nuestra especialidad", que la muestra en grande. */
export function FeatureArt({ art }: { art: string }) {
  const large = hasLargeArt(art);

  return (
    <img
      src={artUrl(art)}
      srcSet={large ? `${artUrl(art)} 640w, ${artUrl(`${art}-960`)} 960w` : undefined}
      sizes={large ? '(min-width: 900px) 400px, (min-width: 640px) 300px, 240px' : undefined}
      width={640}
      height={640}
      alt=""
      loading="lazy"
      decoding="async"
    />
  );
}

/**
 * Una capa del implante de la portada ("implante-portada" o su cinta de luz), en dos tamaños: el celular baja
 * la chica. Con foto en la portada el implante solo se ve en pantallas anchas: ahí se pide cuando hace falta.
 */
export function CoverLayer({ name, lazy }: { name: string; lazy: boolean }) {
  return (
    <img
      src={artUrl(`${name}-640`)}
      srcSet={`${artUrl(`${name}-640`)} 640w, ${artUrl(name)} 1120w`}
      sizes="(min-width: 900px) 440px, 320px"
      width={1120}
      height={1120}
      alt=""
      loading={lazy ? 'lazy' : undefined}
      fetchPriority={lazy ? undefined : 'high'}
    />
  );
}
