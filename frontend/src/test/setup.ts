import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach, beforeEach, vi } from 'vitest';
import { resetPreload } from '@/api/client';

beforeEach(() => {
  // jsdom no mueve la página: basta con saber que se pidió.
  vi.spyOn(window, 'scrollTo').mockImplementation(() => {});
  Element.prototype.scrollIntoView = vi.fn();
});

afterEach(() => {
  cleanup();
  vi.unstubAllGlobals();
  resetPreload();
  document.getElementById('preload')?.remove();
  document.head.querySelectorAll('[data-page-meta]').forEach((element) => element.remove());
  document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
  window.sessionStorage.clear();
  window.history.replaceState(null, '', '/');
});
