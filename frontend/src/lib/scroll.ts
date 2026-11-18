/**
 * La clave de la entrada del historial con que se abrió la web. Esa entrada no trae clave propia (para el router
 * siempre es "default"), y con ella una visita nueva empezaría donde quedó la página de la visita anterior. Se le
 * da una suya, guardada en la misma entrada: al recargar o volver con el navegador la página sigue donde estaba,
 * y una visita nueva empieza arriba.
 */
export function firstEntryKey(): string {
  const state = (window.history.state ?? {}) as { opened?: string };

  if (state.opened) {
    return state.opened;
  }

  const opened = `opened-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`;
  window.history.replaceState({ ...state, opened }, '');

  return opened;
}
