// ============================================
// FamilyBoard - Wall JS (with Quill WYSIWYG)
// ============================================

let wallOffset = 20;
let wallLoading = false;
let postQuill = null;
let editQuill = null;

// ---- Quill toolbar config ----
const QUILL_TOOLBAR = [
    [{ header: [2, 3, false] }],
    ['bold', 'italic', 'underline', 'strike'],
    [{ list: 'ordered' }, { list: 'bullet' }],
    ['blockquote', 'link'],
    ['clean']
];

// ---- Init Quill editors on page load ----
document.addEventListener('DOMContentLoaded', () => {
    // Creation editor
    postQuill = new Quill('#post-quill-editor', {
        theme: 'snow',
        placeholder: 'Partagez quelque chose avec votre famille…',
        modules: { toolbar: QUILL_TOOLBAR }
    });

    // Edit editor (loaded lazily, container already in DOM)
    editQuill = new Quill('#edit-quill-editor', {
        theme: 'snow',
        placeholder: 'Modifier votre publication…',
        modules: { toolbar: QUILL_TOOLBAR }
    });

    // Inject Quill HTML into hidden input before form submit
    const form = document.getElementById('post-form');
    if (form) {
        form.addEventListener('submit', e => {
            const btn = form.querySelector('button[type="submit"]');
            const html = postQuill.root.innerHTML;
            const isEmpty = postQuill.getText().trim() === '';

            // Allow submit if image is attached even if text is empty
            const hasImage = document.getElementById('post-image')?.files?.length > 0;
            if (isEmpty && !hasImage) {
                e.preventDefault();
                Dialog.toast('Rédigez un message ou ajoutez une photo.', 'error');
                return;
            }

            document.getElementById('post-content-hidden').value = isEmpty ? '' : html;
            if (!btn || btn.disabled) { e.preventDefault(); return; }
            btn.disabled = true;
            btn.textContent = 'Publication…';
        });
    }
});

// ---- Image upload preview ----
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('preview-img').src = e.target.result;
            document.getElementById('image-preview').style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function clearImage() {
    document.getElementById('post-image').value = '';
    document.getElementById('image-preview').style.display = 'none';
    document.getElementById('preview-img').src = '';
}

// ---- Reactions ----
async function toggleReaction(postId, btn) {
    const data = await apiFetch(BASE_URL + '/api/wall/' + postId + '/react', { method: 'POST' });
    if (data.success) {
        btn.classList.toggle('reacted', data.action === 'added');
        btn.querySelector('.reaction-count').textContent = data.count;
    }
}

// ---- Comments ----
function toggleComments(postId) {
    const section = document.getElementById('comments-' + postId);
    section.style.display = section.style.display === 'none' ? 'block' : 'none';
}

async function addComment(postId) {
    const input = document.getElementById('comment-input-' + postId);
    const content = input.value.trim();
    if (!content) return;

    const data = await apiFetch(BASE_URL + '/api/wall/' + postId + '/comment', {
        method: 'POST',
        body: JSON.stringify({ content })
    });

    if (data.success) {
        input.value = '';
        const list = document.getElementById('comment-list-' + postId);
        const c = data.comment;
        const div = document.createElement('div');
        div.className = 'comment-item';
        div.innerHTML = `
            ${avatarHtml(c.user_avatar, c.user_color, c.user_name, 'user-avatar-sm')}
            <div class="comment-body">
                <strong>${escapeHtml(c.user_name)}</strong>
                <p>${escapeHtml(c.content).replace(/\n/g,'<br>')}</p>
                <small>À l'instant</small>
            </div>
        `;
        list.appendChild(div);

        const btn = document.querySelector(`[data-post-id="${postId}"] .comment-toggle-btn`);
        if (btn) {
            const match = btn.textContent.match(/\d+/);
            const count = match ? parseInt(match[0]) + 1 : 1;
            btn.textContent = `💬 ${count} commentaire${count > 1 ? 's' : ''}`;
        }
    }
}

