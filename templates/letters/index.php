<?php
$pageTitle = 'Courriers';
$extraCss  = ['quill.snow.css'];
$extraJs   = ['quill.min.js', 'letters.js'];
ob_start();

use App\Core\DateHelper;
?>
<div class="letters-container">

    <!-- Toolbar -->
    <div class="letters-toolbar">
        <div class="letters-search-wrap">
            <input type="text" id="letters-search" placeholder="Rechercher un courrier…" oninput="filterLetters()">
        </div>
        <button class="btn btn-primary" onclick="openLetterModal()">+ Nouveau courrier</button>
        <button class="btn btn-secondary" onclick="openModal('letter-templates-modal')">📋 Modèles (<?= count($templates) ?>)</button>
    </div>

    <?php if (empty($family['sender_address']) || empty($family['sender_postal_city'])): ?>
    <div class="alert alert-info" style="margin-bottom:1rem">
        💡 Renseignez l'<a href="<?= BASE_URL ?>/settings#tab-famille">adresse postale du foyer</a> dans les
        paramètres famille pour qu'elle apparaisse comme expéditeur sur vos courriers.
    </div>
    <?php endif; ?>

    <!-- List -->
    <div class="letters-list" id="letters-list">
        <?php if (empty($letters)): ?>
            <p class="letters-empty">Aucun courrier généré pour l'instant.</p>
        <?php endif; ?>
        <?php foreach ($letters as $l): ?>
        <div class="letter-item" data-letter-id="<?= $l['id'] ?>" data-search="<?= htmlspecialchars(mb_strtolower($l['recipient_display_name'] . ' ' . $l['subject'])) ?>">
            <div class="letter-item-main">
                <div class="letter-item-date"><?= DateHelper::format($l['letter_date'], 'd/m/Y') ?></div>
                <div class="letter-item-info">
                    <strong><?= htmlspecialchars($l['recipient_display_name']) ?></strong>
                    <span class="letter-item-subject"><?= htmlspecialchars(mb_strimwidth($l['subject'], 0, 80, '…')) ?></span>
                </div>
                <div class="letter-item-author">par <?= htmlspecialchars($l['author_name']) ?></div>
            </div>
            <div class="letter-item-actions">
                <button class="btn-chip" title="Voir" onclick="showLetterDetail(<?= $l['id'] ?>)">👁️</button>
                <button class="btn-chip" title="Modifier" onclick="openLetterModal(<?= $l['id'] ?>)">✏️</button>
                <button class="btn-chip" title="Imprimer" onclick="printLetter(<?= $l['id'] ?>)">🖨️</button>
                <button class="btn-chip" title="Supprimer" onclick="deleteLetter(<?= $l['id'] ?>)">🗑️</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal-overlay" id="letter-modal" style="display:none">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 id="lm-title">Nouveau courrier</h3>
            <button onclick="closeModal('letter-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="lm-id">

            <div class="form-row">
                <div class="form-group flex-2">
                    <label>Charger un modèle</label>
                    <div style="display:flex;gap:.4rem">
                        <select id="lm-template-select" style="flex:1">
                            <option value="">-- Choisir un modèle --</option>
                            <?php foreach ($templates as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="loadLetterTemplate()">Charger</button>
                    </div>
                </div>
                <div class="form-group flex-2">
                    <label>Sauvegarder comme modèle</label>
                    <div style="display:flex;gap:.4rem">
                        <input type="text" id="lm-template-name" placeholder="Nom du modèle…" style="flex:1">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="saveLetterTemplate()">Sauver</button>
                    </div>
                </div>
            </div>

            <hr style="margin:1rem 0;border-color:var(--border)">

            <div class="form-row">
                <div class="form-group">
                    <label>Civilité <span style="color:var(--danger)">*</span></label>
                    <select id="lm-civility">
                        <option value="">--</option>
                        <option value="Madame">Madame</option>
                        <option value="Monsieur">Monsieur</option>
                    </select>
                </div>
                <div class="form-group flex-2">
                    <label>Nom <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="lm-last-name">
                </div>
                <div class="form-group flex-2">
                    <label>Prénom</label>
                    <input type="text" id="lm-first-name">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group flex-2">
                    <label>Complément (titre, service…)</label>
                    <input type="text" id="lm-complement">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group flex-2">
                    <label>Adresse <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="lm-address">
                </div>
                <div class="form-group flex-2">
                    <label>Complément d'adresse</label>
                    <input type="text" id="lm-address-complement">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group flex-2">
                    <label>Code postal et ville <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="lm-postal-city">
                </div>
                <div class="form-group">
                    <label>Lieu de rédaction</label>
                    <input type="text" id="lm-place" value="<?= htmlspecialchars(trim(preg_replace('/^\d+\s*/', '', $family['sender_postal_city'] ?? ''))) ?>">
                </div>
                <div class="form-group">
                    <label>Date</label>
                    <input type="text" value="<?= date('d/m/Y') ?>" disabled>
                </div>
            </div>

            <div class="form-group">
                <label>Objet <span style="color:var(--danger)">*</span></label>
                <input type="text" id="lm-subject">
            </div>

            <div class="form-group">
                <div class="letter-variables-header">
                    <label style="margin:0">Variables dynamiques</label>
                    <button type="button" class="btn-chip" onclick="addLetterVariableRow()">+ Ajouter une variable</button>
                </div>
                <p class="letter-variables-help">
                    Cliquez sur une variable pour l'insérer dans le corps. Les variables fixes sont
                    remplies automatiquement depuis les champs destinataire ci-dessus.
                </p>
                <div class="letter-builtin-vars">
                    <button type="button" class="btn-chip" onclick="insertLetterVar('civilite')"><code>{{civilite}}</code></button>
                    <button type="button" class="btn-chip" onclick="insertLetterVar('nom_dest')"><code>{{nom_dest}}</code></button>
                    <button type="button" class="btn-chip" onclick="insertLetterVar('prenom_dest')"><code>{{prenom_dest}}</code></button>
                </div>
                <div id="lm-variables-container"></div>
            </div>

            <div class="form-group">
                <label>Corps du courrier <span style="color:var(--danger)">*</span></label>
                <div id="lm-quill-editor" style="background:var(--bg)"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('letter-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="saveLetter()">Enregistrer le courrier</button>
        </div>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal-overlay" id="letter-detail-modal" style="display:none">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>Détail du courrier</h3>
            <button onclick="closeModal('letter-detail-modal')">✕</button>
        </div>
        <div class="modal-body" id="letter-detail-content"></div>
    </div>
</div>

<!-- Templates management modal -->
<div class="modal-overlay" id="letter-templates-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>Modèles de courrier</h3>
            <button onclick="closeModal('letter-templates-modal')">✕</button>
        </div>
        <div class="modal-body">
            <?php if (empty($templates)): ?>
                <p style="color:var(--text-muted);font-size:.85rem">Aucun modèle enregistré. Créez-en un depuis le formulaire d'un courrier.</p>
            <?php else: ?>
            <?php foreach ($templates as $t): ?>
            <div class="member-item">
                <div class="member-info">
                    <strong><?= htmlspecialchars($t['name']) ?></strong>
                    <small>par <?= htmlspecialchars($t['author_name']) ?></small>
                </div>
                <button class="btn btn-danger btn-sm" onclick="deleteLetterTemplate(<?= $t['id'] ?>)">Supprimer</button>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Hidden print area -->
<div id="letter-print-area" style="display:none"></div>

<script>
const LETTERS_DATA = <?= json_encode($letters) ?>;
const LETTER_TEMPLATES_DATA = <?= json_encode($templates) ?>;
const LETTER_SENDER = <?= json_encode([
    'family_name'  => $family['name'] ?? 'FamilyBoard',
    'user_name'    => $user['name'],
    'address'      => $family['sender_address'] ?? '',
    'postal_city'  => $family['sender_postal_city'] ?? '',
]) ?>;
</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/templates/layout.php';
