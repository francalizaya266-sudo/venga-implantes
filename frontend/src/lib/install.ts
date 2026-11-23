import { useSyncExternalStore } from 'react';

/**
 * La web como app: Chrome (Android, computadora) avisa una vez, al cargar, que se puede instalar. Ese aviso se guarda
 * para ofrecer "Instalar" más tarde, desde un botón de Mi cuenta. En iPhone no existe: se agrega a mano a la pantalla
 * de inicio.
 */
interface InstallPrompt extends Event {
  prompt(): Promise<void>;
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

let deferred: InstallPrompt | null = null;
const listeners = new Set<() => void>();

function changed(): void {
  listeners.forEach((listener) => listener());
}

/** Se llama una vez al abrir la web: el aviso llega antes de que el paciente vaya a Mi cuenta. */
export function listenForInstall(): void {
  window.addEventListener('beforeinstallprompt', (event) => {
    // Sin la barra que Chrome mostraría por su cuenta: se ofrece en Mi cuenta, donde se explica para qué sirve.
    event.preventDefault();
    deferred = event as InstallPrompt;
    changed();
  });

  window.addEventListener('appinstalled', () => {
    deferred = null;
    changed();
  });
}

function subscribe(listener: () => void): () => void {
  listeners.add(listener);

  return () => listeners.delete(listener);
}

/** Si se puede ofrecer "Instalar", y la acción que abre la ventana del navegador para hacerlo. */
export function useInstall(): { canInstall: boolean; install: () => Promise<boolean> } {
  const canInstall = useSyncExternalStore(
    subscribe,
    () => deferred !== null,
    () => false,
  );

  return {
    canInstall,
    install: async () => {
      if (!deferred) {
        return false;
      }

      const prompt = deferred;
      await prompt.prompt();
      const { outcome } = await prompt.userChoice;
      // El aviso se usa una sola vez.
      deferred = null;
      changed();

      return outcome === 'accepted';
    },
  };
}
