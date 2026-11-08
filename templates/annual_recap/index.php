<?php
$pageTitle = 'Récap annuel';
$extraJs = ['vendor/pptxgen.bundle.js', 'annual_recap.js'];
ob_start();
?>
<div class="tasks-main" style="width:100%">
    <div class="tasks-header">
        <h2>🎞️ Récap annuel</h2>
    </div>

    <div class="card settings-section" style="max-width:560px">
        <p style="color:var(--text-muted)">
            Générez un diaporama PowerPoint (.pptx) qui résume l'année de la famille : calendrier,
            tâches, budget, photos marquantes du mur familial et anniversaires. Tout est généré
            directement dans votre navigateur — rien n'est envoyé ailleurs que votre propre
            téléchargement.
        </p>
        <div class="form-group">
            <label>Année</label>
            <select id="recap-year">
                <?php foreach ($years as $y): ?>
                    <option value="<?= $y ?>" <?= $y === $currentYear ? 'selected' : '' ?>><?= $y ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary" id="recap-generate-btn" onclick="generateAnnualRecap()">🎬 Générer le PowerPoint</button>
        <p id="recap-status" style="margin-top:1rem;font-size:.85rem;color:var(--text-muted)"></p>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
