<?php
$pageTitle = 'Additions';
$extraJs   = ['additions.js'];
ob_start();
?>
<div class="content-header">
    <h2>🧾 Additions</h2>
    <div style="display:flex;gap:.5rem">
        <button class="btn btn-secondary" onclick="openAdditionModal(false)">+ Créer une addition</button>
        <button class="btn btn-primary" onclick="openAdditionModal(true)">🪙 Créer une cagnotte</button>
    </div>
</div>
<p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1.25rem">
    Partagez une dépense (restaurant, cadeau commun, activité...) avec des membres de la famille,
    un co-parent, une famille amie, ou une personne invitée par e-mail sans compte. Chaque
    participant doit accepter individuellement. La cagnotte est gratuite : aucun paiement en ligne,
    juste un registre de perceptions saisi à la main.
</p>

<?php if (empty($additions)): ?>
    <p class="empty-state">Aucune addition pour l'instant.</p>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:1rem">
    <?php foreach ($additions as $a): ?>
    <div class="card" style="padding:1.25rem" data-addition-id="<?= (int)$a['id'] ?>" data-split-mode="<?= htmlspecialchars($a['split_mode']) ?>" data-is-cagnotte="<?= $a['is_cagnotte'] ? '1' : '0' ?>">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap">
            <div>
                <h3 style="margin:0"><?= $a['is_cagnotte'] ? '🪙 ' : '' ?><?= htmlspecialchars($a['title']) ?></h3>
                <?php if ($a['description']): ?><p style="color:var(--text-muted);font-size:.85rem;margin:.3rem 0"><?= nl2br(htmlspecialchars($a['description'])) ?></p><?php endif; ?>
                <p style="font-size:.85rem;color:var(--text-muted);margin:.2rem 0">Créée par <?= htmlspecialchars($a['creator_name']) ?></p>
            </div>
            <button class="btn btn-danger btn-sm" onclick="deleteAddition(<?= (int)$a['id'] ?>)">🗑️ Supprimer</button>
        </div>

        <?php if ($a['is_cagnotte']): ?>
            <?php $goal = (float)($a['cagnotte_goal'] ?? 0); $collected = (float)($a['collected_amount'] ?? 0); $pct = $goal > 0 ? min(100, round($collected / $goal * 100)) : null; ?>
            <div style="margin:.75rem 0">
                <strong><?= number_format($collected, 2, ',', ' ') ?> €</strong>
                <?php if ($goal > 0): ?> collectés sur <?= number_format($goal, 2, ',', ' ') ?> € (<?= $pct ?>%)
                    <div style="background:var(--card-bg-alt);border-radius:6px;height:8px;margin-top:.3rem;overflow:hidden">
                        <div style="background:var(--primary);height:100%;width:<?= $pct ?>%"></div>
                    </div>
                <?php else: ?> collectés<?php endif; ?>
            </div>
            <?php if (!empty($a['cagnotte_methods_list'])): ?>
            <p style="font-size:.82rem;color:var(--text-muted)">
                Moyens acceptés :
                <?php foreach ($a['cagnotte_methods_list'] as $m): ?>
                    <span class="badge-custom"><?= ['cash' => 'Espèces', 'transfer' => 'Virement', 'wero' => 'Wero'][$m] ?? $m ?></span>
                <?php endforeach; ?>
                <?php if ($a['cagnotte_iban']): ?> · IBAN : <code><?= htmlspecialchars($a['cagnotte_iban']) ?></code><?php endif; ?>
                <?php if ($a['cagnotte_wero_phone']): ?> · Wero : <code><?= htmlspecialchars($a['cagnotte_wero_phone']) ?></code><?php endif; ?>
            </p>
            <?php endif; ?>
            <?php if ($user['role'] === 'admin'): ?>
            <button class="btn btn-secondary btn-sm" onclick="openPaymentModal(<?= (int)$a['id'] ?>)">+ Enregistrer une perception</button>
            <?php endif; ?>
        <?php else: ?>
            <p style="margin:.5rem 0"><strong><?= number_format((float)$a['total_amount'], 2, ',', ' ') ?> €</strong>
                — répartition <?= $a['split_mode'] === 'percentage' ? 'en pourcentage' : 'en montant libre' ?></p>
        <?php endif; ?>

        <div style="margin-top:.75rem">
            <strong style="font-size:.85rem">Participants</strong>
            <ul style="list-style:none;padding:0;margin:.4rem 0 0;display:flex;flex-direction:column;gap:.3rem">
                <?php foreach ($a['participants'] as $p): ?>
                <li style="display:flex;align-items:center;gap:.5rem;font-size:.85rem">
                    <span style="flex:1"><?= htmlspecialchars($p['display_name']) ?><?= $p['guest_email'] ? ' (invité externe)' : '' ?></span>
                    <?php if (!$a['is_cagnotte']): ?>
                    <span><?= number_format((float)$p['share_value'], 2, ',', ' ') ?><?= $a['split_mode'] === 'percentage' ? ' %' : ' €' ?></span>
                    <?php endif; ?>
                    <span class="badge-custom"><?= ['pending' => 'En attente', 'accepted' => 'Accepté', 'declined' => 'Refusé'][$p['status']] ?? $p['status'] ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <button class="btn btn-secondary btn-sm" style="margin-top:.5rem" onclick="openInviteModal(<?= (int)$a['id'] ?>)">+ Inviter un participant</button>
        </div>

        <?php if ($a['is_cagnotte']): ?>
        <?php $payments = $a['payments'] ?? []; ?>
        <?php if (!empty($payments)): ?>
        <div style="margin-top:.75rem">
            <strong style="font-size:.85rem">Perceptions enregistrées</strong>
            <ul style="list-style:none;padding:0;margin:.4rem 0 0;display:flex;flex-direction:column;gap:.3rem">
                <?php foreach ($payments as $pay): ?>
                <li style="display:flex;align-items:center;gap:.5rem;font-size:.85rem">
                    <span style="flex:1"><?= htmlspecialchars($pay['payer_name']) ?> — <?= ['cash' => 'Espèces', 'transfer' => 'Virement', 'wero' => 'Wero'][$pay['method']] ?? $pay['method'] ?> — <?= htmlspecialchars(substr($pay['paid_at'], 0, 10)) ?></span>
                    <strong><?= number_format((float)$pay['amount'], 2, ',', ' ') ?> €</strong>
                    <?php if ($user['role'] === 'admin'): ?>
                        <button class="btn btn-secondary btn-sm" onclick="deletePayment(<?= (int)$a['id'] ?>, <?= (int)$pay['id'] ?>)">✕</button>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Créer une addition / cagnotte -->
