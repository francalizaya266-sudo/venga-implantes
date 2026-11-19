import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach, beforeEach, expect, vi } from 'vitest';
import { resetPreload } from '@/api/client';
import { takeContractViolations } from '@/test/app';

beforeEach(() => {
  // jsdom no mueve la página: basta con saber que se pidió.
  vi.spyOn(window, 'scrollTo').mockImplementation(() => {});
  Element.prototype.scrollIntoView = vi.fn();
});

afterEach(() => {
  const violations = takeContractViolations();
  cleanup();
  vi.unstubAllGlobals();
  resetPreload();
  document.getElementById('preload')?.remove();
  document.head.querySelectorAll('[data-page-meta]').forEach((element) => element.remove());
  document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
  window.sessionStorage.clear();
  window.history.replaceState(null, '', '/');

  // Lo que respondió la API de mentira tiene que cumplir docs/api/openapi.json, como lo que responde Laravel.
  expect(violations, 'respuestas de prueba que la API no da').toEqual([]);
});
