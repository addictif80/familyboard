// ============================================
// FamilyBoard - Suivi salarié
// ============================================

function switchEmploymentTab(tab) {
    document.querySelectorAll('.settings-tab-panel').forEach(p => p.classList.toggle('active', p.dataset.tab === tab));
    document.querySelectorAll('.settings-tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
    if (history.replaceState) history.replaceState(null, '', '#tab-' + tab);
}
(function () {
    const hash = location.hash.replace('#', '');
    if (hash.startsWith('tab-') && document.querySelector(`.settings-tab-panel[data-tab="${hash.slice(4)}"]`)) {
        switchEmploymentTab(hash.slice(4));
    }
})();

// ---- Profil ----

function toggleMonthlyGrossField() {
    const isMonthly = document.getElementById('profile-pay-mode').value === 'monthly';
    document.getElementById('profile-monthly-gross-wrap').style.display = isMonthly ? '' : 'none';
}

function openNewProfileModal() {
    document.getElementById('profile-modal-title').textContent = 'Nouveau profil salarié';
    document.getElementById('profile-id').value = '';
    document.getElementById('profile-siren').value = '';
    document.getElementById('profile-employer-name').value = '';
    document.getElementById('profile-employer-address').value = '';
    document.getElementById('profile-job-title').value = '';
    document.getElementById('profile-contract-type').value = 'cdi';
    document.getElementById('profile-hire-date').value = '';
    document.getElementById('profile-trial-end').value = '';
    document.getElementById('profile-color').value = '#4A90D9';
    document.getElementById('profile-pay-mode').value = 'hourly';
    document.getElementById('profile-hourly-rate').value = '';
    document.getElementById('profile-monthly-gross').value = '';
    document.getElementById('profile-weekly-hours').value = '35';
    document.getElementById('profile-overtime-threshold').value = '8';
    document.getElementById('profile-overtime-rate1').value = '25';
    document.getElementById('profile-overtime-rate2').value = '50';
    document.getElementById('profile-reset-day').value = '1';
    document.getElementById('profile-reset-month').value = '6';
    document.getElementById('profile-leave-accrual').value = '2.5';
    document.getElementById('profile-rtt-per-year').value = '0';
    document.getElementById('profile-cotisation-rate').value = '';
    document.getElementById('profile-pas-rate').value = '';
    document.getElementById('siren-lookup-result').innerHTML = '';
    toggleMonthlyGrossField();
    openModal('profile-modal');
}

function openEditProfileModal() {
    if (!SELECTED_PROFILE) return;
    const p = SELECTED_PROFILE;
    document.getElementById('profile-modal-title').textContent = "Modifier le profil";
    document.getElementById('profile-id').value = p.id;
    document.getElementById('profile-user-id').value = p.user_id;
    document.getElementById('profile-siren').value = p.employer_siren || '';
    document.getElementById('profile-employer-name').value = p.employer_name || '';
    document.getElementById('profile-employer-address').value = p.employer_address || '';
    document.getElementById('profile-job-title').value = p.job_title || '';
    document.getElementById('profile-contract-type').value = p.contract_type;
    document.getElementById('profile-hire-date').value = p.hire_date || '';
    document.getElementById('profile-trial-end').value = p.trial_period_end || '';
    document.getElementById('profile-color').value = p.color || '#4A90D9';
    document.getElementById('profile-pay-mode').value = p.pay_mode;
    document.getElementById('profile-hourly-rate').value = p.hourly_rate_cents ? (p.hourly_rate_cents / 100).toFixed(2) : '';
    document.getElementById('profile-monthly-gross').value = p.monthly_gross_cents ? (p.monthly_gross_cents / 100).toFixed(2) : '';
    document.getElementById('profile-weekly-hours').value = p.contractual_weekly_hours;
    document.getElementById('profile-overtime-threshold').value = p.overtime_threshold_hours;
    document.getElementById('profile-overtime-rate1').value = p.overtime_rate1_pct;
    document.getElementById('profile-overtime-rate2').value = p.overtime_rate2_pct;
    document.getElementById('profile-reset-day').value = p.leave_reset_day;
    document.getElementById('profile-reset-month').value = p.leave_reset_month;
    document.getElementById('profile-leave-accrual').value = p.leave_accrual_days_per_month;
    document.getElementById('profile-rtt-per-year').value = p.rtt_days_per_year;
    document.getElementById('profile-cotisation-rate').value = p.cotisation_rate_pct ?? '';
    document.getElementById('profile-pas-rate').value = p.pas_rate_pct ?? '';
    document.getElementById('siren-lookup-result').innerHTML = '';
    toggleMonthlyGrossField();
    openModal('profile-modal');
}

async function lookupSiren() {
    const siren = document.getElementById('profile-siren').value.trim();
    const resultEl = document.getElementById('siren-lookup-result');
    if (siren.length !== 9) { Dialog.toast('Le SIREN doit contenir 9 chiffres.', 'error'); return; }
    resultEl.innerHTML = '<small>Recherche…</small>';
    const r = await apiFetch(`${BASE_URL}/api/employment/siren-lookup?siren=${encodeURIComponent(siren)}`);
    if (r.success) {
        document.getElementById('profile-employer-name').value = r.name;
        document.getElementById('profile-employer-address').value = r.address;
        resultEl.innerHTML = '<small style="color:var(--success)">✅ Entreprise trouvée, champs pré-remplis.</small>';
    } else {
        resultEl.innerHTML = `<small style="color:var(--danger)">${r.error || 'Entreprise introuvable.'}</small>`;
    }
}

async function saveProfile() {
    const userId = document.getElementById('profile-user-id').value;
    const hourlyRate = document.getElementById('profile-hourly-rate').value;
    if (!userId || !hourlyRate) { Dialog.toast('Membre et taux horaire requis.', 'error'); return; }
    const payload = {
        user_id: userId,
        employer_siren: document.getElementById('profile-siren').value,
        employer_name: document.getElementById('profile-employer-name').value,
        employer_address: document.getElementById('profile-employer-address').value,
        job_title: document.getElementById('profile-job-title').value,
        contract_type: document.getElementById('profile-contract-type').value,
        hire_date: document.getElementById('profile-hire-date').value,
        trial_period_end: document.getElementById('profile-trial-end').value,
        color: document.getElementById('profile-color').value,
        pay_mode: document.getElementById('profile-pay-mode').value,
        hourly_rate: hourlyRate,
        monthly_gross: document.getElementById('profile-monthly-gross').value,
        contractual_weekly_hours: document.getElementById('profile-weekly-hours').value,
        overtime_threshold_hours: document.getElementById('profile-overtime-threshold').value,
        overtime_rate1_pct: document.getElementById('profile-overtime-rate1').value,
        overtime_rate2_pct: document.getElementById('profile-overtime-rate2').value,
        leave_reset_day: document.getElementById('profile-reset-day').value,
        leave_reset_month: document.getElementById('profile-reset-month').value,
        leave_accrual_days_per_month: document.getElementById('profile-leave-accrual').value,
        rtt_days_per_year: document.getElementById('profile-rtt-per-year').value,
        cotisation_rate_pct: document.getElementById('profile-cotisation-rate').value,
        pas_rate_pct: document.getElementById('profile-pas-rate').value,
    };
    const id = document.getElementById('profile-id').value;
    const url = id ? `${BASE_URL}/api/employment/profiles/${id}` : `${BASE_URL}/api/employment/profiles`;
    const r = await apiFetch(url, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) {
        window.location.href = `${BASE_URL}/employment?id=${id || r.id}`;
    } else {
        Dialog.toast(r.error || 'Erreur.', 'error');
    }
}

async function deleteProfile() {
    const ok = await Dialog.confirm('Supprimer ce profil et toutes ses données (planning, congés, paie, arrêts…) ? Cette action est irréversible.');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/employment/profiles/${PROFILE_ID}/delete`, { method: 'POST' });
    if (r.success) window.location.href = `${BASE_URL}/employment`;
}

// ---- Planning ----

function toggleSchedDay(day) {
    const checked = document.querySelector(`.sched-day-enabled[data-day="${day}"]`).checked;
    document.getElementById(`sched-day-${day}-fields`).style.display = checked ? '' : 'none';
}

async function saveSchedule() {
    const days = {};
    document.querySelectorAll('.sched-day-enabled').forEach(cb => {
        const day = cb.dataset.day;
        if (cb.checked) {
            days[day] = {
                start: document.querySelector(`.sched-start[data-day="${day}"]`).value,
                end: document.querySelector(`.sched-end[data-day="${day}"]`).value,
                break: document.querySelector(`.sched-break[data-day="${day}"]`).value || 0,
            };
        }
    });
    const r = await apiFetch(`${BASE_URL}/api/employment/profiles/${PROFILE_ID}/schedule`, { method: 'POST', body: JSON.stringify({ days }) });
    if (r.success) { Dialog.toast('Planning enregistré.'); window.location.reload(); }
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function addException() {
    const date = document.getElementById('exception-date').value;
    if (!date) { Dialog.toast('Date requise.', 'error'); return; }
    const payload = {
        date,
        hours: document.getElementById('exception-hours').value,
        note: document.getElementById('exception-note').value,
    };
    const r = await apiFetch(`${BASE_URL}/api/employment/profiles/${PROFILE_ID}/exceptions`, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function deleteException(id) {
    const ok = await Dialog.confirm('Supprimer ce correctif ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/employment/profiles/${PROFILE_ID}/exceptions/${id}/delete`, { method: 'POST' });
    if (r.success) window.location.reload();
}

