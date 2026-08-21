// ============================================
// FamilyBoard - Générateur de courriers (Quill WYSIWYG + variables {{...}})
// ============================================

let letterQuill = null;
const LETTER_BUILTIN_VARS = ['civilite', 'nom_dest', 'prenom_dest'];

document.addEventListener('DOMContentLoaded', () => {
    letterQuill = new Quill('#lm-quill-editor', {
        theme: 'snow',
        placeholder: 'Rédigez le corps du courrier…',
        modules: { toolbar: [['bold', 'italic', 'underline'], [{ list: 'ordered' }, { list: 'bullet' }], ['clean']] }
    });

    const params = new URLSearchParams(window.location.search);
    const openId = params.get('open');
    if (openId) {
        showLetterDetail(parseInt(openId, 10));
        history.replaceState(null, '', window.location.pathname);
    }
});

function filterLetters() {
    const q = document.getElementById('letters-search').value.trim().toLowerCase();
    document.querySelectorAll('.letter-item').forEach(el => {
        el.style.display = el.dataset.search.includes(q) ? '' : 'none';
    });
}

// ---- Variables dynamiques ----

function detectLetterVariables(html) {
    const matches = html.match(/\{\{([^}]+)\}\}/g);
    if (!matches) return [];
    const names = [...new Set(matches.map(m => m.replace(/\{\{|\}\}/g, '')))];
    return names.filter(n => !LETTER_BUILTIN_VARS.includes(n));
}

function addLetterVariableRow(name = '', value = '') {
    const container = document.getElementById('lm-variables-container');
    const row = document.createElement('div');
    row.className = 'letter-variable-row';
    row.innerHTML = `
        <input type="text" class="lv-name" placeholder="Nom (ex : date-rdv)" value="${escapeHtml(name)}">
        <input type="text" class="lv-value" placeholder="Valeur" value="${escapeHtml(value)}">
        <button type="button" class="btn-chip" onclick="insertLetterVarFromRow(this)" title="Insérer">↓</button>
        <button type="button" class="btn-chip" onclick="this.closest('.letter-variable-row').remove()" title="Supprimer">✕</button>
    `;
    container.appendChild(row);
}

function insertLetterVar(name) {
    const range = letterQuill.getSelection(true);
    letterQuill.insertText(range ? range.index : letterQuill.getLength(), '{{' + name + '}}');
}

function insertLetterVarFromRow(btn) {
    const row = btn.closest('.letter-variable-row');
    const name = row.querySelector('.lv-name').value.trim();
    if (!name) { Dialog.toast('Nommez la variable avant de l\'insérer.', 'error'); return; }
    insertLetterVar(name);
}

function getLetterBuiltinValues() {
    return {
        civilite: document.getElementById('lm-civility').value,
        nom_dest: document.getElementById('lm-last-name').value,
        prenom_dest: document.getElementById('lm-first-name').value,
    };
}

function getLetterCustomVariables() {
    const vars = {};
    document.querySelectorAll('#lm-variables-container .letter-variable-row').forEach(row => {
        const name = row.querySelector('.lv-name').value.trim();
        if (name) vars[name] = row.querySelector('.lv-value').value;
    });
    return vars;
}

function replaceLetterVariables(html, vars) {
    for (const [name, value] of Object.entries(vars)) {
        const escaped = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        html = html.replace(new RegExp('\\{\\{' + escaped + '\\}\\}', 'g'), escapeHtml(value));
    }
    return html;
}

// ---- Modale ajout/édition ----

function resetLetterForm() {
    document.getElementById('lm-id').value = '';
    document.getElementById('lm-civility').value = '';
    document.getElementById('lm-last-name').value = '';
    document.getElementById('lm-first-name').value = '';
    document.getElementById('lm-complement').value = '';
    document.getElementById('lm-address').value = '';
    document.getElementById('lm-address-complement').value = '';
    document.getElementById('lm-postal-city').value = '';
    document.getElementById('lm-subject').value = '';
    document.getElementById('lm-template-select').value = '';
    document.getElementById('lm-template-name').value = '';
    document.getElementById('lm-variables-container').innerHTML = '';
    letterQuill.setContents([]);
}

