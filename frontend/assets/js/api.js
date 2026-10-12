'use strict';

// Centralized same-origin-aware API client. Override before loading this file at deployment.
window.JASSNET_API_BASE_URL = window.JASSNET_API_BASE_URL || 'http://localhost/project/backend/public/api/v1';

window.jassnetApiRequest = async function jassnetApiRequest(path, options = {}) {
  const controller = new AbortController();
  const timeout = window.setTimeout(() => controller.abort(), 10000);
  try {
    const response = await fetch(`${window.JASSNET_API_BASE_URL}${path}`, {
      ...options,
      credentials: 'include',
      headers: { Accept: 'application/json', ...(options.body ? { 'Content-Type': 'application/json' } : {}), ...options.headers },
      signal: controller.signal,
    });
    const type = response.headers.get('content-type') || '';
    if (!type.includes('application/json')) throw new Error('Jibu la seva haliko katika muundo unaotarajiwa.');
    const payload = await response.json();
    if (!response.ok || payload.ok === false) {
      const error = new Error(payload.error?.message || 'Ombi halijafanikiwa.');
      error.status = response.status;
      error.code = payload.error?.code;
      throw error;
    }
    return payload;
  } catch (error) {
    if (error.name === 'AbortError') throw new Error('Muda wa kuwasiliana na seva umeisha. Jaribu tena baadaye.');
    if (error instanceof TypeError) throw new Error('Seva haipatikani kwa sasa. Jaribu tena baadaye.');
    throw error;
  } finally {
    window.clearTimeout(timeout);
  }
};
