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
                <button type="submit" class="btn btn-primary" style="align-self:end">Enregistrer</button>
            </div>
        </form>
        <div class="invite-section">
            <strong>Code d'invitation :</strong>
            <code class="invite-code"><?= htmlspecialchars($family['invite_code']) ?></code>
            <button onclick="copyCode('<?= htmlspecialchars($family['invite_code']) ?>')" class="btn btn-secondary btn-sm">📋 Copier</button>
            <form method="POST" action="<?= BASE_URL ?>/settings/family/code" style="display:inline">
                <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Régénérer le code ? L\'ancien ne fonctionnera plus.')">🔄 Régénérer</button>
            </form>
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
                        <form method="POST" action="<?= BASE_URL ?>/settings/member/<?= $member['id'] ?>/remove" onsubmit="return confirm('Retirer <?= htmlspecialchars($member['name']) ?> de la famille ?')">
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
            <button type="submit" class="btn btn-primary">Enregistrer la configuration SMTP</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
function copyCode(code) {
    navigator.clipboard.writeText(code).then(() => alert('Code copié !'));
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
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
