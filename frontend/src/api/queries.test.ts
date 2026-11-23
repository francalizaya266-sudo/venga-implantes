import { QueryClient } from '@tanstack/react-query';
import { describe, expect, it } from 'vitest';
import { ApiError } from '@/api/client';
import { cachedSite, pageError, retry } from '@/api/queries';
import { site } from '@/test/fixtures';

const offline = new TypeError('Failed to fetch');
const notFound = new ApiError(404, { message: 'No encontramos esta página' });

describe('retry', () => {
  it('tries again after a network failure, up to twice', () => {
    expect(retry(0, offline)).toBe(true);
    expect(retry(1, offline)).toBe(true);
    expect(retry(2, offline)).toBe(false);
  });

  it('takes an answer of the API as final', () => {
    expect(retry(0, notFound)).toBe(false);
  });
});

describe('pageError', () => {
  it('keeps the page on screen when the network drops while asking for it again', () => {
    expect(pageError({ data: 'la página' }, offline)).toBeNull();
  });

  it('shows the network failure when there is nothing to show yet', () => {
    expect(pageError(undefined, offline)).toBe(offline);
  });

  it('shows what the API answered even with the page on screen', () => {
    expect(pageError({ data: 'la página' }, notFound)).toBe(notFound);
  });
});

describe('cachedSite', () => {
  it('gives the data of the clinic that the document brought written, as main.tsx leaves them', () => {
    const client = new QueryClient();
    client.setQueryData(['/api/v1/site'], { data: site() });

    expect(cachedSite(client)?.clinic.name).toBe('Clínica Vega');
  });

  it('gives nothing while they have not arrived', () => {
    expect(cachedSite(new QueryClient())).toBeUndefined();
  });
});
