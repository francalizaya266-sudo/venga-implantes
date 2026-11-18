import { useQueryClient, type QueryClient } from '@tanstack/react-query';
import { useCallback, useState } from 'react';
import { ApiError, post } from '@/api/client';
import type { MessageResponse } from '@/api/types';
import { useFlash } from '@/lib/flash';

/** Lo que se le dice al paciente cuando la petición ni siquiera llegó (sin señal, servidor caído). */
export const NETWORK_PROBLEM = 'No pudimos conectarnos. Revisa tu conexión e inténtalo de nuevo.';

export function problemOf(error: unknown): string {
  return error instanceof ApiError ? error.message : NETWORK_PROBLEM;
}

/**
 * Después de una acción, nada de lo que se guardó antes se vuelve a mostrar: lo que no está a la vista se
 * descarta y lo que sí, se pide de nuevo.
 */
export async function refresh(queryClient: QueryClient): Promise<void> {
  queryClient.removeQueries({ type: 'inactive' });
  await queryClient.invalidateQueries();
}

interface Options {
  /** Qué hacer con el mensaje de la API en vez de mostrarlo arriba de la página (por ejemplo, llevarlo a otra). */
  onDone?: (message: string | null) => void;
  /** Qué hacer cuando la API dice que no (el horario se ocupó), en vez de mostrarlo arriba de la página. */
  onRejected?: (problem: string) => void;
}

/**
 * Una acción del paciente (confirmar, cancelar, pedir su sesión…): la envía, vuelve a pedir lo que la página
 * muestra y avisa cómo quedó. Mientras viaja, `busy` deja el botón sin tocar dos veces. Si la API no acepta
 * algún campo, `errors` trae qué decir junto a cada uno y `fix` lo retira cuando el paciente lo corrige.
 */
export function useAction() {
  const queryClient = useQueryClient();
  const flash = useFlash();
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const run = useCallback(
    async (url: string, data: unknown = {}, { onDone, onRejected }: Options = {}): Promise<boolean> => {
      setBusy(true);
      setErrors({});
      // El aviso de lo anterior ya no viene al caso.
      flash.clear();

      try {
        const { message } = await post<MessageResponse>(url, data);
        await refresh(queryClient);

        if (onDone) {
          onDone(message);
        } else if (message) {
          flash.show({ tone: 'ok', text: message });
        }

        return true;
      } catch (error) {
        const invalid = error instanceof ApiError ? error.fieldErrors : {};

        if (Object.keys(invalid).length) {
          setErrors(invalid);

          return false;
        }

        // Si la API dijo que no, es que algo cambió (el horario se ocupó, la cita ya pasó): se pide de nuevo.
        if (error instanceof ApiError) {
          await refresh(queryClient);
        }

        if (error instanceof ApiError && onRejected) {
          onRejected(error.message);
        } else {
          flash.show({ tone: 'bad', text: problemOf(error) });
        }

        return false;
      } finally {
        setBusy(false);
      }
    },
    [queryClient, flash],
  );

  const fix = useCallback((field: string) => {
    setErrors((current) => {
      if (!(field in current)) {
        return current;
      }

      const rest = { ...current };
      delete rest[field];

      return rest;
    });
  }, []);

  return { run, busy, errors, fix };
}
