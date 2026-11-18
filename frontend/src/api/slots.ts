import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { cacheKey, get } from '@/api/client';
import type { DataResponse, SlotPicker } from '@/api/types';

interface DaySlots {
  date: string | null;
  slots: SlotPicker['slots'];
  /** Se están pidiendo los horarios de otro día. */
  loading: boolean;
}

/**
 * Los horarios del día que se está mirando. Los del primer día ya llegan con la tira de días (`first`); los de
 * otro día se piden a `slotsUrl` cuando el paciente lo toca.
 */
export function useDaySlots(
  first: SlotPicker | undefined,
  date: string | null,
  slotsUrl: (date: string) => string,
): DaySlots {
  const other = first !== undefined && date !== null && date !== first.date ? date : null;
  const url = other ? slotsUrl(other) : null;
  const { data, isFetching } = useQuery<DataResponse<SlotPicker>>({
    queryKey: [url ? cacheKey(url) : 'sin-horarios'],
    queryFn: () => get(url ?? ''),
    enabled: url !== null,
    placeholderData: keepPreviousData,
  });

  if (other === null) {
    return { date: first?.date ?? null, slots: first?.slots ?? [], loading: false };
  }

  // Mientras llegan los de este día no se muestran los de otro: se podría reservar la hora equivocada.
  const ready = data?.data.date === other ? data.data : null;

  return { date: other, slots: ready?.slots ?? [], loading: isFetching || ready === null };
}
