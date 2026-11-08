// ============================================
// FamilyBoard - Assistant de migration (calendrier, contacts, listes)
// ============================================

async function importMigrationCalendar() {
    const url = document.getElementById('migration-ical-url').value.trim();
    const name = document.getElementById('migration-ical-name').value.trim() || 'Calendrier importé';
    const status = document.getElementById('migration-ical-status');
    if (!url) { status.textContent = 'Collez un lien iCal.'; return; }

    status.textContent = 'Import en cours…';
    const r = await apiFetch(`${BASE_URL}/api/calendar/caldav`, {
        method: 'POST',
        body: JSON.stringify({ name, url, color: '#9B59B6' }),
    });
    status.textContent = r.success
        ? `Calendrier importé ! Retrouvez-le dans le Calendrier.`
        : (r.error || "Impossible d'importer ce lien — vérifiez qu'il s'agit bien d'un lien iCal (.ics).");
}

async function importMigrationContacts() {
    const input = document.getElementById('migration-vcf-file');
    const status = document.getElementById('migration-vcf-status');
    if (!input.files.length) { status.textContent = 'Choisissez un fichier .vcf.'; return; }

    const form = new FormData();
    form.append('file', input.files[0]);
    status.textContent = 'Import en cours…';
    const res = await fetch(`${BASE_URL}/api/contacts/import-vcf`, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: form,
    });
    const r = await res.json();
    status.textContent = r.success
        ? `${r.count} contact(s) importé(s) ! Retrouvez-les dans Contacts.`
        : (r.error || "Erreur lors de l'import.");
}

async function importMigrationList() {
    const listId = document.getElementById('migration-list-select').value;
    const text = document.getElementById('migration-list-text').value;
    const status = document.getElementById('migration-list-status');
    if (!text.trim()) { status.textContent = 'Collez au moins une ligne.'; return; }

    status.textContent = 'Import en cours…';
    const r = await apiFetch(`${BASE_URL}/api/tasks/list/${listId}/bulk-import`, {
        method: 'POST',
        body: JSON.stringify({ text }),
    });
    status.textContent = r.success
        ? `${r.count} élément(s) ajouté(s) !`
        : (r.error || "Erreur lors de l'import.");
    if (r.success) document.getElementById('migration-list-text').value = '';
}