// ---- Edit post ----
function openEditPost(post) {
    document.getElementById('edit-post-id').value = post.id;

    // Fill the Quill editor with existing content
    if (post.content && post.content.trimStart().startsWith('<')) {
        editQuill.root.innerHTML = post.content;
    } else {
        // Legacy plain text — convert newlines to <br>
        editQuill.root.innerHTML = '<p>' + escapeHtml(post.content || '').replace(/\n/g, '<br>') + '</p>';
    }

    const btn = document.getElementById('edit-save-btn');
    btn.disabled = false;
    btn.textContent = 'Enregistrer';

    openModal('edit-post-modal');
    // Focus at end of content
    editQuill.focus();
    const len = editQuill.getLength();
    editQuill.setSelection(len, 0);
}

async function saveEditPost() {
    const id = document.getElementById('edit-post-id').value;
    const btn = document.getElementById('edit-save-btn');
    const html = editQuill.root.innerHTML;

    if (editQuill.getText().trim() === '') {
        Dialog.toast('Le contenu ne peut pas être vide.', 'error');
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Enregistrement…';

    const data = await apiFetch(BASE_URL + '/api/wall/' + id + '/update', {
        method: 'POST',
        body: JSON.stringify({ content: html })
    });

    if (data.success) {
        // Update the post content in-place in the DOM
        const postCard = document.querySelector(`[data-post-id="${id}"]`);
        if (postCard) {
            let contentDiv = postCard.querySelector('.post-content');
            if (!contentDiv) {
                contentDiv = document.createElement('div');
                contentDiv.className = 'post-content ql-content';
                postCard.querySelector('.post-header').after(contentDiv);
            }
            contentDiv.className = 'post-content ql-content';
            contentDiv.innerHTML = data.content;
        }
        closeModal('edit-post-modal');
        Dialog.toast('Publication modifiée.');
    } else {
        Dialog.toast(data.error || 'Erreur lors de la modification.', 'error');
        btn.disabled = false;
        btn.textContent = 'Enregistrer';
    }
}

// ---- Load more ----
async function loadMorePosts() {
    if (wallLoading) return;
    wallLoading = true;
    const btn = document.getElementById('load-more-btn');
    btn.textContent = 'Chargement…';

    const data = await apiFetch(BASE_URL + '/api/wall/more?offset=' + wallOffset);
    if (data.posts && data.posts.length > 0) {
        wallOffset += data.posts.length;
        if (!data.has_more) {
            document.getElementById('load-more-container').style.display = 'none';
        }
    } else {
        document.getElementById('load-more-container').style.display = 'none';
    }
    wallLoading = false;
    btn.textContent = 'Charger plus';
}

// ---- Abonnements (mur "réseau social") ----

async function requestFollow(userId) {
    const r = await apiFetch(BASE_URL + '/api/follow/request', { method: 'POST', body: JSON.stringify({ user_id: userId }) });
    if (r.success) { Dialog.toast('Demande envoyée.'); window.location.reload(); }
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function acceptFollow(followerId) {
    const r = await apiFetch(BASE_URL + '/api/follow/accept', { method: 'POST', body: JSON.stringify({ user_id: followerId }) });
    if (r.success) window.location.reload();
    else Dialog.toast(r.error || 'Erreur.', 'error');
}

async function removeFollower(followerId) {
    const r = await apiFetch(BASE_URL + '/api/follow/remove-follower', { method: 'POST', body: JSON.stringify({ user_id: followerId }) });
    if (r.success) window.location.reload();
    else Dialog.toast('Erreur.', 'error');
}

async function unfollow(userId) {
    const ok = await Dialog.confirm('Se désabonner de ce membre ?');
    if (!ok) return;
    const r = await apiFetch(BASE_URL + '/api/follow/remove', { method: 'POST', body: JSON.stringify({ user_id: userId }) });
    if (r.success) window.location.reload();
    else Dialog.toast('Erreur.', 'error');
}
