<?php
$pageTitle = 'Migrer vers FamilyBoard';
$extraJs = ['migration.js'];
ob_start();
?>
<div class="tasks-main" style="width:100%">
    <div class="tasks-header">
        <h2>🚚 Vous veniez de FamilyWall, 2houses ou d'une autre app ?</h2>
    </div>

    <div class="card settings-section" style="max-width:720px">
        <p style="color:var(--text-muted)">
            Pas besoin de tout ressaisir à la main. Voici les trois données qu'on peut récupérer
            directement, étape par étape.
        </p>
    </div>

    <!-- Étape 1 — Calendrier -->
    <div class="card settings-section" style="max-width:720px">
        <h3>📅 1. Le calendrier</h3>
        <p style="font-size:.9rem;color:var(--text-muted)">
            FamilyWall et 2houses permettent tous les deux de générer un lien iCal pour votre
            calendrier — un lien que FamilyBoard peut suivre en continu, comme n'importe quel
            calendrier externe (Google, Outlook...). Les nouveaux événements créés là-bas
            apparaîtront ici automatiquement.
        </p>
        <details style="margin-bottom:1rem">
            <summary style="cursor:pointer;font-weight:600">Où trouver ce lien sur FamilyWall ?</summary>
            <p style="font-size:.85rem;color:var(--text-muted);margin-top:.5rem">
                Dans l'app ou sur le site : Calendrier → icône ⚙️ (ou la flèche près du nom du
                calendrier) → « Calendar Details » / « Calendar Settings » → « Generate iCal URL »
                → Copier.
            </p>
        </details>
        <details style="margin-bottom:1rem">
            <summary style="cursor:pointer;font-weight:600">Où trouver ce lien sur 2houses ?</summary>
            <p style="font-size:.85rem;color:var(--text-muted);margin-top:.5rem">
                Sur la page Agenda : « Partager mon agenda » → choisissez le calendrier (toute la
                famille ou un enfant) → copiez le lien .ics généré.
            </p>
        </details>
        <?php if ($isAdmin): ?>
        <div class="form-row">
            <div class="form-group flex-2">
                <label>Lien iCal collé depuis l'autre app</label>
                <input type="url" id="migration-ical-url" placeholder="https://...">
            </div>
            <div class="form-group">
                <label>Nom</label>
                <input type="text" id="migration-ical-name" placeholder="Mon ancien calendrier">
            </div>
        </div>
        <button class="btn btn-primary btn-sm" onclick="importMigrationCalendar()">Importer ce calendrier</button>
        <p id="migration-ical-status" style="margin-top:.6rem;font-size:.85rem;color:var(--text-muted)"></p>
        <?php else: ?>
        <p style="font-size:.85rem;color:var(--text-muted)">Seul un administrateur de la famille peut ajouter un calendrier externe.</p>
        <?php endif; ?>
    </div>

    <!-- Étape 2 — Contacts -->
    <div class="card settings-section" style="max-width:720px">
        <h3>👤 2. Les contacts</h3>
        <p style="font-size:.9rem;color:var(--text-muted)">
            FamilyWall et 2houses ne proposent pas d'export direct de leurs contacts. En revanche,
            si vos proches sont dans votre répertoire téléphonique, Google Contacts ou iCloud,
            vous pouvez en exporter un fichier <code>.vcf</code> (format standard, universel) et
            l'importer ici d'un coup.
        </p>
        <input type="file" id="migration-vcf-file" accept=".vcf,text/vcard">
        <button class="btn btn-primary btn-sm" onclick="importMigrationContacts()">Importer ce fichier</button>
        <p id="migration-vcf-status" style="margin-top:.6rem;font-size:.85rem;color:var(--text-muted)"></p>
    </div>

    <!-- Étape 3 — Listes -->
    <div class="card settings-section" style="max-width:720px">
        <h3>🛒 3. Une liste de courses ou de tâches</h3>
        <p style="font-size:.9rem;color:var(--text-muted)">
            Pas d'export automatisé possible ici non plus — mais copiez le contenu de votre liste
            depuis l'autre app (ou une note, un mail...) et collez-le ci-dessous : chaque ligne
            devient un article ou une tâche, sans avoir à les retaper une par une.
        </p>
        <?php if (empty($lists)): ?>
            <p class="empty-state">Créez d'abord une liste dans <a href="<?= BASE_URL ?>/tasks">Tâches & Courses</a>.</p>
        <?php else: ?>
        <div class="form-group">
            <label>Liste de destination</label>
            <select id="migration-list-select">
                <?php foreach ($lists as $l): ?>
                    <option value="<?= $l['id'] ?>"><?= $l['type'] === 'shopping' ? '🛒' : '✅' ?> <?= htmlspecialchars($l['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Contenu collé (une ligne = un élément)</label>
            <textarea id="migration-list-text" rows="6" placeholder="Lait&#10;Pain&#10;Appeler le dentiste"></textarea>
        </div>
        <button class="btn btn-primary btn-sm" onclick="importMigrationList()">Ajouter ces éléments</button>
        <p id="migration-list-status" style="margin-top:.6rem;font-size:.85rem;color:var(--text-muted)"></p>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
