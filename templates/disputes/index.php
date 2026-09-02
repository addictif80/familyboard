<?php
$pageTitle = 'Dossiers de litige';
$extraJs = ['vendor/qrcode.min.js', 'disputes.js'];
ob_start();

use App\Core\DateHelper;

$exchangeTypeLabel = ['telephone' => '📞 Téléphone', 'email' => '✉️ E-mail', 'courrier' => '📮 Courrier'];
?>
<div class="tasks-container">
    <div class="tasks-sidebar">
        <div class="tasks-sidebar-header">
            <h3>Dossiers</h3>
            <button class="btn-icon" onclick="openNewDisputeModal()" title="Nouveau dossier">+</button>
        </div>
        <ul class="lists-menu">
            <?php foreach ($disputes as $d): ?>
                <li class="list-item <?= $selected && (int)$selected['id'] === (int)$d['id'] ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>/disputes?id=<?= $d['id'] ?>" class="list-link">
                        <span class="list-dot" style="background:<?= $d['status'] === 'open' ? '#E67E22' : '#7F8C8D' ?>"></span>
                        <span class="list-name"><?= htmlspecialchars($d['title']) ?></span>
                        <?php if ($d['status'] === 'closed'): ?><span class="list-badge badge-task">Clos</span><?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
            <?php if (empty($disputes)): ?>
                <li class="empty-state">Aucun dossier.</li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="tasks-main">
        <?php if ($selected): ?>
            <div class="tasks-header">
                <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
                    <h2><?= htmlspecialchars($selected['title']) ?></h2>
                    <span class="badge"><?= $selected['status'] === 'open' ? '🟠 En cours' : '⚪ Clos' ?></span>
                </div>
                <div style="display:flex;gap:.4rem;flex-wrap:wrap">
                    <button class="btn btn-secondary btn-sm" onclick="openEditDisputeModal()">✏️ Modifier</button>
                    <button class="btn btn-secondary btn-sm" onclick="toggleDisputeStatus()"><?= $selected['status'] === 'open' ? '✅ Clore' : '🔄 Rouvrir' ?></button>
                    <button class="btn btn-secondary btn-sm" onclick="openShareModal()">🔗 Partager</button>
                    <button class="btn btn-danger btn-sm" onclick="deleteDispute()">🗑 Supprimer</button>
                </div>
            </div>

            <div class="card settings-section" style="margin-bottom:1rem">
                <div class="form-row">
                    <div><strong>Partie adverse</strong><br><?= htmlspecialchars($selected['opposing_party']) ?></div>
                    <div><strong>Date de début du litige</strong><br><?= DateHelper::format($selected['start_date'], 'd/m/Y') ?></div>
                    <div><strong>Créé par</strong><br><?= htmlspecialchars($selected['author_name']) ?></div>
                </div>
                <?php if (trim(strip_tags($selected['details'] ?? ''))): ?>
                <div style="margin-top:1rem">
                    <strong>Détails</strong>
                    <div style="margin-top:.3rem;white-space:pre-wrap"><?= nl2br(htmlspecialchars($selected['details'])) ?></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Pièces jointes -->
            <div class="card settings-section" style="margin-bottom:1rem">
                <h3>📎 Pièces jointes</h3>
                <div id="dispute-doc-list" style="margin-bottom:.75rem">
                    <?php if (empty($documents)): ?>
                        <p style="color:var(--text-muted);font-size:.85rem">Aucune pièce jointe.</p>
                    <?php endif; ?>
                    <?php foreach ($documents as $doc): ?>
                    <div class="member-item" data-doc-id="<?= $doc['id'] ?>">
                        <div class="member-info">
                            <strong><?= htmlspecialchars($doc['file_original']) ?></strong>
                            <small>ajouté par <?= htmlspecialchars($doc['uploader_name']) ?> le <?= DateHelper::fromUtc($doc['uploaded_at'], 'd/m/Y à H:i') ?></small>
                        </div>
                        <a class="btn btn-secondary btn-sm" href="<?= BASE_URL ?>/disputes/<?= $selected['id'] ?>/documents/<?= $doc['id'] ?>" target="_blank">Télécharger</a>
                        <button class="btn btn-danger btn-sm" onclick="deleteDisputeDocument(<?= $doc['id'] ?>)">Supprimer</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <form id="dispute-doc-form" onsubmit="return false">
                    <input type="file" id="dispute-doc-file" accept=".pdf,.doc,.docx,.xls,.xlsx,.eml,image/*">
                    <button type="button" class="btn btn-primary btn-sm" onclick="uploadDisputeDocument()">+ Ajouter</button>
                    <small style="display:block;color:var(--text-muted);margin-top:.3rem">PDF, Word, Excel, images ou e-mail (.eml) — 20 Mo max.</small>
                </form>
            </div>

            <!-- Traçabilité des échanges -->
            <div class="card settings-section" style="margin-bottom:1rem">
                <h3>🗂️ Traçabilité des échanges</h3>
                <div id="dispute-exchange-list" style="margin-bottom:.75rem">
                    <?php if (empty($exchanges)): ?>
                        <p style="color:var(--text-muted);font-size:.85rem">Aucun échange enregistré.</p>
                    <?php endif; ?>
                    <?php foreach ($exchanges as $ex): ?>
                    <div class="member-item" data-exchange-id="<?= $ex['id'] ?>">
                        <div class="member-info">
                            <strong><?= $exchangeTypeLabel[$ex['type']] ?> — <?= htmlspecialchars($ex['contact_info']) ?></strong>
                            <small><?= DateHelper::format($ex['exchange_date'], 'd/m/Y') ?> · <?= htmlspecialchars($ex['author_name']) ?><?= $ex['notes'] ? ' — ' . htmlspecialchars($ex['notes']) : '' ?></small>
                        </div>
                        <button class="btn btn-danger btn-sm" onclick="deleteDisputeExchange(<?= $ex['id'] ?>)">Supprimer</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Canal</label>
                        <select id="exchange-type">
                            <option value="telephone">📞 Téléphone</option>
                            <option value="email">✉️ E-mail</option>
                            <option value="courrier">📮 Courrier</option>
                        </select>
                    </div>
                    <div class="form-group flex-2">
                        <label id="exchange-contact-label">Numéro de téléphone</label>
                        <input type="text" id="exchange-contact" placeholder="06 12 34 56 78">
                    </div>
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" id="exchange-date" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group flex-2">
                        <label>Résumé (facultatif)</label>
                        <input type="text" id="exchange-notes" placeholder="Objet de l'échange…">
                    </div>
                </div>
                <button type="button" class="btn btn-primary btn-sm" onclick="addDisputeExchange()">+ Ajouter l'échange</button>
            </div>

            <?php if ($user['role'] === 'admin'): ?>
            <div class="card settings-section">
                <h3>🕵️ Journal des ouvertures du lien public</h3>
                <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:.75rem">Visible uniquement par les administrateurs de la famille.</p>
                <?php if (empty($accessLog)): ?>
                    <p style="color:var(--text-muted);font-size:.85rem">Aucune ouverture enregistrée.</p>
                <?php else: ?>
                <div style="max-height:220px;overflow-y:auto">
                    <?php foreach ($accessLog as $log): ?>
                    <div style="font-size:.82rem;padding:.3rem 0;border-bottom:1px solid var(--border)">
                        <?= DateHelper::fromUtc($log['accessed_at'], 'd/m/Y à H:i') ?> — <code><?= htmlspecialchars($log['ip_address'] ?? 'IP inconnue') ?></code>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state-card">
                <p>Créez un dossier pour commencer.</p>
                <button class="btn btn-primary" onclick="openNewDisputeModal()">+ Nouveau dossier</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- New/Edit Dispute Modal -->
