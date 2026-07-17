<?php
$pageTitle = 'Paramètres';
$extraJs = ['settings.js'];
ob_start();
?>
<div class="settings-container">

    <!-- Profile -->
    <div class="card settings-section">
        <h3>👤 Mon profil</h3>
        <form method="POST" action="<?= BASE_URL ?>/settings/profile" enctype="multipart/form-data"><?= \App\Core\Csrf::field() ?>
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

    <!-- Mes données -->
    <div class="card settings-section">
        <h3>📁 Mes données</h3>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Téléchargez une archive de vos données (profil, événements, tâches, documents…) au format ZIP.
        </p>
        <div style="display:flex;gap:.6rem;flex-wrap:wrap">
            <a href="<?= BASE_URL ?>/settings/export?scope=mine" class="btn btn-secondary">📥 Télécharger mes données</a>
            <?php if ($user['role'] === 'admin'): ?>
                <a href="<?= BASE_URL ?>/settings/export?scope=family" class="btn btn-secondary">📦 Télécharger les données de toute la famille</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Application Android -->
    <div class="card settings-section">
        <h3>📱 Application Android</h3>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Installez FamilyBoard sur votre téléphone Android. L'application se connecte à
            l'adresse de votre serveur (celle que vous utilisez déjà dans votre navigateur) —
            aucune donnée supplémentaire n'est envoyée ailleurs.
        </p>
        <div style="display:flex;gap:.6rem;flex-wrap:wrap">
            <a href="<?= BASE_URL ?>/app/android" class="btn btn-secondary">⬇️ Télécharger l'APK</a>
        </div>
        <p style="color:var(--text-muted);font-size:.75rem;margin-top:.6rem">
            L'installation depuis un fichier APK nécessite d'autoriser « sources inconnues » pour votre navigateur dans les réglages Android.
        </p>
    </div>

    <!-- Zone dangereuse -->
    <div class="card settings-section" style="border:1px solid var(--danger)">
        <h3 style="color:var(--danger)">⚠️ Zone dangereuse</h3>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Supprimer votre compte est définitif et irréversible.
            <?php if ($user['role'] === 'admin' && count($members) > 1): ?>
                En tant qu'administrateur, vous devrez transférer ce rôle à un autre membre ou supprimer toute la famille.
            <?php elseif ($user['role'] === 'admin'): ?>
                Vous êtes le seul membre de cette famille : supprimer votre compte supprimera aussi toute la famille et ses données.
            <?php endif; ?>
        </p>
        <button type="button" class="btn btn-danger" onclick="openDeleteAccountModal()">🗑 Supprimer mon compte</button>
    </div>

    <!-- Family settings (admin only) -->
    <?php if ($user['role'] === 'admin'): ?>
    <div class="card settings-section">
        <h3>👨‍👩‍👧 Famille : <?= htmlspecialchars($family['name']) ?></h3>
        <form method="POST" action="<?= BASE_URL ?>/settings/family"><?= \App\Core\Csrf::field() ?>
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
                <label>📺 Ville pour la météo</label>
                <div class="city-autocomplete" id="city-ac-wrap">
                    <input type="text" name="weather_city" id="city-ac-input" autocomplete="off"
                           value="<?= htmlspecialchars($family['weather_city'] ?? '') ?>"
                           placeholder="Tapez une ville…">
                    <ul class="city-ac-dropdown" id="city-ac-list" style="display:none"></ul>
                </div>
                <small style="color:var(--text-muted)">Affiché dans le bandeau du tableau de bord.</small>
            </div>
            <div class="form-group">
                <label>🎓 Zone scolaire (vacances scolaires sur le calendrier)</label>
                <?php
                    $currentZone = $family['school_zone'] ?? '';
                    // Suggest a zone from weather_city if none set
                    $suggestedZone = '';
                    if (!$currentZone && !empty($family['weather_city'])) {
                        $suggestedZone = \App\Models\SchoolHoliday::detectZone($family['weather_city']);
                    }
                ?>
                <select name="school_zone">
                    <option value="">— Désactivé —</option>
                    <option value="A" <?= $currentZone === 'A' ? 'selected' : '' ?>>Zone A</option>
                    <option value="B" <?= $currentZone === 'B' ? 'selected' : '' ?>>Zone B</option>
                    <option value="C" <?= $currentZone === 'C' ? 'selected' : '' ?>>Zone C</option>
                </select>
                <small style="color:var(--text-muted)">
                    <?php if ($suggestedZone): ?>
                        Zone détectée d'après votre ville : <strong>Zone <?= htmlspecialchars($suggestedZone) ?></strong>.
                    <?php endif; ?>
                    Zone A : Lyon, Bordeaux, Grenoble… · Zone B : Marseille, Nantes, Lille… · Zone C : Paris, Toulouse, Montpellier…
                </small>
            </div>
            <div class="form-group">
                <label>🎥 URL go2rtc (lecture RTSP en direct)</label>
                <input type="url" name="go2rtc_url"
                       value="<?= htmlspecialchars($family['go2rtc_url'] ?? '') ?>"
                       placeholder="http://192.168.1.10:1984">
                <small style="color:var(--text-muted)">
                    Le flux passe par PHP — <code>http://127.0.0.1:1984</code> fonctionne si go2rtc tourne sur le même serveur.<br>
                    Démarrer avec Docker : <code>docker run -d --network=host alexxit/go2rtc</code>
                </small>
            </div>
            <div class="form-group">
                <label>🔄 Synchronisation automatique des calendriers CalDAV</label>
                <?php $currentInterval = (int)($family['caldav_sync_interval'] ?? 0); ?>
                <select name="caldav_sync_interval">
                    <option value="0"    <?= $currentInterval === 0    ? 'selected' : '' ?>>— Désactivée —</option>
                    <option value="15"   <?= $currentInterval === 15   ? 'selected' : '' ?>>Toutes les 15 minutes</option>
                    <option value="30"   <?= $currentInterval === 30   ? 'selected' : '' ?>>Toutes les 30 minutes</option>
                    <option value="60"   <?= $currentInterval === 60   ? 'selected' : '' ?>>Toutes les heures</option>
                    <option value="120"  <?= $currentInterval === 120  ? 'selected' : '' ?>>Toutes les 2 heures</option>
                    <option value="360"  <?= $currentInterval === 360  ? 'selected' : '' ?>>Toutes les 6 heures</option>
                    <option value="720"  <?= $currentInterval === 720  ? 'selected' : '' ?>>Toutes les 12 heures</option>
                    <option value="1440" <?= $currentInterval === 1440 ? 'selected' : '' ?>>Une fois par jour</option>
                </select>
                <small style="color:var(--text-muted)">
                    Fréquence à laquelle le cron synchronise automatiquement tous les calendriers CalDAV de la famille.<br>
                    Nécessite que le script <code>cron.php</code> soit planifié (ex. toutes les minutes : <code>* * * * * php /chemin/familyboard/cron.php</code>).
                </small>
            </div>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
        </form>
        <div class="invite-section" style="margin-top:1rem">
            <strong>Code d'invitation :</strong>
            <code class="invite-code"><?= htmlspecialchars($family['invite_code']) ?></code>
            <button onclick="copyCode('<?= htmlspecialchars($family['invite_code']) ?>')" class="btn btn-secondary btn-sm">📋 Copier</button>
            <form method="POST" action="<?= BASE_URL ?>/settings/family/code" style="display:inline"><?= \App\Core\Csrf::field() ?>
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

    <!-- Push notifications -->
    <div class="card settings-section">
        <h3>🔔 Notifications push</h3>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Recevez une notification directement sur cet appareil (ordinateur ou mobile) dès qu'un événement
            vous concerne, même quand l'application est fermée.
        </p>
        <button type="button" class="btn btn-primary" id="push-toggle-btn" onclick="togglePushNotifications()">
            Activer les notifications push
        </button>
        <p id="push-status" class="push-status" style="font-size:.8rem;margin-top:.5rem"></p>
    </div>

    <!-- Modules (admin only) -->
    <?php if ($user['role'] === 'admin'): ?>
    <?php $_disabledMods = \App\Models\Family::getDisabledModules($family ?? []); ?>
    <div class="card settings-section">
        <h3>🧩 Modules actifs</h3>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Tous les modules sont activés par défaut. Décochez ceux que vous souhaitez masquer pour toute la famille.
        </p>
        <form method="POST" action="<?= BASE_URL ?>/settings/modules"><?= \App\Core\Csrf::field() ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.6rem;margin-bottom:1rem">
                <?php foreach (\App\Models\Family::MODULES as $slug => $mod): ?>
                <label style="display:flex;align-items:center;gap:.55rem;padding:.5rem .65rem;border:1px solid var(--border);border-radius:8px;cursor:pointer;transition:background .12s"
                       onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background=''">
                    <input type="checkbox" name="modules[]" value="<?= $slug ?>"
                           <?= !in_array($slug, $_disabledMods) ? 'checked' : '' ?>
                           style="width:16px;height:16px;cursor:pointer">
                    <span><?= $mod['icon'] ?> <?= htmlspecialchars($mod['label']) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="btn btn-primary">Enregistrer les modules</button>
        </form>
    </div>
    <?php endif; ?>

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
                        <small>
                            <?= htmlspecialchars($member['email']) ?> ·
                            <?php if ($member['role'] === 'admin'): ?>
                                👑 Admin
                            <?php elseif ($member['role'] === 'coparent'): ?>
                                🔒 Co-parent (accès restreint)
                                <?php if (!empty($coparentChildren[$member['id']])): ?>
                                    — <?= htmlspecialchars(implode(', ', $coparentChildren[$member['id']])) ?>
                                <?php endif; ?>
                            <?php else: ?>
                                Membre
                            <?php endif; ?>
                        </small>
                    </div>
                    <?php if ($member['id'] !== $user['id']): ?>
                        <form method="POST" action="<?= BASE_URL ?>/settings/member/<?= $member['id'] ?>/remove" onsubmit="return confirmSubmit(this,'Retirer <?= htmlspecialchars($member['name']) ?> de la famille ?')"><?= \App\Core\Csrf::field() ?>
                            <button type="submit" class="btn btn-danger btn-sm">Retirer</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Sitter access -->
    <?php $_disabledModsForSitter = \App\Models\Family::getDisabledModules($family ?? []); ?>
    <?php if (!in_array('sitter', $_disabledModsForSitter)): ?>
    <div class="card settings-section">
        <h3>👶 Accès baby-sitter</h3>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Générez un lien temporaire donnant un accès limité en lecture seule (planning du jour, tâches
            ouvertes, fiches urgence) — sans connexion, sans accès au reste des données familiales.
        </p>
        <div class="form-row">
            <div class="form-group flex-2">
                <label>Libellé</label>
                <input type="text" id="sitter-label" placeholder="Ex : Baby-sitter mardi soir">
            </div>
            <div class="form-group">
                <label>Valide pendant</label>
                <select id="sitter-hours">
                    <option value="6">6 heures</option>
                    <option value="12" selected>12 heures</option>
                    <option value="24">24 heures</option>
                    <option value="72">3 jours</option>
                </select>
            </div>
        </div>
        <button type="button" class="btn btn-primary" onclick="createSitterLink()">+ Générer un lien</button>
        <div id="sitter-new-link" style="margin-top:1rem"></div>

        <div id="sitter-links-list" style="margin-top:1.25rem">
            <?php foreach ($sitterLinks as $link): ?>
                <?php /* expires_at is stored in UTC (matches SQL NOW()) — parse it as such, not as local time */ ?>
                <?php $active = !$link['revoked_at'] && strtotime($link['expires_at'] . ' UTC') > time(); ?>
                <div class="member-item" data-sitter-id="<?= $link['id'] ?>">
                    <div class="member-info">
                        <strong><?= htmlspecialchars($link['label']) ?></strong>
                        <small>
                            <?= $active ? '✅ Actif' : '⛔ ' . ($link['revoked_at'] ? 'Révoqué' : 'Expiré') ?>
                            · expire le <?= \App\Core\DateHelper::fromUtc($link['expires_at'], 'd/m/Y à H:i') ?>
                        </small>
                    </div>
                    <?php if ($active): ?>
                    <button class="btn btn-danger btn-sm" onclick="revokeSitterLink(<?= $link['id'] ?>)">Révoquer</button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Kiosk access (écran mural) -->
    <?php $_disabledModsForKiosk = \App\Models\Family::getDisabledModules($family ?? []); ?>
    <?php if ($user['role'] === 'admin' && !in_array('kiosk', $_disabledModsForKiosk)): ?>
    <div class="card settings-section">
        <h3>🖥️ Écran mural (mode kiosque)</h3>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Générez un accès permanent pour une tablette dédiée (Android/iOS) affichée au mur : tâches et
            courses (avec ajout et coche), contacts, événements et repas, actualisés automatiquement.
            Quand un accès baby-sitter est actif, l'écran bascule sur un QR code au lieu d'afficher les
            données familiales.
        </p>
        <div class="form-row">
            <div class="form-group flex-2">
                <label>Libellé</label>
                <input type="text" id="kiosk-label" placeholder="Ex : Tablette cuisine">
            </div>
        </div>
        <button type="button" class="btn btn-primary" onclick="createKioskLink()">+ Générer un accès</button>
        <div id="kiosk-new-link" style="margin-top:1rem"></div>

        <div id="kiosk-links-list" style="margin-top:1.25rem">
            <?php if (empty($kioskLinks)): ?>
                <p style="color:var(--text-muted);font-size:.85rem">Aucun accès kiosque créé.</p>
            <?php endif; ?>
            <?php foreach ($kioskLinks as $link): ?>
                <?php $active = !$link['revoked_at']; ?>
                <div class="member-item" data-kiosk-id="<?= $link['id'] ?>">
                    <div class="member-info">
                        <strong><?= htmlspecialchars($link['label']) ?></strong>
                        <small><?= $active ? '✅ Actif' : '⛔ Révoqué' ?> · créé le <?= \App\Core\DateHelper::fromUtc($link['created_at'], 'd/m/Y') ?></small>
                    </div>
                    <?php if ($active): ?>
                    <button class="btn btn-danger btn-sm" onclick="revokeKioskLink(<?= $link['id'] ?>)">Révoquer</button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

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
                        <td style="padding:.4rem .6rem;color:var(--text-muted)"><?= \App\Core\DateHelper::fromUtc($log['created_at'], 'd/m H:i') ?></td>
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