function openLetterModal(id) {
    resetLetterForm();
    document.getElementById('lm-title').textContent = id ? 'Modifier le courrier' : 'Nouveau courrier';
    if (id) {
        const l = LETTERS_DATA.find(x => x.id == id);
        if (!l) return;
        document.getElementById('lm-id').value = l.id;
        document.getElementById('lm-civility').value = l.civility || '';
        document.getElementById('lm-last-name').value = l.recipient_last_name || '';
        document.getElementById('lm-first-name').value = l.recipient_first_name || '';
        document.getElementById('lm-complement').value = l.recipient_complement || '';
        document.getElementById('lm-address').value = l.recipient_address || '';
        document.getElementById('lm-address-complement').value = l.recipient_address_complement || '';
        document.getElementById('lm-postal-city').value = l.recipient_postal_city || '';
        document.getElementById('lm-place').value = l.place || '';
        document.getElementById('lm-subject').value = l.subject || '';
        letterQuill.root.innerHTML = l.body || '';
    }
    openModal('letter-modal');
}

async function saveLetter() {
    const id = document.getElementById('lm-id').value;
    const builtin = getLetterBuiltinValues();
    const custom = getLetterCustomVariables();
    const body = replaceLetterVariables(letterQuill.root.innerHTML, { ...builtin, ...custom });

    if (letterQuill.getText().trim() === '') {
        Dialog.toast('Rédigez le corps du courrier.', 'error');
        return;
    }

    const payload = {
        civility: builtin.civilite,
        recipient_last_name: builtin.nom_dest,
        recipient_first_name: builtin.prenom_dest,
        recipient_complement: document.getElementById('lm-complement').value,
        recipient_address: document.getElementById('lm-address').value,
        recipient_address_complement: document.getElementById('lm-address-complement').value,
        recipient_postal_city: document.getElementById('lm-postal-city').value,
        place: document.getElementById('lm-place').value,
        subject: document.getElementById('lm-subject').value,
        body,
    };

    const url = id ? `${BASE_URL}/api/letters/${id}` : `${BASE_URL}/api/letters`;
    const r = await apiFetch(url, { method: 'POST', body: JSON.stringify(payload) });
    if (!r.success) {
        Dialog.toast(r.error || 'Erreur.', 'error');
        return;
    }
    window.location.href = `${BASE_URL}/letters?open=${id || r.id}`;
}

async function deleteLetter(id) {
    const ok = await Dialog.confirm('Supprimer ce courrier ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/letters/${id}/delete`, { method: 'POST' });
    if (r.success) window.location.reload();
}

// ---- Modèles ----

function loadLetterTemplate() {
    const id = document.getElementById('lm-template-select').value;
    if (!id) return;
    const t = LETTER_TEMPLATES_DATA.find(x => x.id == id);
    if (!t) return;
    document.getElementById('lm-subject').value = t.subject || '';
    letterQuill.root.innerHTML = t.body || '';
    document.getElementById('lm-variables-container').innerHTML = '';
    let names = [];
    try { names = JSON.parse(t.variables || '[]'); } catch (e) {}
    if (!names.length) names = detectLetterVariables(t.body || '');
    names.forEach(n => addLetterVariableRow(n, ''));
}

async function saveLetterTemplate() {
    const name = document.getElementById('lm-template-name').value.trim();
    if (!name) { Dialog.toast('Donnez un nom au modèle.', 'error'); return; }
    const variables = [];
    document.querySelectorAll('#lm-variables-container .lv-name').forEach(inp => {
        if (inp.value.trim()) variables.push(inp.value.trim());
    });
    const payload = {
        name,
        subject: document.getElementById('lm-subject').value,
        body: letterQuill.root.innerHTML,
        variables,
    };
    const r = await apiFetch(`${BASE_URL}/api/letter-templates`, { method: 'POST', body: JSON.stringify(payload) });
    if (r.success) {
        Dialog.toast('Modèle enregistré.');
        setTimeout(() => window.location.reload(), 600);
    } else {
        Dialog.toast(r.error || 'Erreur.', 'error');
    }
}

async function deleteLetterTemplate(id) {
    const ok = await Dialog.confirm('Supprimer ce modèle ?');
    if (!ok) return;
    const r = await apiFetch(`${BASE_URL}/api/letter-templates/${id}/delete`, { method: 'POST' });
    if (r.success) window.location.reload();
}

// ---- Rendu (détail / impression) — même mise en page dans les deux cas ----

function fmtLetterDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr.replace(' ', 'T'));
    return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
}

