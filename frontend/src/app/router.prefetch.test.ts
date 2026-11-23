import { describe, expect, it, vi } from 'vitest';
import { prefetchPages } from '@/app/router';

const teamPageLoaded = vi.hoisted(() => vi.fn());

// Una de las páginas que se bajan a pedido: avisa cuando el navegador pide su código.
vi.mock('@/pages/TeamPage', () => {
  teamPageLoaded();

  return { TeamPage: () => null };
});

describe('the code of the pages that are not in the main file', () => {
  it('comes in a free moment of the browser, after the first page is painted', async () => {
    let whenIdle: IdleRequestCallback | undefined;
    vi.stubGlobal('requestIdleCallback', (callback: IdleRequestCallback) => {
      whenIdle = callback;

      return 1;
    });

    prefetchPages();
    await new Promise((resolve) => setTimeout(resolve, 100));
    expect(teamPageLoaded).not.toHaveBeenCalled();

    whenIdle?.({ didTimeout: false, timeRemaining: () => 50 });
    await vi.waitFor(() => expect(teamPageLoaded).toHaveBeenCalledOnce());
  });

  it('is not brought ahead when the phone is saving data', () => {
    const requestIdleCallback = vi.fn();
    vi.stubGlobal('requestIdleCallback', requestIdleCallback);
    vi.stubGlobal('navigator', { connection: { saveData: true } });

    prefetchPages();

    expect(requestIdleCallback).not.toHaveBeenCalled();
  });
});
