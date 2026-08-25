<?php
$pageTitle = $list ? $list['name'] : 'Liste introuvable';
ob_start();
?>
<div class="card" style="max-width:480px;margin:2rem auto;padding:1.5rem">
<?php if (!$list): ?>
    <h2>🔗 Lien introuvable</h2>
    <p style="color:var(--text-muted)">Ce lien de partage est invalide ou a été révoqué.</p>
<?php else: ?>
    <h2><?= $list['type'] === 'shopping' ? '🛒' : '✅' ?> <?= htmlspecialchars($list['name']) ?></h2>
    <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
        Cochez ou décochez un élément : la liste vue par la famille se met à jour immédiatement.
    </p>
    <ul id="public-task-list" style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.4rem"></ul>
<?php endif; ?>
</div>
<?php if ($list): ?>
<script>
const SHARE_TOKEN = <?= json_encode($token) ?>;
const BASE_URL = <?= json_encode(BASE_URL) ?>;

function publicEscapeHtml(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

function renderPublicTasks(tasks) {
    const ul = document.getElementById('public-task-list');
    if (!tasks.length) {
        ul.innerHTML = '<li style="color:var(--text-muted);font-size:.9rem;padding:.5rem 0">Aucun élément.</li>';
        return;
    }
    ul.innerHTML = tasks.map(t => `
        <li style="display:flex;align-items:center;gap:.6rem;padding:.5rem 0;border-bottom:1px solid var(--border);cursor:pointer" onclick="togglePublicTask(${t.id})">
            <span style="font-size:1.2rem">${t.is_completed ? '✅' : '⬜'}</span>
            <span style="${t.is_completed ? 'text-decoration:line-through;color:var(--text-muted)' : ''}">${publicEscapeHtml(t.title)}</span>
        </li>
    `).join('');
}

async function fetchPublicTasks() {
    try {
        const res = await fetch(BASE_URL + '/share/list/' + SHARE_TOKEN + '/data');
        const data = await res.json();
        if (data.success) renderPublicTasks(data.tasks);
    } catch (e) { /* offline — on retente au prochain cycle */ }
}

async function togglePublicTask(taskId) {
    await fetch(BASE_URL + '/share/list/' + SHARE_TOKEN + '/toggle/' + taskId, { method: 'POST' });
    fetchPublicTasks();
}

fetchPublicTasks();
setInterval(fetchPublicTasks, 8000);
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