<div class="modal-overlay" id="dispute-modal" style="display:none">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 id="dispute-modal-title">Nouveau dossier</h3>
            <button onclick="closeModal('dispute-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="dispute-id">
            <div class="form-group">
                <label>Titre <span style="color:var(--danger)">*</span></label>
                <input type="text" id="dispute-title" placeholder="Litige avec le voisin, Réclamation opérateur…">
            </div>
            <div class="form-row">
                <div class="form-group flex-2">
                    <label>Partie adverse <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="dispute-opposing-party">
                </div>
                <div class="form-group">
                    <label>Date de début du litige <span style="color:var(--danger)">*</span></label>
                    <input type="date" id="dispute-start-date">
                </div>
            </div>
            <div class="form-group">
                <label>Détails</label>
                <textarea id="dispute-details" rows="6" placeholder="Contexte, faits, demandes en cours…"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('dispute-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="saveDispute()">Enregistrer</button>
        </div>
    </div>
</div>

<!-- Share Modal -->
<div class="modal-overlay" id="share-dispute-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>Partager le dossier</h3>
            <button onclick="closeModal('share-dispute-modal')">✕</button>
        </div>
        <div class="modal-body" style="text-align:center">
            <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
                Toute personne avec ce lien peut consulter les détails et pièces jointes de ce
                dossier en lecture seule, sans compte — chaque ouverture est journalisée
                (date, heure, IP), visible par les administrateurs de la famille.
            </p>
            <div id="dispute-share-qr-container" style="display:inline-block;margin-bottom:1rem"></div>
            <p style="word-break:break-all;font-size:.8rem" id="dispute-share-link-text"></p>
            <div style="display:flex;gap:.5rem;justify-content:center;flex-wrap:wrap;margin-bottom:1rem">
                <button type="button" class="btn btn-secondary btn-sm" id="dispute-share-copy-btn">📋 Copier le lien</button>
                <a href="#" target="_blank" class="btn btn-secondary btn-sm" id="dispute-share-open-link">Ouvrir</a>
            </div>
            <div style="display:flex;gap:.5rem;justify-content:center">
                <button type="button" class="btn btn-secondary btn-sm" onclick="regenerateDisputeShare()">🔄 Régénérer</button>
                <button type="button" class="btn btn-danger btn-sm" onclick="revokeDisputeShare()">Révoquer</button>
            </div>
        </div>
    </div>
</div>

<script>
const DISPUTE_ID = <?= json_encode($selected['id'] ?? null) ?>;
const SELECTED_DISPUTE = <?= json_encode($selected) ?>;
const BASE_URL = <?= json_encode(BASE_URL) ?>;
const EXCHANGE_CONTACT_LABELS = {
    telephone: 'Numéro de téléphone',
    email: 'Adresse e-mail',
    courrier: 'Adresse postale',
};
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
