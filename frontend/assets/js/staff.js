'use strict';

const apiRequest = window.jassnetApiRequest;
const loginForm = document.getElementById('staff-login-form');
const statusMessage = document.getElementById('staff-message');
const dashboard = document.getElementById('staff-dashboard');
const staffModules = [
  { label: 'Wateja', permission: 'customers.read', note: 'Taarifa za wateja' },
  { label: 'Mipango', permission: 'plans.manage', note: 'Vifurushi na kasi' },
  { label: 'Akaunti na huduma', permission: 'accounts.read', note: 'PPPoE na Hotspot' },
  { label: 'Malipo', permission: 'payments.read', note: 'Historia na miamala' },
  { label: 'Cashier', permission: 'payments.collect_cash', note: 'Sessions na risiti' },
  { label: 'Tiketi', permission: 'tickets.read', note: 'Msaada kwa wateja' },
  { label: 'Work orders', permission: 'work_orders.manage', note: 'Kazi za mafundi' },
  { label: 'Network', permission: 'network.diagnostics.read', note: 'Health na RADIUS sync' },
  { label: 'Staff', permission: 'users.manage', note: 'Watumiaji na roles' },
  { label: 'Audit', permission: 'audit.read', note: 'Historia ya mabadiliko' },
  { label: 'Settings', permission: 'settings.manage', note: 'System na providers' },
];

function showStatus(text, isError = false) {
  statusMessage.hidden = false;
  statusMessage.textContent = text;
  statusMessage.classList.toggle('is-error', isError);
}

function setButtonBusy(form, busy) {
  const button = form.querySelector('button[type="submit"]');
  button.disabled = busy;
  button.textContent = busy ? 'Inathibitisha…' : 'Ingia →';
}

function renderStaff(profile) {
  document.getElementById('staff-identity').textContent = profile.full_name ? `${profile.full_name} · ${profile.username}` : profile.username;
  const roles = document.getElementById('staff-role-chips');
  roles.replaceChildren();
  for (const role of profile.roles) {
    const chip = document.createElement('span');
    chip.textContent = role;
    roles.append(chip);
  }
  const permissions = new Set(profile.permissions);
  const modules = document.getElementById('staff-modules');
  modules.replaceChildren();
  for (const item of staffModules) {
    if (!permissions.has(item.permission)) continue;
    const card = document.createElement('article');
    card.className = 'staff-module locked';
    const title = document.createElement('strong');
    title.textContent = item.label;
    const note = document.createElement('span');
    note.textContent = `${item.note} · API ya kazi bado haijaunganishwa`;
    card.append(title, note);
    modules.append(card);
  }
  if (!modules.childElementCount) {
    const empty = document.createElement('p');
    empty.className = 'dashboard-notice';
    empty.textContent = 'Role hii haijapangiwa ruhusa zozote. Wasiliana na Super Admin.';
    modules.append(empty);
  }
  loginForm.hidden = true;
  dashboard.hidden = false;
}

async function restoreSession() {
  try {
    const result = await apiRequest('/staff/me');
    renderStaff(result.data);
    statusMessage.hidden = true;
  } catch (error) {
    if (error.status !== 401) showStatus(error.message || 'Seva haipatikani kwa sasa.', true);
  }
}

loginForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const values = new FormData(loginForm);
  const username = values.get('username')?.toString().trim().toLowerCase() || '';
  const password = values.get('password')?.toString() || '';
  if (!/^[a-z][a-z0-9_-]{0,39}\.[a-z][a-z0-9_-]{0,39}$/.test(username) || !password) {
    showStatus('Weka username kwa mfumo firstname.lastname na password.', true);
    return;
  }
  setButtonBusy(loginForm, true);
  statusMessage.hidden = true;
  try {
    await apiRequest('/staff/login', { method: 'POST', body: JSON.stringify({ username, password }) });
    const profile = await apiRequest('/staff/me');
    renderStaff(profile.data);
    loginForm.reset();
  } catch (error) {
    showStatus(error.message || 'Imeshindikana kuingia. Jaribu tena baadaye.', true);
  } finally {
    setButtonBusy(loginForm, false);
  }
});

document.getElementById('staff-logout').addEventListener('click', async () => {
  try {
    const profile = await apiRequest('/staff/me');
    await apiRequest('/staff/logout', { method: 'POST', headers: { 'X-CSRF-Token': profile.data.csrf_token } });
    dashboard.hidden = true;
    loginForm.hidden = false;
    showStatus('Umetoka salama kwenye mfumo.');
  } catch (error) {
    dashboard.hidden = true;
    loginForm.hidden = false;
    showStatus(error.message || 'Session imeisha. Ingia tena.', true);
  }
});

document.getElementById('year').textContent = String(new Date().getFullYear());
restoreSession();
