'use strict';

// Centralized JSON API client. The backend base URL can be overridden at deployment.
const API_BASE_URL = window.JASSNET_API_BASE_URL || 'http://localhost/project/backend/public/api/v1';

async function apiRequest(path, options = {}) {
  const controller = new AbortController();
  const timeout = window.setTimeout(() => controller.abort(), 10000);
  try {
    const response = await fetch(`${API_BASE_URL}${path}`, {
      ...options,
      credentials: 'include',
      headers: { Accept: 'application/json', ...(options.body ? { 'Content-Type': 'application/json' } : {}), ...options.headers },
      signal: controller.signal,
    });
    const type = response.headers.get('content-type') || '';
    if (!type.includes('application/json')) throw new Error('Jibu la seva haliko katika muundo unaotarajiwa.');
    const payload = await response.json();
    if (!response.ok || payload.ok === false) throw new Error(payload.error?.message || 'Ombi halijafanikiwa.');
    return payload;
  } catch (error) {
    if (error.name === 'AbortError') throw new Error('Muda wa kuwasiliana na seva umeisha. Jaribu tena baadaye.');
    if (error instanceof TypeError) throw new Error('Seva haipatikani kwa sasa. Jaribu tena baadaye.');
    throw error;
  } finally {
    window.clearTimeout(timeout);
  }
}

const year = document.getElementById('year');
if (year) year.textContent = String(new Date().getFullYear());

const form = document.getElementById('otp-form');
const message = document.getElementById('form-message');
form?.addEventListener('submit', async (event) => {
  event.preventDefault();
  const accountNumber = new FormData(form).get('accountNumber')?.toString().trim() || '';
  if (!/^[0-9A-Za-z-]{4,32}$/.test(accountNumber)) {
    message.hidden = false;
    message.textContent = 'Weka namba halali ya akaunti yako.';
    return;
  }

  const submitButton = form.querySelector('button[type="submit"]');
  submitButton.disabled = true;
  submitButton.textContent = 'Inatuma ombi…';
  message.hidden = true;
  try {
    await apiRequest('/portal/otp/request', {
      method: 'POST',
      body: JSON.stringify({ account_number: accountNumber }),
    });
    message.textContent = 'Ikiwa akaunti inastahili, OTP itatumwa kwenye simu iliyosajiliwa. Angalia ujumbe wako.';
  } catch (error) {
    message.textContent = error.message || 'Ombi halijafanikiwa. Jaribu tena baadaye.';
  } finally {
    message.hidden = false;
    submitButton.disabled = false;
    submitButton.innerHTML = 'Tuma OTP kwenye simu <span>→</span>';
  }
});
