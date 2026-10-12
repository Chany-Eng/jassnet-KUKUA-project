'use strict';

const apiRequest = window.jassnetApiRequest;
const packageState = document.getElementById('package-state');
const packageGrid = document.getElementById('package-grid');
const packageEmpty = document.getElementById('package-empty');
const selectedPackage = document.getElementById('selected-package');

function formatMoney(minor, currency) {
  try {
    return new Intl.NumberFormat('sw-TZ', { style: 'currency', currency, maximumFractionDigits: 0 }).format(minor);
  } catch {
    return `${new Intl.NumberFormat('sw-TZ').format(minor)} ${currency}`;
  }
}

function formatSpeed(kbps) {
  if (kbps >= 1000 && kbps % 1000 === 0) return `${kbps / 1000} Mbps`;
  return `${kbps} Kbps`;
}

function formatCap(bytes) {
  if (bytes === null || bytes === undefined) return 'Bila kikomo';
  const gb = bytes / (1024 ** 3);
  return `${Number.isInteger(gb) ? gb : gb.toFixed(1)} GB`;
}

function makeText(tag, className, text) {
  const element = document.createElement(tag);
  if (className) element.className = className;
  element.textContent = text;
  return element;
}

function renderPackages(packages) {
  packageState.hidden = true;
  packageGrid.replaceChildren();
  packageGrid.hidden = false;
  packages.forEach((plan, index) => {
    const card = document.createElement('button');
    card.type = 'button';
    card.className = 'package-card';
    card.setAttribute('aria-pressed', 'false');
    card.setAttribute('aria-label', `Chagua kifurushi ${plan.name}, ${formatMoney(plan.price_minor, plan.currency)}`);
    if (index === 1) card.append(makeText('span', 'popular-label', 'CHAGUO MAARUFU'));
    card.append(makeText('h3', '', plan.name));
    const price = document.createElement('div');
    price.className = 'package-price';
    price.append(makeText('strong', '', formatMoney(plan.price_minor, plan.currency)), makeText('span', '', 'kwa kifurushi'));
    card.append(price);
    const details = document.createElement('div');
    details.className = 'package-details';
    [
      ['Kasi ya kupakua', formatSpeed(plan.speed_down_kbps)],
      ['Kasi ya kupakia', formatSpeed(plan.speed_up_kbps)],
      ['Muda', `${plan.validity_days} ${plan.validity_days === 1 ? 'siku' : 'siku'}`],
      ['Data', formatCap(plan.data_cap_bytes)],
    ].forEach(([label, value]) => {
      const row = document.createElement('span');
      row.append(makeText('span', '', label), makeText('b', '', value));
      details.append(row);
    });
    card.append(details);
    card.addEventListener('click', () => {
      packageGrid.querySelectorAll('.package-card').forEach((item) => item.setAttribute('aria-pressed', String(item === card)));
      selectedPackage.textContent = `${plan.name} · ${formatMoney(plan.price_minor, plan.currency)} · ${plan.validity_days} siku`;
    });
    packageGrid.append(card);
  });
}

async function loadPackages() {
  packageState.hidden = false;
  packageState.textContent = 'Inapakia vifurushi...';
  try {
    const result = await apiRequest('/hotspot/packages');
    const packages = Array.isArray(result.data.packages) ? result.data.packages : [];
    if (packages.length === 0) {
      packageState.hidden = true;
      packageEmpty.hidden = false;
      return;
    }
    packageEmpty.hidden = true;
    renderPackages(packages);
  } catch (error) {
    packageState.textContent = error.message || 'Vifurushi havipatikani kwa sasa.';
    packageState.setAttribute('data-error', 'true');
  }
}

document.getElementById('year').textContent = String(new Date().getFullYear());
loadPackages();
