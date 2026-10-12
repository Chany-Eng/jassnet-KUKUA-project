'use strict';

const apiRequest = window.jassnetApiRequest;

const year = document.getElementById('year');
if (year) year.textContent = String(new Date().getFullYear());

const form = document.getElementById('otp-form');
const verifyForm = document.getElementById('verify-form');
const message = document.getElementById('form-message');
const dashboard = document.getElementById('portal-dashboard');
const accountInput = document.getElementById('account-number');
const changeAccount = document.getElementById('change-account');
let accountNumber = '';
let csrfToken = '';

function showMessage(text, error = false) {
  message.hidden = false;
  message.textContent = text;
  message.classList.toggle('is-error', error);
}

function setBusy(targetForm, busy, label) {
  const button = targetForm.querySelector('button[type="submit"]');
  if (!button) return;
  button.disabled = busy;
  if (busy) button.dataset.originalLabel = button.textContent;
  button.textContent = busy ? label : button.dataset.originalLabel || label;
}

function appendEntry(container, title, detail) {
  const entry = document.createElement('div');
  entry.className = 'portal-entry';
  const heading = document.createElement('strong');
  heading.textContent = title;
  const description = document.createElement('span');
  description.textContent = detail;
  entry.append(heading, description);
  container.append(entry);
}

function formatDate(value) {
  if (!value) return 'Haijawekwa';
  const date = new Date(`${value.replace(' ', 'T')}Z`);
  return Number.isNaN(date.getTime()) ? 'Haijawekwa' : new Intl.DateTimeFormat('sw-TZ', { dateStyle: 'medium' }).format(date);
}

async function loadDashboard() {
  const result = await apiRequest('/portal/me');
  csrfToken = result.data.csrf_token;
  const account = result.data.account;
  const accountPanel = document.getElementById('portal-account');
  accountPanel.replaceChildren();
  appendEntry(accountPanel, `Akaunti ${account.account_number}`, `Hali: ${account.service_status} · Kifurushi: ${account.plan || 'Haijawekwa'}`);
  appendEntry(accountPanel, 'Kasi ya huduma', `Upakuaji ${account.speed_down_kbps || '—'} kbps · Upakiaji ${account.speed_up_kbps || '—'} kbps`);
  appendEntry(accountPanel, 'Usajili', `${formatDate(account.starts_at)} hadi ${formatDate(account.ends_at)} · Malipo: ${account.payment_status || 'pending'} · Huduma: ${account.provisioning_status || 'pending'}`);

  const payments = document.getElementById('portal-payments');
  payments.replaceChildren();
  for (const payment of result.data.payments || []) {
    appendEntry(payments, `${payment.amount_minor} ${payment.currency} · ${payment.payment_status}`, `${formatDate(payment.created_at)} · Risiti itapatikana baada ya huduma ya risiti kuunganishwa.`);
  }
  if (!payments.childElementCount) appendEntry(payments, 'Hakuna malipo ya kuonyesha', 'Historia yako itaonekana hapa.');

  const receipts = document.getElementById('portal-receipts');
  receipts.replaceChildren();
  for (const receipt of result.data.receipts || []) {
    appendEntry(receipts, `${receipt.receipt_number} · ${receipt.amount_minor} ${receipt.currency}`, formatDate(receipt.issued_at));
  }
  if (!receipts.childElementCount) appendEntry(receipts, 'Hakuna risiti', 'Risiti zilizotolewa zitaonekana hapa.');

  const tickets = document.getElementById('portal-tickets');
  tickets.replaceChildren();
  for (const ticket of result.data.tickets || []) appendEntry(tickets, ticket.subject, `${ticket.status} · ${ticket.priority}`);
  if (!tickets.childElementCount) appendEntry(tickets, 'Hakuna tiketi', 'Tiketi za msaada zitaonekana hapa.');
  form.hidden = true;
  verifyForm.hidden = true;
  changeAccount.hidden = true;
  dashboard.hidden = false;
}

form?.addEventListener('submit', async (event) => {
  event.preventDefault();
  accountNumber = new FormData(form).get('accountNumber')?.toString().trim() || '';
  if (!/^[0-9]{4,32}$/.test(accountNumber)) {
    showMessage('Weka namba halali ya akaunti yako.', true);
    return;
  }
  setBusy(form, true, 'Inatuma ombi…');
  message.hidden = true;
  try {
    await apiRequest('/portal/otp/request', { method: 'POST', body: JSON.stringify({ account_number: accountNumber }) });
    verifyForm.hidden = false;
    changeAccount.hidden = false;
    showMessage('Ikiwa akaunti inastahili, OTP itatumwa kwenye simu iliyosajiliwa. Ujumbe huu hauhakikishi kuwa SMS imewasilishwa.');
  } catch (error) {
    showMessage(error.message || 'Ombi halijafanikiwa. Jaribu tena baadaye.', true);
  } finally {
    setBusy(form, false, 'Tuma OTP kwenye simu →');
  }
});

verifyForm?.addEventListener('submit', async (event) => {
  event.preventDefault();
  const otp = new FormData(verifyForm).get('otp')?.toString().trim() || '';
  if (!/^[0-9]{6}$/.test(otp)) {
    showMessage('Weka OTP ya tarakimu 6.', true);
    return;
  }
  setBusy(verifyForm, true, 'Inathibitisha…');
  message.hidden = true;
  try {
    await apiRequest('/portal/otp/verify', { method: 'POST', body: JSON.stringify({ account_number: accountNumber, otp }) });
    await loadDashboard();
    showMessage('Umeingia salama kwenye akaunti yako.');
  } catch (error) {
    showMessage(error.message || 'OTP si sahihi au muda wake umeisha.', true);
  } finally {
    setBusy(verifyForm, false, 'Thibitisha OTP →');
    verifyForm.reset();
  }
});

changeAccount?.addEventListener('click', () => {
  accountNumber = '';
  verifyForm.hidden = true;
  changeAccount.hidden = true;
  message.hidden = true;
  form.hidden = false;
  accountInput.focus();
});

document.getElementById('logout-button')?.addEventListener('click', async () => {
  try {
    await apiRequest('/portal/logout', { method: 'POST', headers: { 'X-CSRF-Token': csrfToken } });
    dashboard.hidden = true;
    form.reset();
    verifyForm.reset();
    form.hidden = false;
    accountNumber = '';
    showMessage('Umetoka salama kwenye akaunti.');
  } catch (error) {
    showMessage(error.message || 'Imeshindikana kutoka. Jaribu tena.', true);
  }
});

// Recover the session on reload; protected routes reveal only the signed-in account.
loadDashboard().then(() => { message.hidden = true; }).catch((error) => {
  if (error.message && !error.message.includes('Tafadhali ingia tena')) showMessage(error.message, true);
});
