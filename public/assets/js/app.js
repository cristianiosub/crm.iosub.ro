/**
 * CyberCRM — Client-side JS
 * All AJAX requests include CSRF token.
 */

function switchProfile(el) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `<input type="hidden" name="_switch_profile" value="${el.value}"><input type="hidden" name="${document.querySelector('meta[name=csrf-param]')?.content || '_token'}" value="${document.querySelector('meta[name=csrf-token]')?.content || ''}">`;
    document.body.appendChild(form);
    form.submit();
}

function openModal(id) { const m = document.getElementById(id); if (m) { m.classList.add('active'); document.body.style.overflow = 'hidden'; } }
function closeModal(id) { const m = document.getElementById(id); if (m) { m.classList.remove('active'); document.body.style.overflow = ''; } }
document.addEventListener('click', e => { if (e.target.classList.contains('modal-overlay')) { e.target.classList.remove('active'); document.body.style.overflow = ''; } });
document.addEventListener('keydown', e => { if (e.key === 'Escape') { document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active')); document.body.style.overflow = ''; } });

function switchTab(group, tab) {
    document.querySelectorAll(`[data-tab-group="${group}"]`).forEach(el => el.style.display = 'none');
    document.querySelectorAll(`[data-tab-btn="${group}"]`).forEach(el => el.classList.remove('active'));
    const panel = document.getElementById(`tab-${group}-${tab}`);
    if (panel) panel.style.display = '';
    const btn = document.querySelector(`[data-tab-btn="${group}"][data-tab="${tab}"]`);
    if (btn) btn.classList.add('active');
}

function getCSRFToken() { return document.querySelector('meta[name=csrf-token]')?.content || ''; }

async function apiPost(url, data = {}) {
    const fd = new FormData(); fd.append('_token', getCSRFToken());
    for (const [k, v] of Object.entries(data)) fd.append(k, v);
    return (await fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' })).json();
}

async function apiGet(url) { return (await fetch(url, { credentials: 'same-origin' })).json(); }

async function lookupCUI(input, form) {
    const cui = input.value.replace(/\D/g, '');
    if (cui.length < 2) return;
    const btn = input.closest('.form-group')?.querySelector('.btn-lookup');
    if (btn) { btn.textContent = 'Se caută...'; btn.disabled = true; }
    try {
        const data = await apiGet(`${window.APP_URL || ''}/api/anaf-lookup?cui=${encodeURIComponent(cui)}`);
        if (data.success && data.data) {
            const d = data.data;
            for (const [name, val] of Object.entries({ company_name: d.company_name, reg_com: d.reg_com, address: d.address, phone: d.phone })) {
                const el = form.querySelector(`[name="${name}"]`); if (el && val) el.value = val;
            }
        } else { notify('CUI negăsit în baza ANAF.', 'warning'); }
    } catch { notify('Eroare la căutarea CUI.', 'error'); }
    if (btn) { btn.textContent = 'Caută CUI'; btn.disabled = false; }
}

function notify(msg, type = 'success') {
    const div = document.createElement('div'); div.className = `flash flash-${type}`;
    div.textContent = msg; div.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;min-width:320px;max-width:480px;';
    document.body.appendChild(div);
    setTimeout(() => { div.style.opacity = '0'; setTimeout(() => div.remove(), 300); }, 4000);
}

function confirmAction(msg, url) { if (confirm(msg || 'Ești sigur?')) window.location.href = url; }
function execCmd(cmd) { document.execCommand(cmd, false, null); }
function execCmdVal(cmd, val) { if (val) document.execCommand(cmd, false, val); }

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.flash').forEach(el => { setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 5000); });
});
