        <h2>Tableau de bord</h2>
        <div class="admin-stats-grid">
            <div class="admin-stat-card"><div class="stat-val"><?= $stats['families'] ?></div><div class="stat-label">Familles</div></div>
            <div class="admin-stat-card"><div class="stat-val"><?= $stats['users'] ?></div><div class="stat-label">Utilisateurs</div></div>
            <div class="admin-stat-card <?= $stats['blocked'] ? 'stat-warn' : '' ?>"><div class="stat-val"><?= $stats['blocked'] ?></div><div class="stat-label">Comptes bloqués</div></div>
            <div class="admin-stat-card <?= $stats['tickets'] ? 'stat-warn' : '' ?>"><div class="stat-val"><?= $stats['tickets'] ?></div><div class="stat-label">Tickets ouverts</div></div>
        </div>

