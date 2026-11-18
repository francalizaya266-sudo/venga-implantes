import { FILLED, STROKED, TOOTH } from '@/components/ui/icon-paths';

export type IconName = keyof typeof STROKED | keyof typeof FILLED;

function isFilled(name: string): name is keyof typeof FILLED {
  return name in FILLED;
}

/** Un ícono de la web por su nombre. Uno que no existe sale como la flecha, igual que en el panel. */
export function Icon({ name }: { name: string }) {
  if (isFilled(name)) {
    const { box, path } = FILLED[name];

    return <svg viewBox={box} fill="currentColor" aria-hidden="true" dangerouslySetInnerHTML={{ __html: path }} />;
  }

  const paths = (STROKED as Record<string, string>)[name] ?? STROKED['arrow-right'];

  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.8"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      dangerouslySetInnerHTML={{ __html: paths }}
    />
  );
}

/** El diente de trazo: la marca del encabezado y del pie. */
export function ToothIcon() {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.6"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      dangerouslySetInnerHTML={{ __html: TOOTH }}
    />
  );
}
