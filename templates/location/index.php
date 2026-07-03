<?php
$pageTitle = 'Position';
$extraJs   = ['location.js'];
ob_start();
?>
<div class="content-header">
    <h2>📍 Position</h2>
</div>

<div class="settings-container">

    <div class="card settings-section">
        <h3>Partager ma position</h3>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Partage ponctuel : votre position est visible par toute la famille pendant la durée choisie,
            puis disparaît automatiquement. Aucun suivi en continu — vous décidez à chaque fois.
        </p>
        <div class="form-row">
            <div class="form-group">
                <label>Durée du partage</label>
                <select id="loc-duration">
                    <option value="60">1 heure</option>
                    <option value="240" selected>4 heures</option>
                    <option value="720">12 heures</option>
                    <option value="1440">24 heures</option>
                </select>
            </div>
            <div class="form-group flex-2">
                <label>Note (optionnel)</label>
                <input type="text" id="loc-note" placeholder="Ex : de retour à 18h">
            </div>
        </div>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap">
            <button class="btn btn-primary" onclick="shareMyLocation()">📍 Partager ma position maintenant</button>
            <button class="btn btn-secondary" onclick="clearMyLocation()">Arrêter mon partage</button>
        </div>
        <p id="loc-share-status" style="margin-top:.75rem;font-size:.85rem"></p>
    </div>

    <div class="card settings-section">
        <h3>Où est la famille ?</h3>
        <div id="loc-current-list">
            <p style="color:var(--text-muted);font-size:.85rem">Chargement…</p>
        </div>
    </div>

    <div class="card settings-section">
        <h3>Lieux favoris</h3>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Enregistrez un lieu (maison, école, travail…) à l'endroit où vous vous trouvez actuellement.
            Un partage effectué à proximité sera automatiquement associé à ce lieu.
        </p>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem">
            <input type="text" id="loc-place-name" placeholder="Nom du lieu (ex : Maison)" style="flex:1;min-width:180px">
            <button class="btn btn-secondary" onclick="addPlaceHere()">+ Ajouter ce lieu (position actuelle)</button>
        </div>
        <div id="loc-places-list">
            <?php if (empty($places)): ?>
                <p style="color:var(--text-muted);font-size:.85rem">Aucun lieu favori enregistré.</p>
            <?php else: ?>
                <?php foreach ($places as $p): ?>
                <div class="member-item" data-place-id="<?= $p['id'] ?>">
                    <div class="member-info">
                        <strong><?= htmlspecialchars($p['name']) ?></strong>
                        <small>Rayon : <?= (int)$p['radius_m'] ?> m</small>
                    </div>
                    <button class="btn btn-danger btn-sm" onclick="deletePlace(<?= $p['id'] ?>)">Retirer</button>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