<!-- Delete account modal -->
<div class="modal-overlay" id="delete-account-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>⚠️ Supprimer mon compte</h3>
            <button onclick="closeModal('delete-account-modal')">✕</button>
        </div>
        <div class="modal-body">
            <div id="dam-admin-choice" style="display:none">
                <p>Vous êtes administrateur de cette famille. Avant de supprimer votre compte, choisissez :</p>
                <div class="form-group">
                    <label><input type="radio" name="dam-action" value="transfer" checked> Transférer le rôle admin à :</label>
                    <select id="dam-transfer-target"></select>
                </div>
                <div class="form-group">
                    <label><input type="radio" name="dam-action" value="delete_family"> Supprimer toute la famille et toutes ses données (irréversible)</label>
                </div>
            </div>
            <div id="dam-simple-notice" style="display:none">
                <p id="dam-simple-text"></p>
            </div>
            <div class="form-group">
                <label>Tapez <strong>SUPPRIMER</strong> pour confirmer</label>
                <input type="text" id="dam-confirm-text" placeholder="SUPPRIMER" autocomplete="off">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('delete-account-modal')">Annuler</button>
            <button class="btn btn-danger" onclick="confirmDeleteAccount()">Supprimer définitivement</button>
        </div>
    </div>
</div>

<script>
// Prevent double-submission on all settings forms
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.settings-container form').forEach(form => {
        form.addEventListener('submit', e => {
            const btn = form.querySelector('button[type="submit"]');
            if (!btn || btn.disabled) { e.preventDefault(); return; }
            btn.disabled = true;
            btn.textContent = 'Enregistrement…';
        });
    });
});
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
// ── City autocomplete ──────────────────────────────────────────
(function () {
    const input = document.getElementById('city-ac-input');
    const list  = document.getElementById('city-ac-list');
    if (!input) return;

    let timer = null;
    let activeIdx = -1;

    input.addEventListener('input', () => {
        clearTimeout(timer);
        const q = input.value.trim();
        if (q.length < 2) { hide(); return; }
        timer = setTimeout(() => fetchCities(q), 280);
    });

    input.addEventListener('keydown', e => {
        const items = list.querySelectorAll('li');
        if (!items.length) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); setActive(activeIdx + 1, items); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(activeIdx - 1, items); }
        else if (e.key === 'Enter' && activeIdx >= 0) { e.preventDefault(); items[activeIdx].click(); }
        else if (e.key === 'Escape') { hide(); }
    });

    document.addEventListener('click', e => {
        if (!e.target.closest('#city-ac-wrap')) hide();
    });

    async function fetchCities(q) {
        try {
            const url = 'https://geocoding-api.open-meteo.com/v1/search?name=' +
                encodeURIComponent(q) + '&count=6&language=fr&format=json';
            const res  = await fetch(url);
            const data = await res.json();
            render(data.results || []);
        } catch (_) { hide(); }
    }

    function render(results) {
        if (!results.length) { hide(); return; }
        activeIdx = -1;
        list.innerHTML = '';
        results.forEach(r => {
            const parts = [r.name];
            if (r.admin1) parts.push(r.admin1);
            if (r.country) parts.push(r.country);
            const label = parts.join(', ');
            const li = document.createElement('li');
            li.className = 'city-ac-item';
            li.innerHTML = '<span class="city-ac-name">' + esc(r.name) + '</span>' +
                           '<span class="city-ac-sub">' + esc(parts.slice(1).join(', ')) + '</span>';
            li.addEventListener('mousedown', e => { e.preventDefault(); select(r.name); });
            list.appendChild(li);
        });
        list.style.display = 'block';
    }

    function select(name) {
        input.value = name;
        hide();
    }

    function setActive(idx, items) {
        items.forEach(i => i.classList.remove('active'));
        activeIdx = Math.max(0, Math.min(idx, items.length - 1));
        items[activeIdx].classList.add('active');
        input.value = items[activeIdx].querySelector('.city-ac-name').textContent;
    }

    function hide() { list.style.display = 'none'; activeIdx = -1; }

    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }
})();