// ---- Congés / RTT ----

async function addLeaveAdjustment(type) {
    const prefix = type === 'rtt' ? 'rtt' : 'cp';
    const days = document.getElementById(`${prefix}-adj-days`).value;
    if (!days) { Dialog.toast('Nombre de jours requis.', 'error'); return; }
    const payload = {
        leave_type: type,
        date: document.getElementById(`${prefix}-adj-date`).value,
        days,
        note: document.getElementById(`${prefix}-adj-note`).value,
    };
    const r = await apiFetch(`${BASE_URL}/api/employment/profiles/${PROFILE_ID}/leave-adjustments`, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function deleteLeaveAdjustment(id) {
    const ok = await Dialog.confirm('Supprimer cet ajustement ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/employment/profiles/${PROFILE_ID}/leave-adjustments/${id}/delete`, { method: 'POST' });
    if (r.success) window.location.reload();
}

// ---- Arrêts de travail ----

async function addSickLeave() {
    const start = document.getElementById('sick-start').value;
    const end = document.getElementById('sick-end').value;
    if (!start || !end) { Dialog.toast('Dates de début et de fin requises.', 'error'); return; }
    const payload = {
        start_date: start,
        end_date: end,
        reason: document.getElementById('sick-reason').value,
        ijss_total: document.getElementById('sick-ijss').value,
        employer_complement: document.getElementById('sick-complement').value,
        notes: document.getElementById('sick-notes').value,
    };
    const r = await apiFetch(`${BASE_URL}/api/employment/profiles/${PROFILE_ID}/sick-leaves`, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function deleteSickLeave(id) {
    const ok = await Dialog.confirm('Supprimer cet arrêt de travail ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/employment/profiles/${PROFILE_ID}/sick-leaves/${id}/delete`, { method: 'POST' });
    if (r.success) window.location.reload();
}

// ---- Paie ----

function reloadPayPeriod() {
    const month = document.getElementById('pay-month').value;
    const year = document.getElementById('pay-year').value;
    window.location.href = `${BASE_URL}/employment?id=${PROFILE_ID}&year=${year}&month=${month}#tab-paie`;
}

async function computePayslip() {
    const payload = {
        year: document.getElementById('pay-year').value,
        month: document.getElementById('pay-month').value,
    };
    const r = await apiFetch(`${BASE_URL}/api/employment/profiles/${PROFILE_ID}/compute-payslip`, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function addPrime() {
    const label = document.getElementById('prime-label').value.trim();
    const amount = document.getElementById('prime-amount').value;
    if (!label || !amount) { Dialog.toast('Libellé et montant requis.', 'error'); return; }
    const payload = {
        label, amount,
        year: document.getElementById('pay-year').value,
        month: document.getElementById('pay-month').value,
    };
    const r = await apiFetch(`${BASE_URL}/api/employment/profiles/${PROFILE_ID}/primes`, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function deletePrime(id) {
    const ok = await Dialog.confirm('Supprimer cette prime ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/employment/profiles/${PROFILE_ID}/primes/${id}/delete`, { method: 'POST' });
    if (r.success) window.location.reload();
}

// ---- Documents liés ----

async function saveLinkedDocuments() {
    const documentIds = Array.from(document.querySelectorAll('.link-doc-cb:checked')).map(cb => cb.value);
    const r = await apiFetch(`${BASE_URL}/api/employment/profiles/${PROFILE_ID}/documents/link`, { method: 'POST', body: JSON.stringify({ document_ids: documentIds }) });
    if (r.success) Dialog.toast('Documents liés mis à jour.');
    else Dialog.toast(r.error || 'Erreur.', 'error');
}