function buildLetterRecipientLines(l) {
    let lines = escapeHtml(l.recipient_display_name);
    if (l.recipient_complement) lines += '<br>' + escapeHtml(l.recipient_complement);
    lines += '<br>' + escapeHtml(l.recipient_address);
    if (l.recipient_address_complement) lines += '<br>' + escapeHtml(l.recipient_address_complement);
    lines += '<br>' + escapeHtml(l.recipient_postal_city);
    return lines;
}

function showLetterDetail(id) {
    const l = LETTERS_DATA.find(x => x.id == id);
    if (!l) return;
    const destLines = buildLetterRecipientLines(l);
    document.getElementById('letter-detail-content').innerHTML = `
        <div class="letter-preview">
            <div class="lp-brand">${escapeHtml(LETTER_SENDER.family_name)}</div>
            <div class="lp-sender">
                ${escapeHtml(LETTER_SENDER.user_name)}<br>
                ${escapeHtml(LETTER_SENDER.address)}<br>
                ${escapeHtml(LETTER_SENDER.postal_city)}
            </div>
            <div class="lp-dest">${destLines}</div>
            <div class="lp-lieu-date">${escapeHtml(l.place || '')}, le ${fmtLetterDate(l.letter_date)}</div>
            <div class="lp-objet">Objet : ${escapeHtml(l.subject)}</div>
            <div class="lp-corps">${l.body}</div>
            <div class="lp-footer">${escapeHtml(LETTER_SENDER.user_name)}</div>
        </div>
        <div style="text-align:center;margin-top:1rem">
            <button class="btn btn-primary" onclick="printLetter(${l.id})">🖨️ Imprimer</button>
        </div>
    `;
    openModal('letter-detail-modal');
}

function printLetter(id) {
    const l = LETTERS_DATA.find(x => x.id == id);
    if (!l) return;
    const destLines = buildLetterRecipientLines(l);
    const printArea = document.getElementById('letter-print-area');
    printArea.innerHTML = `
        <div style="font-family:Georgia,'Times New Roman',serif;font-size:11pt;line-height:1.4;color:#000;position:relative;">
            <div>
                <div style="font-family:Georgia,serif;font-size:20pt;font-weight:bold;color:#2C3E50">${escapeHtml(LETTER_SENDER.family_name)}</div>
                <div style="font-family:Arial,sans-serif;margin-top:4mm;font-size:9pt;line-height:1.3">
                    ${escapeHtml(LETTER_SENDER.user_name)}<br>
                    ${escapeHtml(LETTER_SENDER.address)}<br>
                    ${escapeHtml(LETTER_SENDER.postal_city)}
                </div>
            </div>
            <div style="position:absolute;top:47mm;left:100mm;width:85mm;font-size:11pt;line-height:1.5;font-family:Arial,sans-serif">
                ${destLines}
            </div>
            <div style="margin-top:40mm"></div>
            <div style="text-align:right;font-family:Arial,sans-serif">${escapeHtml(l.place || '')}, le ${fmtLetterDate(l.letter_date)}</div>
            <br><br>
            <div style="font-weight:bold;font-family:Arial,sans-serif">Objet : ${escapeHtml(l.subject)}</div>
            <br><br>
            <div style="text-align:justify;font-family:Arial,sans-serif">${l.body}</div>
            <div style="text-align:right;margin-top:40px;font-family:Arial,sans-serif">${escapeHtml(LETTER_SENDER.user_name)}</div>
        </div>
    `;
    printArea.style.display = 'block';
    document.querySelectorAll('.modal-overlay').forEach(m => m.style.display = 'none');
    setTimeout(() => {
        window.print();
        printArea.style.display = 'none';
    }, 250);
}
