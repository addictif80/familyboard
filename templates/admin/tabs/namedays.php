        <h2>Fêtes des prénoms</h2>
        <p style="color:var(--text-muted);font-size:.88rem;margin-bottom:1rem">
            Complète ou corrige le calendrier intégré des fêtes (affichées sur les fiches contact,
            le tableau de bord, l'écran mural et le kiosque). Une entrée ajoutée ici prend le pas
            sur le calendrier par défaut pour ce prénom.
        </p>
        <form method="POST" action="<?= BASE_URL ?>/admin/namedays" class="admin-inline-form"><?= \App\Core\Csrf::field() ?>
            <input type="text" name="name" placeholder="Prénom (ex : Elouan)" required style="width:200px">
            <input type="date" name="name_day" required>
            <button class="btn btn-primary btn-sm">Ajouter</button>
        </form>
        <?php if (empty($customNameDays)): ?>
            <p class="empty-state">Aucune fête personnalisée pour l'instant.</p>
        <?php else: ?>
        <table class="admin-table" style="margin-top:1rem">
            <thead><tr><th>Prénom</th><th>Date</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($customNameDays as $nd): ?>
            <?php [$ndm, $ndd] = explode('-', $nd['name_day']); ?>
            <tr>
                <td><?= htmlspecialchars($nd['name']) ?></td>
                <td><?= (int)$ndd ?>/<?= (int)$ndm ?></td>
                <td>
                    <form method="POST" action="<?= BASE_URL ?>/admin/namedays/<?= $nd['id'] ?>/delete"><?= \App\Core\Csrf::field() ?>
                        <button class="btn btn-secondary btn-sm">Supprimer</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

