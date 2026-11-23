import { onlineManager } from '@tanstack/react-query';
import { useState } from 'react';
import { useOnline } from '@/api/queries';

/**
 * El hueco de una página mientras llegan sus datos: reserva el alto para que el pie no salte. Sin señal, los datos
 * esperan a que vuelva y el hueco lo dice, en vez de quedar en blanco todo ese rato: la página se completa sola.
 */
export function Loading() {
  const online = useOnline();
  const [stillOffline, setStillOffline] = useState(false);

  if (online) {
    return <div className="page-loading" aria-busy="true" />;
  }

  function tryAgain() {
    // El navegador avisa solo cuando vuelve la señal; si ya dice que hay, se pide ahora mismo.
    if (navigator.onLine) {
      onlineManager.setOnline(true);
    } else {
      setStillOffline(true);
    }
  }

  return (
    <div className="page-loading">
      <div className="panel page-offline">
        <p role="status">
          {stillOffline
            ? 'Todavía no hay señal. La página se cargará sola cuando vuelva.'
            : 'Sin conexión. La página se cargará sola cuando vuelva la señal.'}
        </p>
        <button type="button" className="btn" onClick={tryAgain}>
          Volver a intentar
        </button>
      </div>
    </div>
  );
}
