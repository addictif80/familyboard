<?php
$pageTitle = 'Gestionnaire de documents';
$extraJs   = ['document.js', 'twodoc.js'];
ob_start();

use App\Core\OcrHelper;
use App\Core\DateHelper;

$totalDocs = array_sum($typeCounts);
?>
<div class="docs-container">

    <!-- Toolbar -->
    <div class="docs-toolbar">
        <div class="docs-search-wrap">
            <input type="search" id="docs-search" class="docs-search"
                   placeholder="🔍 Rechercher dans vos documents…"
                   value="<?= htmlspecialchars($search) ?>"
                   oninput="searchDocs(this.value)">
        </div>
        <button class="btn btn-primary" onclick="openDocModal()">+ Ajouter un document</button>
    </div>

    <!-- Expiry alert -->
    <?php if (!empty($expiring)): ?>
    <div class="warranty-alert-strip" style="margin-bottom:1rem">
        ⚠️ <strong><?= count($expiring) ?> document<?= count($expiring) > 1 ? 's' : '' ?> expire<?= count($expiring) > 1 ? 'nt' : '' ?> dans les 90 jours :</strong>
        <?php foreach ($expiring as $d): ?>
            <span class="warranty-alert-chip <?= $d['expiry_status'] === 'critical' ? 'w-critical' : 'w-soon' ?>">
                <?= htmlspecialchars($d['title']) ?>
                (<?= $d['days_until_expiry'] ?>j)
            </span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Type filter chips -->
    <div class="docs-type-filters" id="docs-type-filters">
        <button class="doc-filter-chip <?= $filterType === '' ? 'active' : '' ?>"
                onclick="filterByType('')">
            📁 Tous <span class="doc-filter-count"><?= $totalDocs ?></span>
        </button>
        <?php foreach ($allTypes as $tKey => $tDef): ?>
            <?php $cnt = $typeCounts[$tKey] ?? 0; if ($cnt === 0 && $filterType !== $tKey) continue; ?>
            <button class="doc-filter-chip <?= $filterType === $tKey ? 'active' : '' ?>"
                    onclick="filterByType('<?= $tKey ?>')"
                    style="--type-color:<?= htmlspecialchars($tDef['color']) ?>">
                <?= $tDef['icon'] ?> <?= htmlspecialchars($tDef['label']) ?>
                <span class="doc-filter-count"><?= $cnt ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- Member filter (if >1 member) -->
    <?php if (count($members) > 1): ?>
    <div class="docs-member-filters">
        <span style="font-size:.8rem;color:var(--text-muted);margin-right:.5rem">Membre :</span>
        <button class="doc-filter-chip small <?= $filterUser === 0 ? 'active' : '' ?>" onclick="filterByUser(0)">Tous</button>
        <?php foreach ($members as $m): ?>
            <button class="doc-filter-chip small <?= $filterUser === (int)$m['id'] ? 'active' : '' ?>"
                    onclick="filterByUser(<?= $m['id'] ?>)">
                <?= \App\Core\Avatar::html($m['avatar'] ?? null, $m['color'], $m['name'], 'user-avatar-xs') ?>
                <?= htmlspecialchars($m['name']) ?>
            </button>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Document grid -->
    <div class="docs-grid" id="docs-grid">
        <?php if (empty($documents)): ?>
            <div class="empty-state" style="grid-column:1/-1">
                Aucun document. Ajoutez vos pièces d'identité, justificatifs, contrats…
            </div>
        <?php else: ?>
        <?php foreach ($documents as $doc): ?>
        <div class="doc-card" data-id="<?= $doc['id'] ?>" data-type="<?= htmlspecialchars($doc['doc_type']) ?>"
             onclick="openDocDetail(<?= htmlspecialchars(json_encode($doc)) ?>)">

            <!-- Colored type header -->
            <div class="doc-card-head" style="background:<?= htmlspecialchars($doc['type_color']) ?>">
                <span class="doc-card-icon"><?= $doc['type_icon'] ?></span>
                <span class="doc-card-type"><?= htmlspecialchars($doc['type_label']) ?></span>
                <?php if (!empty($doc['custody_schedule_ids'])): ?><span title="Partagé avec le co-parent">🔒</span><?php endif; ?>
                <div class="doc-card-actions" onclick="event.stopPropagation()">
                    <button class="btn-icon-sm light" onclick="openEditDocModal(<?= htmlspecialchars(json_encode($doc)) ?>)" title="Modifier">✏️</button>
                    <button class="btn-icon-sm light" onclick="deleteDoc(<?= $doc['id'] ?>)" title="Supprimer">🗑</button>
                </div>
            </div>

            <div class="doc-card-body">
                <div class="doc-card-title"><?= htmlspecialchars($doc['title']) ?></div>

                <div class="doc-card-meta">
                    <?php if ($doc['issuer']): ?>
                        <span>🏢 <?= htmlspecialchars($doc['issuer']) ?></span>
                    <?php endif; ?>
                    <?php if ($doc['issue_date']): ?>
                        <span>📅 <?= DateHelper::format($doc['issue_date'], 'd/m/Y') ?></span>
                    <?php endif; ?>
                    <?php if ($doc['expiry_date']): ?>
                        <span class="doc-expiry <?= $doc['expiry_status'] ?>">
                            ⏳ <?= DateHelper::format($doc['expiry_date'], 'd/m/Y') ?>
                            <?php if ($doc['days_until_expiry'] !== null): ?>
                                <?php if ($doc['days_until_expiry'] < 0): ?>
                                    (expirée)
                                <?php elseif ($doc['days_until_expiry'] <= 90): ?>
                                    (<?= $doc['days_until_expiry'] ?>j)
                                <?php endif; ?>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="doc-card-footer">
                    <div class="doc-card-members">
                        <?php foreach ($doc['members'] as $m): ?>
                            <?= \App\Core\Avatar::html($m['avatar'] ?? null, $m['color'], $m['name'], 'user-avatar-xs', $m['name']) ?>
                        <?php endforeach; ?></div>
                    <?php if ($doc['file_path']): ?>
                        <a href="<?= BASE_URL ?>/documents/file/<?= $doc['id'] ?>" target="_blank"
                           class="doc-file-badge" onclick="event.stopPropagation()">📎 Voir</a>
                    <?php endif; ?>
                    <?php if ($doc['tags']): ?>
                        <?php foreach (array_slice(explode(',', $doc['tags']), 0, 3) as $tag): ?>
                            <span class="doc-tag"><?= htmlspecialchars(trim($tag)) ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ── Add / Edit Modal ─────────────────────────────────────────────────── -->
