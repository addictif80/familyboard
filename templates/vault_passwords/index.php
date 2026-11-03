<?php
$pageTitle = 'Coffre-fort de mots de passe';
$extraJs = ['bw-crypto.js', 'vaultwarden-vault.js'];
ob_start();
?>
<div class="tasks-main" style="width:100%">
    <div class="tasks-header">
        <h2>🔐 Coffre-fort de mots de passe</h2>
    </div>
    <p style="color:var(--text-muted);font-size:.85rem;margin-top:-.5rem">
        Un coffre-fort chiffré pour vos mots de passe et codes de double authentification,
        hébergé séparément de FamilyBoard (comme Bitwarden) — votre mot de passe maître n'est
        jamais connu de FamilyBoard.
    </p>

    <div class="card settings-section">
        <?php if (empty($user['vault_invited_at'])): ?>
            <button type="button" class="btn btn-primary" id="vault-invite-btn" onclick="requestVaultInvite()">Créer mon coffre-fort</button>
        <?php else: ?>
            <p class="alert alert-success" style="margin-bottom:.75rem">
                Invitation envoyée le <?= htmlspecialchars(substr($user['vault_invited_at'], 0, 10)) ?>. Vérifiez vos e-mails
                pour finaliser la création de votre coffre.
            </p>
        <?php endif; ?>
        <a href="<?= htmlspecialchars(\App\Models\VaultwardenSettings::get()['url'] ?? '#') ?>" target="_blank" rel="noopener" class="btn btn-secondary" style="margin-top:.5rem">
            🔗 Ouvrir mon coffre-fort
        </a>
        <p style="font-size:.8rem;margin-top:.5rem" id="vault-invite-message"></p>
        <p style="color:var(--text-muted);font-size:.78rem;margin-top:.75rem">
            Pour le remplissage automatique des mots de passe, installez l'application ou l'extension
            <a href="https://bitwarden.com/download/" target="_blank" rel="noopener">Bitwarden</a> et connectez-la à votre coffre
            avec l'adresse de votre instance auto-hébergée.
        </p>

        <?php if (!empty($user['vault_invited_at'])): ?>
        <hr style="margin:1rem 0;border-color:var(--border)">
        <div id="bw-locked-panel">
            <button type="button" class="btn btn-secondary" onclick="bwOpenUnlockModal()">🔓 Gérer mes identifiants dans FamilyBoard</button>
        </div>
        <div id="bw-unlocked-panel" style="display:none">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem">
                <strong>Mes identifiants</strong>
                <div style="display:flex;gap:.5rem">
                    <button type="button" class="btn btn-primary btn-sm" onclick="bwOpenNewItemModal()">+ Ajouter</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="bwLock()">🔒 Verrouiller</button>
                </div>
            </div>
            <div id="bw-items-list"></div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Déverrouiller le coffre Vaultwarden -->
<div class="modal-overlay" id="bw-unlock-modal" style="display:none">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h3>🔓 Déverrouiller mon coffre</h3>
            <button onclick="closeModal('bw-unlock-modal')">✕</button>
        </div>
        <div class="modal-body">
            <div id="bw-unlock-kdf-warning" style="display:none;background:color-mix(in srgb, #E67E22 12%, transparent);border-left:3px solid #E67E22;border-radius:6px;padding:.75rem 1rem;font-size:.85rem">
                Ce compte utilise un type de dérivation de clé (Argon2id) non pris en charge par cette vue intégrée.
                Utilisez le bouton « 🔗 Ouvrir mon coffre-fort » ci-dessus pour y accéder directement.
            </div>
            <div class="form-group" id="bw-unlock-password-group" style="display:none">
                <label>Mot de passe maître</label>
                <input type="password" id="bw-unlock-password" onkeydown="if(event.key==='Enter')bwUnlock()">
                <small class="form-hint">Jamais envoyé à FamilyBoard — tout le déchiffrement se fait dans votre navigateur.</small>
            </div>
            <p style="color:var(--danger);font-size:.85rem" id="bw-unlock-error"></p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('bw-unlock-modal')">Annuler</button>
            <button class="btn btn-primary" id="bw-unlock-submit" style="display:none" onclick="bwUnlock()">Déverrouiller</button>
        </div>
    </div>
</div>

<!-- Ajouter/modifier un identifiant -->
<div class="modal-overlay" id="bw-item-modal" style="display:none">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h3 id="bw-item-modal-title">Nouvel identifiant</h3>
            <button onclick="closeModal('bw-item-modal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="bw-item-idx">
            <div class="form-group">
                <label>Nom <span style="color:var(--danger)">*</span></label>
                <input type="text" id="bw-item-name" placeholder="Netflix, impôts.gouv.fr…">
            </div>
            <div class="form-group">
                <label>Identifiant / e-mail</label>
                <input type="text" id="bw-item-username">
            </div>
            <div class="form-group">
                <label>Mot de passe</label>
                <input type="text" id="bw-item-password" style="font-family:monospace">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('bw-item-modal')">Annuler</button>
            <button class="btn btn-primary" onclick="bwSaveItem()">Enregistrer</button>
        </div>
    </div>
</div>
<script>
const BW_USER_EMAIL = <?= json_encode($user['email']) ?>;

async function requestVaultInvite() {
    const btn = document.getElementById('vault-invite-btn');
    const msg = document.getElementById('vault-invite-message');
    btn.disabled = true;
    btn.textContent = 'Envoi en cours…';
    const r = await apiFetch(BASE_URL + '/settings/vault/invite', { method: 'POST' });
    if (r.success) {
        if (r.already_existed) {
            Dialog.toast('Vous avez déjà un coffre sur cette instance — vous pouvez le gérer ci-dessous.', 'success');
            setTimeout(() => window.location.reload(), 1800);
            return;
        }
        window.location.reload();
        return;
    }
    msg.style.color = 'var(--danger)';
    msg.textContent = r.error || 'Erreur lors de l\'envoi de l\'invitation.';
    btn.disabled = false;
    btn.textContent = 'Créer mon coffre-fort';
}
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
