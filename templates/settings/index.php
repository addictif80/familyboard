<?php
$pageTitle = 'Paramètres';
$extraJs = ['settings.js'];
ob_start();
?>
<div class="settings-container">

    <!-- Profile -->
    <div class="card settings-section">
        <h3>👤 Mon profil</h3>
        <form method="POST" action="<?= BASE_URL ?>/settings/profile" enctype="multipart/form-data">
            <div class="profile-preview">
                <div class="user-avatar-lg" style="background:<?= htmlspecialchars($user['color']) ?>" id="avatar-preview">
                    <?php if ($user['avatar']): ?>
                        <img src="<?= BASE_URL . htmlspecialchars($user['avatar']) ?>" alt="">
                    <?php else: ?>
                        <?= mb_substr($user['name'], 0, 1) ?>
                    <?php endif; ?>
                </div>
                <label class="btn btn-secondary btn-sm" for="avatar-upload">
                    Changer l'avatar
                    <input type="file" id="avatar-upload" name="avatar" accept="image/*" style="display:none" onchange="previewAvatar(this)">
                </label>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Nom</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Couleur</label>
                    <input type="color" name="color" value="<?= htmlspecialchars($user['color']) ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" value="<?= htmlspecialchars($user['email']) ?>" readonly class="input-readonly">
            </div>
            <div class="form-group">
                <label>Nouveau mot de passe (laisser vide pour ne pas changer)</label>
                <input type="password" name="password" minlength="6" placeholder="••••••••">
            </div>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
        </form>
    </div>

    <!-- Family settings (admin only) -->
    <?php if ($user['role'] === 'admin'): ?>
    <div class="card settings-section">
        <h3>👨‍👩‍👧 Famille : <?= htmlspecialchars($family['name']) ?></h3>
        <form method="POST" action="<?= BASE_URL ?>/settings/family">
            <div class="form-row">
                <div class="form-group flex-1">
                    <label>Nom de la famille</label>
                    <input type="text" name="family_name" value="<?= htmlspecialchars($family['name']) ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label>🌍 Fuseau horaire</label>
                <select name="timezone">
                    <?php foreach (\App\Core\DateHelper::timezoneList() as $group => $zones): ?>
                        <optgroup label="<?= htmlspecialchars($group) ?>">
                            <?php foreach ($zones as $tz => $label): ?>
                                <option value="<?= $tz ?>" <?= ($family['timezone'] ?? 'Europe/Paris') === $tz ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                    (<?= (new \DateTime('now', new \DateTimeZone($tz)))->format('P') ?>)
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <small style="color:var(--text-muted)">
                    Heure actuelle dans ce fuseau : <strong><?= (new \DateTime('now', new \DateTimeZone($family['timezone'] ?? 'Europe/Paris')))->format('H:i') ?></strong>
                </small>
            </div>
            <div class="form-group">
                <label>📺 Ville pour la météo (écran mural)</label>
                <input type="text" name="weather_city"
                       value="<?= htmlspecialchars($family['weather_city'] ?? '') ?>"
                       placeholder="Paris, Lyon, Bordeaux…">
                <small style="color:var(--text-muted)">Affiché dans le bandeau de l'écran mural. Laisser vide pour utiliser la géolocalisation du navigateur.</small>
            </div>
            <div class="form-group" id="strix">
                <label>🔍 URL Strix (découverte de flux caméras)</label>
                <input type="url" name="strix_url"
                       value="<?= htmlspecialchars($family['strix_url'] ?? '') ?>"
                       placeholder="http://192.168.1.x:4567">
                <small style="color:var(--text-muted)">
                    Instance <a href="https://github.com/eduard256/Strix" target="_blank" rel="noopener">Strix</a> pour détecter automatiquement les flux RTSP/MJPEG.
                    Démarrer avec : <code>docker run -p 4567:4567 eduard256/strix</code>
                </small>
            </div>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
        </form>
        <div class="invite-section" style="margin-top:1rem">
            <strong>Code d'invitation :</strong>
            <code class="invite-code"><?= htmlspecialchars($family['invite_code']) ?></code>
            <button onclick="copyCode('<?= htmlspecialchars($family['invite_code']) ?>')" class="btn btn-secondary btn-sm">📋 Copier</button>
            <form method="POST" action="<?= BASE_URL ?>/settings/family/code" style="display:inline">
                <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirmSubmit(this.closest('form'),&quot;Régénérer le code ? L\'ancien ne fonctionnera plus.&quot;)">🔄 Régénérer</button>
            </form>
        </div>

        <!-- Email invitation -->
        <div style="margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid var(--border)">
            <h4 style="margin-bottom:.75rem">✉️ Inviter par email</h4>
            <div class="form-row" style="align-items:flex-end">
                <div class="form-group flex-2" style="margin-bottom:0">
                    <label>Adresse email de la personne à inviter</label>
                    <input type="email" id="invite-email" placeholder="prenom@exemple.fr">
                </div>
                <button class="btn btn-primary" onclick="sendInvitation()" style="white-space:nowrap">
                    Envoyer l'invitation
                </button>
            </div>
            <small style="color:var(--text-muted);display:block;margin-top:.4rem">
                Un lien valide 7 jours sera envoyé par email. Requiert la configuration SMTP.
            </small>
        </div>
    </div>

    <!-- Members -->
    <div class="card settings-section">
        <h3>Membres de la famille</h3>
        <div class="members-list">
            <?php foreach ($members as $member): ?>
                <div class="member-item">
                    <div class="user-avatar" style="background:<?= htmlspecialchars($member['color']) ?>">
                        <?= mb_substr($member['name'], 0, 1) ?>
                    </div>
                    <div class="member-info">
                        <strong><?= htmlspecialchars($member['name']) ?></strong>
                        <small><?= htmlspecialchars($member['email']) ?> · <?= $member['role'] === 'admin' ? '👑 Admin' : 'Membre' ?></small>
                    </div>
                    <?php if ($member['id'] !== $user['id']): ?>
                        <form method="POST" action="<?= BASE_URL ?>/settings/member/<?= $member['id'] ?>/remove" onsubmit="return confirmSubmit(this,'Retirer <?= htmlspecialchars($member['name']) ?> de la famille ?')">
                            <button type="submit" class="btn btn-danger btn-sm">Retirer</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- SMTP -->
    <div class="card settings-section">
        <h3>✉️ Serveur SMTP (notifications email)</h3>
        <form method="POST" action="<?= BASE_URL ?>/settings/smtp">
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
                    <input type="password" name="smtp_pass" value="<?= htmlspecialchars($smtp['password'] ?? '') ?>">
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
            <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;margin-top:.5rem">
                <button type="submit" class="btn btn-primary">Enregistrer la configuration SMTP</button>
                <?php if ($smtp): ?>
                <button type="button" class="btn btn-secondary" onclick="testSmtp(false)">🔌 Tester la connexion</button>
                <button type="button" class="btn btn-secondary" onclick="testSmtp(true)" title="Envoie un email réel à votre adresse">📨 Envoyer un email test</button>
                <?php endif; ?>
            </div>
        </form>
        <div id="smtp-test-result" style="display:none;margin-top:1rem"></div>
    </div>

    <!-- Email templates -->
    <div class="card settings-section">
        <h3>📝 Templates d'emails</h3>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Personnalisez le contenu des emails automatiques. Variables disponibles entre <code>{{'{{'}}double accolades{{'}}'}}</code>.
        </p>
        <div class="template-tabs">
            <?php foreach ($emailTemplates as $type => $tpl): ?>
            <div class="template-block" id="tpl-<?= $type ?>">
                <div class="template-header" onclick="toggleTemplate('<?= $type ?>')">
                    <strong><?= htmlspecialchars($tpl['label']) ?></strong>
                    <?php if ($tpl['is_custom']): ?>
                        <span class="badge-custom">Personnalisé</span>
                    <?php endif; ?>
                    <span class="toggle-icon">▼</span>
                </div>
                <div class="template-body" style="display:none">
                    <div style="margin-bottom:.5rem;font-size:.78rem;color:var(--text-muted)">
                        Variables : <?= implode(', ', array_map(fn($v) => '<code>{{' . $v . '}}</code>', \App\Models\EmailTemplate::variables($type))) ?>
                    </div>
                    <div class="form-group">
                        <label>Sujet</label>
                        <input type="text" id="tpl-subject-<?= $type ?>" value="<?= htmlspecialchars($tpl['subject']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Corps (HTML)</label>
                        <textarea id="tpl-body-<?= $type ?>" rows="6" style="font-family:monospace;font-size:.8rem"><?= htmlspecialchars($tpl['body']) ?></textarea>
                    </div>
                    <div style="display:flex;gap:.5rem">
                        <button class="btn btn-primary btn-sm" onclick="saveTemplate('<?= $type ?>')">Enregistrer</button>
                        <?php if ($tpl['is_custom']): ?>
                        <button class="btn btn-secondary btn-sm" onclick="resetTemplate('<?= $type ?>')">Réinitialiser</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Email logs -->
    <?php if (!empty($emailLogs)): ?>
    <div class="card settings-section">
        <h3>📨 Historique des emails</h3>
        <div class="email-log-table">
            <table style="width:100%;font-size:.82rem;border-collapse:collapse">
                <thead>
                    <tr style="text-align:left;border-bottom:2px solid var(--border)">
                        <th style="padding:.4rem .6rem">Date</th>
                        <th style="padding:.4rem .6rem">À</th>
                        <th style="padding:.4rem .6rem">Sujet</th>
                        <th style="padding:.4rem .6rem">Type</th>
                        <th style="padding:.4rem .6rem">Statut</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($emailLogs as $log): ?>
                    <tr style="border-bottom:1px solid var(--border)">
                        <td style="padding:.4rem .6rem;color:var(--text-muted)"><?= date('d/m H:i', strtotime($log['created_at'])) ?></td>
                        <td style="padding:.4rem .6rem"><?= htmlspecialchars($log['to_email']) ?></td>
                        <td style="padding:.4rem .6rem"><?= htmlspecialchars(mb_substr($log['subject'], 0, 50)) ?><?= strlen($log['subject']) > 50 ? '…' : '' ?></td>
                        <td style="padding:.4rem .6rem"><span class="badge"><?= htmlspecialchars($log['type']) ?></span></td>
                        <td style="padding:.4rem .6rem">
                            <span style="color:<?= $log['status'] === 'sent' ? 'var(--success)' : 'var(--danger)' ?>">
                                <?= $log['status'] === 'sent' ? '✓ Envoyé' : '✗ Échec' ?>
                            </span>
                            <?php if ($log['status'] === 'failed' && $log['error_message']): ?>
                                <br><small style="color:var(--danger);font-size:.7rem" title="<?= htmlspecialchars($log['error_message']) ?>">
                                    <?= htmlspecialchars(mb_substr($log['error_message'], 0, 60)) ?><?= mb_strlen($log['error_message']) > 60 ? '…' : '' ?>
                                </small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.template-block { border: 1px solid var(--border); border-radius: 8px; margin-bottom: .5rem; overflow: hidden; }
.template-header { display:flex; align-items:center; gap:.75rem; padding: .75rem 1rem; cursor:pointer; background:var(--bg); }
.template-header:hover { background: var(--bg-hover, #f5f5f5); }
.template-header strong { flex:1; }
.template-body { padding: 1rem; border-top: 1px solid var(--border); }
.badge-custom { background:#fef3c7;color:#92400e;padding:.15rem .5rem;border-radius:4px;font-size:.75rem; }
.toggle-icon { color:var(--text-muted);font-size:.75rem; }
</style>

<script>
function copyCode(code) {
    navigator.clipboard.writeText(code).then(() => Dialog.toast('Code copié !'));
}
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            const el = document.getElementById('avatar-preview');
            el.innerHTML = '<img src="' + e.target.result + '" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%">';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
async function testSmtp(sendReal = false) {
    const box = document.getElementById('smtp-test-result');
    const url = sendReal
        ? BASE_URL + '/api/settings/smtp/send-test'
        : BASE_URL + '/api/settings/smtp/test';
    box.style.display = 'none';
    box.innerHTML = '<p style="color:var(--text-muted)">⏳ Test en cours…</p>';
    box.style.display = 'block';
    try {
        const r = await apiFetch(url, { method: 'POST' });
        let html = '<div style="border:1px solid var(--border);border-radius:var(--radius);overflow:hidden">';
        for (const step of (r.steps || [])) {
            html += `<div style="display:flex;align-items:center;gap:.6rem;padding:.45rem .75rem;border-bottom:1px solid var(--border)">
                <span style="font-size:1rem">${step.ok ? '✅' : '❌'}</span>
                <strong style="min-width:140px;flex-shrink:0">${step.label}</strong>
                <code style="font-size:.75rem;color:var(--text-muted)">${step.detail}</code>
            </div>`;
        }
        html += '</div>';
        if (!r.ok && r.error) {
            html += `<p style="color:var(--danger);margin-top:.5rem;font-size:.85rem">❌ ${r.error}</p>`;
        } else if (r.ok) {
            const msg = sendReal
                ? '✅ Email envoyé ! Vérifiez votre boîte de réception (et les spams).'
                : '✅ Connexion SMTP réussie !';
            html += `<p style="color:var(--success);margin-top:.5rem;font-size:.85rem">${msg}</p>`;
        }
        box.innerHTML = html;
    } catch(e) {
        box.innerHTML = '<p style="color:var(--danger)">Erreur réseau.</p>';
    }
}
async function sendInvitation() {
    const email = document.getElementById('invite-email').value.trim();
    if (!email) { Dialog.toast('Entrez une adresse email.', 'error'); return; }
    const r = await apiFetch(BASE_URL + '/api/settings/invite', { method: 'POST', body: JSON.stringify({ email }) });
    if (r.success) {
        Dialog.toast('Invitation envoyée à ' + email + ' !');
        document.getElementById('invite-email').value = '';
    } else {
        Dialog.toast(r.error || 'Erreur lors de l\'envoi.', 'error');
    }
}
function toggleTemplate(type) {
    const body = document.querySelector('#tpl-' + type + ' .template-body');
    body.style.display = body.style.display === 'none' ? 'block' : 'none';
}
async function saveTemplate(type) {
    const subject = document.getElementById('tpl-subject-' + type).value;
    const body = document.getElementById('tpl-body-' + type).value;
    const r = await apiFetch(BASE_URL + '/api/settings/email-template', {
        method: 'POST',
        body: JSON.stringify({ type, subject, body })
    });
    if (r.success) Dialog.toast('Template enregistré !');
    else Dialog.toast(r.error || 'Erreur.', 'error');
}
async function resetTemplate(type) {
    if (!await Dialog.confirm('Réinitialiser ce template avec la valeur par défaut ?')) return;
    const r = await apiFetch(BASE_URL + '/api/settings/email-template/' + type + '/reset', { method: 'POST', body: '{}' });
    if (r.success) location.reload();
}
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
