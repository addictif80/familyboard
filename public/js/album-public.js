// ============================================
// FamilyBoard - Page publique d'album partagé
// ============================================

function apViewPhoto(src) {
    document.getElementById('ap-lightbox-img').src = src;
    openModal('ap-lightbox');
}

const apInput = document.getElementById('ap-photo-input');
if (apInput) {
    apInput.addEventListener('change', async () => {
        const file = apInput.files[0];
        if (!file) return;

        const fd = new FormData();
        fd.append('image', file);
        const name = document.getElementById('ap-uploader-name')?.value.trim();
        if (name) fd.append('uploader_name', name);

        const res = await fetch(`${BASE_URL}/album/${AP_TOKEN}/photos`, { method: 'POST', body: fd });
        const result = await res.json();
        apInput.value = '';

        if (result.success) {
            const grid = document.getElementById('ap-photo-grid');
            const empty = grid.querySelector('.empty-state-card');
            if (empty) empty.remove();
            const div = document.createElement('div');
            div.className = 'album-photo';
            div.innerHTML = `
                <img src="${BASE_URL}${result.image_path}" alt="" loading="lazy" onclick="apViewPhoto(this.src)">
                <div class="album-photo-meta">● ${escapeHtml(result.uploader_name)}</div>
            `;
            grid.prepend(div);
            Dialog.toast('Photo ajoutée !', 'success');
        } else {
            Dialog.toast(result.error || "Erreur lors de l'envoi.", 'error');
        }
    });
}
