import { useCallback } from 'react';
import { useNavigate } from 'react-router';
import { useFlash } from '@/lib/flash';

/**
 * Para lo que termina en otra página: lleva allá y deja arriba el aviso de lo que se acaba de hacer
 * ("La cita quedó confirmada", "Este celular ya no está recordado"). Con `replace`, la página que se deja no
 * queda en el historial: volver atrás no regresa a un formulario que ya se envió.
 */
export function useLeave() {
  const navigate = useNavigate();
  const flash = useFlash();

  return useCallback(
    (to: string, message: string | null, { replace = false }: { replace?: boolean } = {}) => {
      if (message) {
        flash.showOnNextPage({ tone: 'ok', text: message });
      }

      void navigate(to, { replace });
    },
    [navigate, flash],
  );
}