<div class="modal-overlay" id="doc-modal" style="display:none">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 id="doc-modal-title">Ajouter un document</h3>
            <button onclick="closeModal('doc-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="doc-id">

            <!-- File upload zone -->
            <div class="wm-upload-zone" id="doc-drop-zone"
                 ondragover="event.preventDefault();this.classList.add('drag-over')"
                 ondragleave="this.classList.remove('drag-over')"
                 ondrop="handleDocDrop(event)">
                <input type="file" id="doc-file" accept="image/*,application/pdf" style="display:none"
                       onchange="handleDocFile(this.files[0])">
                <div id="doc-upload-label" onclick="document.getElementById('doc-file').click()" style="cursor:pointer">
                    <span style="font-size:2rem">📄</span>
                    <p>Glissez votre document ici ou <u>cliquez pour choisir</u></p>
                    <small style="color:var(--text-muted)">JPEG, PNG, PDF — max 20 Mo · OCR + 2D-Doc automatique</small>
                </div>
                <div id="doc-file-preview" style="display:none"></div>
            </div>

            <!-- 2D-Doc scan actions (always visible) -->
            <div class="twodoc-actions">
                <button class="btn btn-secondary btn-sm" id="doc-btn-scan-image"
                        style="display:none" onclick="scan2DDocFromUpload()">🔲 Lire le 2D-Doc</button>
                <button class="btn btn-secondary btn-sm" onclick="openCameraScanner()">📷 Scanner avec la caméra</button>
                <span class="twodoc-hint">Lit le code 2D-Doc des CNI, avis d'imposition, cartes grises…</span>
            </div>

            <div id="doc-ocr-status" style="display:none;margin:.4rem 0;font-size:.8rem;color:var(--text-muted)"></div>

            <!-- 2D-Doc result panel -->
            <div id="doc-2ddoc-result" style="display:none" class="twodoc-result">
                <div class="twodoc-result-head">
                    <div id="doc-2ddoc-type" class="twodoc-type-badge"></div>
                    <button class="btn btn-sm btn-primary" onclick="apply2DocAndCollapse()">✅ Pré-remplir le formulaire</button>
                    <button class="btn btn-sm btn-secondary" onclick="document.getElementById('doc-2ddoc-result').style.display='none'">✕</button>
                </div>
                <table class="twodoc-fields-table" id="doc-2ddoc-fields"></table>
            </div>

            <!-- OCR detected type banner -->
            <div id="doc-detected-type" style="display:none;margin:.5rem 0;padding:.5rem .85rem;border-radius:var(--radius);font-size:.85rem;font-weight:500"></div>

            <div class="form-row" style="margin-top:.75rem">
                <div class="form-group" style="flex:2">
                    <label>Intitulé <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="doc-title" placeholder="Carte d'identité de Marie, Avis d'imposition 2024…">
                </div>
                <div class="form-group" style="flex:1">
                    <label>Appartient à</label>
                    <div class="member-picker" id="doc-member-picker">
                        <?php foreach ($members as $m): ?>
                        <label class="member-pick-item">
                            <input type="checkbox" class="doc-member-cb" value="<?= (int)$m['id'] ?>">
                            <?= \App\Core\Avatar::html($m['avatar'] ?? null, $m['color'], $m['name'], 'user-avatar-xs') ?>
                            <?= htmlspecialchars($m['name']) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <small class="form-hint">Sélectionnez un ou plusieurs membres</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Type de document</label>
                    <select id="doc-type">
                        <option value="auto">🤖 Détection automatique</option>
                        <?php foreach ($allTypes as $tKey => $tDef): ?>
                            <option value="<?= $tKey ?>"><?= $tDef['icon'] ?> <?= htmlspecialchars($tDef['label']) ?></option>
                        <?php endforeach; ?>
                        <option value="other">📄 Autre</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Organisme émetteur</label>
                    <input type="text" id="doc-issuer" placeholder="Mairie, DGFiP, employeur…">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Date d'émission</label>
                    <input type="date" id="doc-issue-date">
                </div>
                <div class="form-group">
                    <label>Date d'expiration</label>
                    <input type="date" id="doc-expiry-date">
                    <small style="color:var(--text-muted)">Laisser vide si illimité</small>
                </div>
            </div>

            <div class="form-group">
                <label>Tags <small style="color:var(--text-muted)">(séparés par des virgules)</small></label>
                <input type="text" id="doc-tags" placeholder="2024, important, à renouveler…">
            </div>

            <?php if (!empty($custodySchedules)): ?>
            <div class="form-group">
                <label class="radio-option" style="border:none;padding:0">
                    <input type="checkbox" id="doc-custody-toggle" onchange="document.getElementById('doc-custody-select-wrap').style.display = this.checked ? '' : 'none'">
                    <span>Garde alternée</span>
                </label>
                <div id="doc-custody-select-wrap" style="display:none;margin-top:.4rem">
                    <?php foreach ($custodySchedules as $cs): ?>
                        <label class="radio-option">
                            <input type="checkbox" class="doc-custody-child-cb" value="<?= (int)$cs['id'] ?>">
                            <span><?= htmlspecialchars($cs['child_name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                    <small class="form-hint">Ce document sera visible par le(s) co-parent(s) à accès restreint des enfants sélectionnés.</small>
                </div>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label>Notes</label>
                <textarea id="doc-notes" rows="2"></textarea>
            </div>

            <details id="doc-ocr-details" style="display:none">
                <summary style="cursor:pointer;font-size:.8rem;color:var(--text-muted)">Texte extrait par OCR</summary>
                <textarea id="doc-ocr-text" rows="5" style="font-size:.72rem;font-family:monospace;margin-top:.4rem"></textarea>
            </details>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('doc-modal')">Annuler</button>
            <button class="btn btn-primary" id="doc-save-btn" onclick="saveDoc()">Enregistrer</button>
        </div>
    </div>
</div>

<!-- ── Camera / 2D-Doc scanner Modal ────────────────────────────────────── -->
<div class="modal-overlay" id="twodoc-camera-modal" style="display:none">
    <div class="modal twodoc-camera-modal">
        <div class="modal-header">
            <h3>📷 Scanner le 2D-Doc</h3>
            <button onclick="stopCameraScanner()">✕</button>
        </div>
        <div class="modal-body" style="padding:0">
            <div class="camera-container">
                <video id="twodoc-video" playsinline autoplay muted></video>
                <!-- animated crosshair overlay -->
                <div class="camera-overlay">
                    <div class="camera-frame">
                        <span class="camera-corner tl"></span>
                        <span class="camera-corner tr"></span>
                        <span class="camera-corner bl"></span>
                        <span class="camera-corner br"></span>
                        <div class="camera-scan-line"></div>
                    </div>
                </div>
            </div>
            <p class="camera-hint" id="twodoc-camera-status">Pointez la caméra vers le code 2D-Doc</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="stopCameraScanner()">Annuler</button>
        </div>
    </div>
</div>

<!-- ── Detail Modal ─────────────────────────────────────────────────────── -->
<div class="modal-overlay" id="doc-detail-modal" style="display:none">
    <div class="modal modal-lg">
        <div class="modal-header" id="doc-detail-header">
            <h3 id="doc-detail-title">Document</h3>
            <button onclick="closeModal('doc-detail-modal')">✕</button>
        </div>
        <div class="modal-body" id="doc-detail-body"></div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('doc-detail-modal')">Fermer</button>
            <button class="btn btn-primary" id="doc-detail-edit-btn">✏️ Modifier</button>
        </div>
    </div>
</div>

<!-- ── Duplicate detection Modal ────────────────────────────────────────── -->
<div class="modal-overlay" id="duplicate-doc-modal" style="display:none">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h3>Document similaire trouvé</h3>
            <button onclick="closeModal('duplicate-doc-modal')">✕</button>
        </div>
        <div class="modal-body">
            <p>
                Un document au titre proche existe déjà : <strong id="duplicate-doc-title"></strong>
                (ajouté le <span id="duplicate-doc-date"></span>).
            </p>
            <p style="color:var(--text-muted);font-size:.85rem">
                « Remplacer » garde l'ancien en archive (retiré de la liste, mais consultable depuis
                l'historique du nouveau document) — ou gardez les deux comme documents distincts.
            </p>
        </div>
        <div class="modal-footer" style="flex-wrap:wrap">
            <button class="btn btn-secondary" onclick="closeModal('duplicate-doc-modal')">Annuler</button>
            <button class="btn btn-secondary" onclick="resolveDuplicateDoc('keep')">Garder les deux</button>
            <button class="btn btn-primary" onclick="resolveDuplicateDoc('replace')">Remplacer</button>
        </div>
    </div>
</div>

<script>
const DOC_TYPES = <?= json_encode(array_map(fn($k, $v) => ['key'=>$k,'label'=>$v['label'],'icon'=>$v['icon'],'color'=>$v['color']], array_keys($allTypes), $allTypes)) ?>;
const DOC_CURRENT_FILTER_TYPE = <?= json_encode($filterType) ?>;
const DOC_CURRENT_FILTER_USER = <?= json_encode($filterUser) ?>;
const DOC_CURRENT_USER_ID = <?= json_encode((int)($_SESSION['user_id'] ?? 0)) ?>;
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
