// ============================================
// FamilyBoard - Wall JS
// ============================================

let wallOffset = 20;
let wallLoading = false;

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

async function toggleReaction(postId, btn) {
    const data = await apiFetch(BASE_URL + '/api/wall/' + postId + '/react', { method: 'POST' });
    if (data.success) {
        btn.classList.toggle('reacted', data.action === 'added');
        btn.querySelector('.reaction-count').textContent = data.count;
    }
}

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
            <div class="user-avatar-sm" style="background:${escapeHtml(c.user_color)}">${escapeHtml(c.user_name[0])}</div>
            <div class="comment-body">
                <strong>${escapeHtml(c.user_name)}</strong>
                <p>${escapeHtml(c.content).replace(/\n/g,'<br>')}</p>
                <small>À l'instant</small>
            </div>
        `;
        list.appendChild(div);

        // Update comment count button
        const btn = document.querySelector(`[data-post-id="${postId}"] .comment-toggle-btn`);
        if (btn) {
            const match = btn.textContent.match(/\d+/);
            const count = match ? parseInt(match[0]) + 1 : 1;
            btn.textContent = `💬 ${count} commentaire${count > 1 ? 's' : ''}`;
        }
    }
}

async function loadMorePosts() {
    if (wallLoading) return;
    wallLoading = true;
    const btn = document.getElementById('load-more-btn');
    btn.textContent = 'Chargement…';

    const data = await apiFetch(BASE_URL + '/api/wall/more?offset=' + wallOffset);
    if (data.posts && data.posts.length > 0) {
        wallOffset += data.posts.length;
        const feed = document.getElementById('posts-feed');
        // Would need server-side rendering of posts - simplified approach
        if (!data.has_more) {
            document.getElementById('load-more-container').style.display = 'none';
        }
    } else {
        document.getElementById('load-more-container').style.display = 'none';
    }
    wallLoading = false;
    btn.textContent = 'Charger plus';
}

// Prevent double-submission on the post form (double-tap on mobile)
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('post-form');
    if (!form) return;
    form.addEventListener('submit', e => {
        const btn = form.querySelector('button[type="submit"]');
        if (!btn || btn.disabled) { e.preventDefault(); return; }
        btn.disabled = true;
        btn.textContent = 'Publication…';
    });
});