// ── Suppression de compte ───────────────────────────────────────
const DAM_IS_ADMIN = <?= $user['role'] === 'admin' ? 'true' : 'false' ?>;
const DAM_OTHER_MEMBERS = <?= json_encode(array_values(array_map(
    fn($m) => ['id' => $m['id'], 'name' => $m['name']],
    array_filter($members, fn($m) => (int)$m['id'] !== (int)$user['id'])
))) ?>;

function openDeleteAccountModal() {
    document.getElementById('dam-confirm-text').value = '';
    document.getElementById('dam-admin-choice').style.display = 'none';
    document.getElementById('dam-simple-notice').style.display = 'none';

    if (DAM_IS_ADMIN && DAM_OTHER_MEMBERS.length > 0) {
        const sel = document.getElementById('dam-transfer-target');
        sel.innerHTML = DAM_OTHER_MEMBERS.map(m => `<option value="${m.id}">${escapeHtml(m.name)}</option>`).join('');
        document.getElementById('dam-admin-choice').style.display = '';
    } else {
        document.getElementById('dam-simple-text').textContent = DAM_IS_ADMIN
            ? "Vous êtes le seul membre de cette famille : la suppression de votre compte supprimera aussi toute la famille et ses données."
            : "Votre compte et les données que vous avez créées seront définitivement supprimés.";
        document.getElementById('dam-simple-notice').style.display = '';
    }
    openModal('delete-account-modal');
}

async function confirmDeleteAccount() {
    if (document.getElementById('dam-confirm-text').value.trim().toUpperCase() !== 'SUPPRIMER') {
        Dialog.toast('Tapez SUPPRIMER pour confirmer.', 'error');
        return;
    }
    const body = {};
    if (DAM_IS_ADMIN && DAM_OTHER_MEMBERS.length > 0) {
        const action = document.querySelector('input[name="dam-action"]:checked').value;
        body.action = action;
        if (action === 'transfer') body.transfer_to_user_id = document.getElementById('dam-transfer-target').value;
    }
    const r = await apiFetch(BASE_URL + '/settings/delete-account', { method: 'POST', body: JSON.stringify(body) });
    if (r.success) {
        window.location.href = r.redirect || (BASE_URL + '/login');
    } else {
        Dialog.toast(r.error || 'Erreur.', 'error');
    }
}
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
