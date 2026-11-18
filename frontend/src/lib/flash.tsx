import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { useLocation } from 'react-router';

export interface FlashMessage {
  tone: 'ok' | 'bad';
  text: string;
}

interface FlashContextValue {
  message: FlashMessage | null;
  /** Muestra un aviso en la página actual. */
  show: (message: FlashMessage) => void;
  /** Deja un aviso para la página a la que se está por ir ("La cita quedó confirmada"). */
  showOnNextPage: (message: FlashMessage) => void;
  clear: () => void;
}

const FlashContext = createContext<FlashContextValue | null>(null);

/**
 * El aviso de lo que se acaba de hacer o de lo que salió mal. Se ve una vez, arriba de la página, y se va al
 * pasar a otra.
 */
export function FlashProvider({ children }: { children: ReactNode }) {
  const [message, setMessage] = useState<FlashMessage | null>(null);
  const location = useLocation();
  const forNextPage = useRef(false);

  useEffect(() => {
    if (forNextPage.current) {
      forNextPage.current = false;

      return;
    }

    setMessage(null);
  }, [location.key]);

  const show = useCallback((next: FlashMessage) => {
    setMessage(next);
    window.scrollTo({ top: 0 });
  }, []);

  const showOnNextPage = useCallback((next: FlashMessage) => {
    forNextPage.current = true;
    setMessage(next);
  }, []);

  const clear = useCallback(() => setMessage(null), []);

  const value = useMemo(() => ({ message, show, showOnNextPage, clear }), [message, show, showOnNextPage, clear]);

  return <FlashContext.Provider value={value}>{children}</FlashContext.Provider>;
}

export function useFlash(): FlashContextValue {
  const context = useContext(FlashContext);

  if (!context) {
    throw new Error('useFlash se usa dentro de FlashProvider.');
  }

  return context;
}

/** El aviso, donde la página lo muestra. */
export function Flash() {
  const { message } = useFlash();

  if (!message) {
    return null;
  }

  return (
    <div className={`flash ${message.tone}`} role={message.tone === 'ok' ? 'status' : 'alert'}>
      {message.text}
    </div>
  );
}
