// ============================================
// FamilyBoard - Configurateur familial (onboarding)
// ============================================

function goToStep(step) {
    document.querySelectorAll('.onboarding-step-panel').forEach(p => {
        p.style.display = p.dataset.step === step ? '' : 'none';
    });
    document.querySelectorAll('#onboarding-steps-nav .settings-tab-btn').forEach(b => {
        b.classList.toggle('active', b.dataset.step === step);
    });
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function finishOnboarding() {
    await apiFetch(`${BASE_URL}/onboarding/complete`, { method: 'POST' });
    window.location.href = `${BASE_URL}/`;
}

// ---- Enfants ----

async function addOnboardingChild() {
    const name = document.getElementById('ob-child-name').value.trim();
    if (!name) { Dialog.toast('Le prénom est requis.', 'error'); return; }
    const payload = {
        name,
        birth_date: document.getElementById('ob-child-birthdate').value,
        color: document.getElementById('ob-child-color').value,
    };
    const r = await apiFetch(`${BASE_URL}/api/children`, { method: 'POST', body: JSON.stringify(payload) });
    if (!r.success) { Dialog.toast(r.error || 'Erreur.', 'error'); return; }

    const list = document.getElementById('onboarding-children-list');
    const row = document.createElement('div');
    row.className = 'member-item';
    row.innerHTML = `<div class="member-info"><span class="list-dot" style="background:${r.child.color}"></span><strong></strong></div>`;
    row.querySelector('strong').textContent = r.child.name;
    list.appendChild(row);

    const schoolSelect = document.getElementById('ob-school-child');
    if (schoolSelect) {
        const opt = document.createElement('option');
        opt.value = r.child.id;
        opt.textContent = r.child.name;
        schoolSelect.appendChild(opt);
    }

    document.getElementById('ob-child-name').value = '';
    document.getElementById('ob-child-birthdate').value = '';
    document.getElementById('ob-child-color').value = '#4A90D9';
    Dialog.toast('Enfant ajouté.', 'success');
}

// ---- Membres ----

async function sendOnboardingInvite() {
    const email = document.getElementById('ob-invite-email').value.trim();
    if (!email) { Dialog.toast('Adresse e-mail requise.', 'error'); return; }
    const r = await apiFetch(`${BASE_URL}/api/settings/invite`, { method: 'POST', body: JSON.stringify({ email }) });
    if (!r.success) { Dialog.toast(r.error || 'Erreur.', 'error'); return; }

    const list = document.getElementById('onboarding-invites-list');
    const row = document.createElement('div');
    row.className = 'member-item';
    row.innerHTML = '<div class="member-info">📨 <span></span></div>';
    row.querySelector('span').textContent = `Invitation envoyée à ${email}`;
    list.appendChild(row);

    document.getElementById('ob-invite-email').value = '';
    Dialog.toast('Invitation envoyée.', 'success');
}

// ---- Scolarité ----

async function addOnboardingSchool() {
    const childId = document.getElementById('ob-school-child').value;
    if (!childId) { Dialog.toast('Choisissez un enfant.', 'error'); return; }
    const payload = {
        family_child_id: childId,
        school_name: document.getElementById('ob-school-name').value,
        class_name: document.getElementById('ob-school-class').value,
    };
    const r = await apiFetch(`${BASE_URL}/api/school/students`, { method: 'POST', body: JSON.stringify(payload) });
    if (!r.success) { Dialog.toast(r.error || 'Erreur.', 'error'); return; }

    const childName = document.getElementById('ob-school-child').selectedOptions[0].textContent;
    const list = document.getElementById('onboarding-school-list');
    const row = document.createElement('div');
    row.className = 'member-item';
    row.innerHTML = '<div class="member-info">🎓 <span></span></div>';
    row.querySelector('span').textContent = `Fiche scolaire créée pour ${childName}`;
    list.appendChild(row);

    document.getElementById('ob-school-name').value = '';
    document.getElementById('ob-school-class').value = '';
    Dialog.toast('Fiche scolaire ajoutée.', 'success');
}

// ---- Activité pro ----

function toggleOnboardingPayMode() {
    const monthly = document.getElementById('ob-emp-pay-mode').value === 'monthly';
    document.getElementById('ob-emp-hourly-group').style.display = monthly ? 'none' : '';
    document.getElementById('ob-emp-monthly-group').style.display = monthly ? '' : 'none';
}

async function lookupOnboardingSiren() {
    const siren = document.getElementById('ob-emp-siren').value.trim();
    const resultEl = document.getElementById('ob-siren-result');
    if (siren.length !== 9) { Dialog.toast('Le SIREN doit contenir 9 chiffres.', 'error'); return; }
    resultEl.textContent = 'Recherche…';
    const r = await apiFetch(`${BASE_URL}/api/employment/siren-lookup?siren=${encodeURIComponent(siren)}`);
    if (r.success) {
        document.getElementById('ob-emp-name').value = r.name;
        resultEl.textContent = '✅ Entreprise trouvée, champ pré-rempli.';
    } else {
        resultEl.textContent = r.error || 'Entreprise introuvable.';
    }
}

async function addOnboardingEmployment() {
    const userId = document.getElementById('ob-emp-user').value;
    const payMode = document.getElementById('ob-emp-pay-mode').value;
    const hourlyRate = document.getElementById('ob-emp-hourly-rate').value;
    const monthlyGross = document.getElementById('ob-emp-monthly-gross').value;
    const rate = payMode === 'monthly' ? monthlyGross : hourlyRate;
    if (!userId || !rate) { Dialog.toast('Membre et rémunération requis.', 'error'); return; }

    const payload = {
        user_id: userId,
        employer_siren: document.getElementById('ob-emp-siren').value,
        employer_name: document.getElementById('ob-emp-name').value,
        job_title: document.getElementById('ob-emp-job').value,
        pay_mode: payMode,
        hourly_rate: payMode === 'hourly' ? hourlyRate : null,
        monthly_gross: payMode === 'monthly' ? monthlyGross : null,
    };
    const r = await apiFetch(`${BASE_URL}/api/employment/profiles`, { method: 'POST', body: JSON.stringify(payload) });
    if (!r.success) { Dialog.toast(r.error || 'Erreur.', 'error'); return; }

    const memberName = document.getElementById('ob-emp-user').selectedOptions[0].textContent;
    const list = document.getElementById('onboarding-employment-list');
    const row = document.createElement('div');
    row.className = 'member-item';
    row.innerHTML = '<div class="member-info">💼 <span></span></div>';
    row.querySelector('span').textContent = `Profil créé pour ${memberName}`;
    list.appendChild(row);

    Dialog.toast('Profil créé.', 'success');
}

// ---- Budget ----

async function addOnboardingBudget() {
    const title = document.getElementById('ob-budget-title').value.trim();
    const amount = document.getElementById('ob-budget-amount').value;
    if (!title || !amount) { Dialog.toast('Libellé et montant requis.', 'error'); return; }
    const payload = {
        title,
        type: document.getElementById('ob-budget-type').value,
        amount: parseFloat(String(amount).replace(',', '.')),
        day_of_month: document.getElementById('ob-budget-day').value || 1,
    };
    const r = await apiFetch(`${BASE_URL}/api/budget/recurring`, { method: 'POST', body: JSON.stringify(payload) });
    if (!r.success) { Dialog.toast(r.error || 'Erreur.', 'error'); return; }

    const list = document.getElementById('onboarding-budget-list');
    const row = document.createElement('div');
    row.className = 'member-item';
    const icon = payload.type === 'income' ? '💵' : '🧾';
    row.innerHTML = '<div class="member-info">' + icon + ' <span></span></div>';
    row.querySelector('span').textContent = `${title} — ${amount} €`;
    list.appendChild(row);

    document.getElementById('ob-budget-title').value = '';
    document.getElementById('ob-budget-amount').value = '';
    Dialog.toast('Ajouté.', 'success');
}
