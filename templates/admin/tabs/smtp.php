        <h2>Configuration SMTP (globale)</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Un seul serveur SMTP est utilisé pour l'envoi d'emails de toutes les familles inscrites.
        </p>
        <form method="POST" action="<?= BASE_URL ?>/admin/smtp" class="card" style="padding:1.25rem;max-width:640px"><?= \App\Core\Csrf::field() ?>
            <div class="form-row">
                <div class="form-group flex-2">
                    <label>Serveur SMTP</label>
                    <input type="text" name="smtp_host" value="<?= htmlspecialchars($smtp['host'] ?? '') ?>" placeholder="smtp.gmail.com">
                </div>
                <div class="form-group">
                    <label>Port</label>
                    <input type="number" name="smtp_port" value="<?= htmlspecialchars($smtp['port'] ?? '587') ?>">
                </div>
                <div class="form-group">
                    <label>Chiffrement</label>
                    <select name="smtp_encryption">
                        <option value="tls" <?= ($smtp['encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                        <option value="ssl" <?= ($smtp['encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                        <option value="none" <?= ($smtp['encryption'] ?? '') === 'none' ? 'selected' : '' ?>>Aucun</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Identifiant</label>
                    <input type="text" name="smtp_user" value="<?= htmlspecialchars($smtp['username'] ?? '') ?>" placeholder="user@gmail.com">
                </div>
                <div class="form-group">
                    <label>Mot de passe</label>
                    <input type="password" name="smtp_pass" placeholder="<?= !empty($smtp['password']) ? '•••••••• (laisser vide pour conserver)' : '' ?>" autocomplete="new-password">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Email expéditeur</label>
                    <input type="email" name="smtp_from_email" value="<?= htmlspecialchars($smtp['from_email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Nom expéditeur</label>
                    <input type="text" name="smtp_from_name" value="<?= htmlspecialchars($smtp['from_name'] ?? 'FamilyBoard') ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Enregistrer la configuration SMTP</button>
        </form>

        <?php if ($smtp): ?>
        <div class="card" style="padding:1.25rem;max-width:640px;margin-top:1rem">
            <h3 style="margin-top:0">Tester la configuration</h3>
            <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;margin-bottom:.75rem">
                <button type="button" class="btn btn-secondary" onclick="testSmtp()">🔌 Tester la connexion</button>
                <input type="email" id="smtp-test-email" placeholder="destinataire@exemple.com" style="flex:1;min-width:220px">
                <button type="button" class="btn btn-secondary" onclick="sendTestSmtpEmail()">📨 Envoyer un email test</button>
            </div>
            <div id="smtp-test-result"></div>
        </div>
        <?php endif; ?>

