import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Site } from '@/api/types';

const loaded = {
  team: vi.fn(),
  treatment: vi.fn(),
  booking: vi.fn(),
  cases: vi.fn(),
  reviews: vi.fn(),
  privacy: vi.fn(),
  booked: vi.fn(),
};

/** Algunas de las páginas que se bajan a pedido: dónde está su código, qué exporta y quién avisa al pedirlo. */
const PAGES = [
  ['@/pages/TeamPage', 'TeamPage', loaded.team],
  ['@/pages/TreatmentPage', 'TreatmentPage', loaded.treatment],
  ['@/pages/booking/BookingPage', 'BookingPage', loaded.booking],
  ['@/pages/CasesPage', 'CasesPage', loaded.cases],
  ['@/pages/ReviewsPage', 'ReviewsPage', loaded.reviews],
  ['@/pages/PrivacyPage', 'PrivacyPage', loaded.privacy],
  ['@/pages/booking/BookedPage', 'BookedPage', loaded.booked],
] as const;

/** La web sin casos ni opiniones publicados: esas dos páginas no están en el menú y dan 404. */
const SECTIONS: Site['sections'] = { promotions: true, cases: false, reviews: false, faq: true };

let whenIdle: IdleRequestCallback | undefined;

/** El router recién cargado: ninguna página bajada todavía, y cada una avisa cuando el navegador pide su código. */
async function freshRouter() {
  vi.resetModules();
  for (const [path, name, onLoad] of PAGES) {
    vi.doMock(path, () => {
      onLoad();

      return { [name]: () => null };
    });
  }

  return import('@/app/router');
}

/** El navegador tiene un rato libre: corre lo que se dejó para entonces. */
function idle() {
  whenIdle?.({ didTimeout: false, timeRemaining: () => 50 });
}

beforeEach(() => {
  whenIdle = undefined;
  Object.values(loaded).forEach((page) => page.mockClear());
  vi.stubGlobal('requestIdleCallback', (callback: IdleRequestCallback) => {
    whenIdle = callback;

    return 1;
  });
});

describe('the code of the pages that are not in the main file', () => {
  it('comes in a free moment of the browser, after the first page is painted', async () => {
    const { prefetchPages } = await freshRouter();

    prefetchPages(SECTIONS);
    await new Promise((resolve) => setTimeout(resolve, 100));
    expect(loaded.team).not.toHaveBeenCalled();

    idle();
    await vi.waitFor(() => expect(loaded.team).toHaveBeenCalledOnce());
  });

  it('brings ahead the menu, the booking and a service, but not the sections that are off nor what opens from a link', async () => {
    const { prefetchPages } = await freshRouter();

    prefetchPages(SECTIONS);
    idle();

    await vi.waitFor(() => {
      expect(loaded.booking).toHaveBeenCalledOnce();
      expect(loaded.treatment).toHaveBeenCalledOnce();
    });
    expect(loaded.cases).not.toHaveBeenCalled();
    expect(loaded.reviews).not.toHaveBeenCalled();
    expect(loaded.privacy).not.toHaveBeenCalled();
    expect(loaded.booked).not.toHaveBeenCalled();
  });

  it('brings ahead the cases and the reviews once they are in the menu', async () => {
    const { prefetchPages } = await freshRouter();

    prefetchPages({ ...SECTIONS, cases: true, reviews: true });
    idle();

    await vi.waitFor(() => {
      expect(loaded.cases).toHaveBeenCalledOnce();
      expect(loaded.reviews).toHaveBeenCalledOnce();
    });
  });

  it.each([
    ['the phone is saving data', { saveData: true }],
    ['the signal is very slow', { effectiveType: '2g' }],
    ['the signal is the slowest', { effectiveType: 'slow-2g' }],
  ])('is not brought ahead when %s', async (_case, connection) => {
    const { prefetchPages } = await freshRouter();
    vi.stubGlobal('navigator', { connection });

    prefetchPages(SECTIONS);

    expect(whenIdle).toBeUndefined();
  });
});