<div class="modal-overlay" id="addition-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3 id="addition-modal-title">Nouvelle addition</h3>
            <button onclick="closeModal('addition-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="addition-is-cagnotte" value="0">
            <div class="form-group">
                <label>Titre</label>
                <input type="text" id="addition-title" placeholder="Restaurant, cadeau commun, activité…" required>
            </div>
            <div class="form-group">
                <label>Description (optionnel)</label>
                <textarea id="addition-description" rows="2"></textarea>
            </div>
            <div class="form-group" id="addition-amount-wrap">
                <label>Montant total (€)</label>
                <input type="number" id="addition-total-amount" min="0" step="0.01">
            </div>
            <div class="form-group" id="addition-split-wrap">
                <label>Répartition</label>
                <select id="addition-split-mode">
                    <option value="percentage">En pourcentage</option>
                    <option value="amount">En montant libre</option>
                </select>
            </div>
            <div id="addition-cagnotte-fields" style="display:none">
                <div class="form-group">
                    <label>Objectif (€, optionnel)</label>
                    <input type="number" id="addition-cagnotte-goal" min="0" step="0.01">
                </div>
                <div class="form-group">
                    <label>Moyens de paiement acceptés</label>
                    <label class="radio-option"><input type="checkbox" class="addition-cagnotte-method" value="cash"> Espèces</label>
                    <label class="radio-option"><input type="checkbox" class="addition-cagnotte-method" value="transfer"> Virement</label>
                    <label class="radio-option"><input type="checkbox" class="addition-cagnotte-method" value="wero"> Wero</label>
                </div>
                <div class="form-group">
                    <label>IBAN (si virement)</label>
                    <input type="text" id="addition-cagnotte-iban" placeholder="FR76...">
                </div>
                <div class="form-group">
                    <label>Numéro de téléphone Wero (si Wero)</label>
                    <input type="text" id="addition-cagnotte-wero" placeholder="06...">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('addition-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="saveAddition()">Créer</button>
        </div>
    </div>
</div>

<!-- Inviter un participant -->
<div class="modal-overlay" id="invite-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>Inviter un participant</h3>
            <button onclick="closeModal('invite-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="invite-addition-id">
            <div class="form-group">
                <label>Membre existant</label>
                <select id="invite-user-select">
                    <option value="">— Choisir —</option>
                    <?php foreach ($invitableUsers as $iu): ?>
                        <option value="<?= (int)$iu['id'] ?>">
                            <?= htmlspecialchars($iu['name']) ?><?= $iu['role'] === 'coparent' ? ' (co-parent)' : '' ?><?= !empty($iu['friend_family_name']) ? ' — ' . htmlspecialchars($iu['friend_family_name']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <p style="text-align:center;color:var(--text-muted);font-size:.85rem;margin:.5rem 0">— ou —</p>
            <div class="form-group">
                <label>E-mail d'une personne sans compte</label>
                <input type="email" id="invite-email" placeholder="prenom@exemple.fr">
            </div>
            <div class="form-group">
                <label>Nom (si e-mail renseigné)</label>
                <input type="text" id="invite-name">
            </div>
            <div class="form-group" id="invite-share-wrap">
                <label>Part <span id="invite-share-unit">(%)</span></label>
                <input type="number" id="invite-share-value" min="0" step="0.01">
            </div>
            <p id="invite-modal-error" style="color:var(--danger,#E74C3C);font-size:.82rem;display:none"></p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('invite-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="submitInvite()">Inviter</button>
        </div>
    </div>
</div>

<!-- Enregistrer une perception (cagnotte) -->
<div class="modal-overlay" id="payment-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>Enregistrer une perception</h3>
            <button onclick="closeModal('payment-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="payment-addition-id">
            <div class="form-group">
                <label>Qui a payé</label>
                <input type="text" id="payment-payer-name" required>
            </div>
            <div class="form-group">
                <label>Montant (€)</label>
                <input type="number" id="payment-amount" min="0" step="0.01" required>
            </div>
            <div class="form-group">
                <label>Moyen de paiement</label>
                <select id="payment-method">
                    <option value="cash">Espèces</option>
                    <option value="transfer">Virement</option>
                    <option value="wero">Wero</option>
                </select>
            </div>
            <div class="form-group">
                <label>Date</label>
                <input type="date" id="payment-date" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group">
                <label>Note (optionnel)</label>
                <input type="text" id="payment-note">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('payment-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="submitPayment()">Enregistrer</button>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
