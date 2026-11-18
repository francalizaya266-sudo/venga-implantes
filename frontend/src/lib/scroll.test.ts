import { describe, expect, it } from 'vitest';
import { firstEntryKey } from '@/lib/scroll';

describe('firstEntryKey', () => {
  it('gives the entry the web was opened with a key of its own and keeps it on reload', () => {
    window.history.replaceState({ idx: 0 }, '', '/');

    const key = firstEntryKey();

    expect(key).toMatch(/^opened-/);
    // Lo que el router guardó en la entrada sigue ahí.
    expect(window.history.state).toEqual({ idx: 0, opened: key });
    expect(firstEntryKey()).toBe(key);
  });

  it('gives a new visit a different key, so it starts at the top', () => {
    const first = firstEntryKey();
    window.history.replaceState(null, '', '/');

    expect(firstEntryKey()).not.toBe(first);
  });
});
