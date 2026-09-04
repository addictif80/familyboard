<?php
$pageTitle = 'Paramètres';
$extraJs = ['vendor/qrcode.min.js', 'settings.js'];
ob_start();
?>
<?php $isCoparentSettings = ($user['role'] ?? null) === 'coparent'; ?>
<div class="settings-container">

    <?php if (!$isCoparentSettings): ?>
    <div class="settings-tabs">
        <button type="button" class="settings-tab-btn" data-tab="compte" onclick="switchSettingsTab('compte')">👤 Mon compte</button>
        <button type="button" class="settings-tab-btn" data-tab="app" onclick="switchSettingsTab('app')">📲 Application</button>
        <button type="button" class="settings-tab-btn" data-tab="famille" onclick="switchSettingsTab('famille')">👨‍👩‍👧 Famille</button>
        <button type="button" class="settings-tab-btn" data-tab="acces" onclick="switchSettingsTab('acces')">🔗 Accès partagés</button>
        <?php if ($user['role'] === 'admin'): ?>
        <button type="button" class="settings-tab-btn" data-tab="abonnement" onclick="switchSettingsTab('abonnement')">💳 Abonnement</button>
        <?php endif; ?>
        <?php if (!empty($emailLogs)): ?>
        <button type="button" class="settings-tab-btn" data-tab="historique" onclick="switchSettingsTab('historique')">📨 Historique</button>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ═══ Onglet : Mon compte ═══ -->
    <div class="settings-tab-panel" data-tab="compte">

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
                <label>🎂 Date de naissance (optionnel)</label>
                <input type="date" name="birthday" value="<?= htmlspecialchars($user['birthday'] ?? '') ?>">
                <small style="color:var(--text-muted)">Permet à votre famille de voir un rappel avant votre anniversaire.</small>
            </div>
            <div class="form-group">
                <label>📞 Téléphone (optionnel)</label>
                <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="06 12 34 56 78">
                <small style="color:var(--text-muted)">Affiché à une baby-sitter pendant un accès actif — nulle part ailleurs.</small>
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:.5rem">
                    <input type="checkbox" name="location_tracking_enabled" value="1" <?= !empty($user['location_tracking_enabled']) ? 'checked' : '' ?>>
                    Suivi de position pour les minuteurs
                </label>
                <small style="color:var(--text-muted)">
                    Permet de savoir si quelqu'un est à la maison quand un minuteur se termine (pour
                    l'alerte différée). Position mise à jour périodiquement pendant que l'app est
                    ouverte, jamais conservée en historique. Désactivable ici à tout moment.
                </small>
            </div>
            <div class="form-group">
                <label>Nouveau mot de passe (laisser vide pour ne pas changer)</label>
                <input type="password" name="password" minlength="8" placeholder="••••••••" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label>Mot de passe actuel (requis uniquement pour changer le mot de passe)</label>
                <input type="password" name="current_password" placeholder="••••••••" autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
        </form>
        <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border)">
            <button type="button" class="btn btn-secondary btn-sm" onclick="confirmLogoutAllDevices()">🔌 Déconnecter tous les appareils</button>
            <p style="color:var(--text-muted);font-size:.78rem;margin-top:.4rem">
                Met fin à toutes vos connexions actives (navigateurs, PWA installées) — y compris celle-ci, vous devrez vous reconnecter.
            </p>
        </div>
    </div>

    <!-- Authentification à deux facteurs -->
    <div class="card settings-section">
        <h3>🔐 Double authentification</h3>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Ajoutez une deuxième étape à la connexion : un code généré par une application
            d'authentification (recommandé), ou par défaut un code envoyé par email si vous n'en
            utilisez pas.
        </p>

        <div id="tfa-status-none" style="display:<?= $twoFactorMethod === null ? 'block' : 'none' ?>">
            <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                <button type="button" class="btn btn-primary" onclick="startTfaTotpSetup()">📱 Utiliser une application</button>
                <button type="button" class="btn btn-secondary" onclick="enableTfaEmail()">✉️ Utiliser mon email</button>
            </div>
        </div>

        <div id="tfa-status-totp" style="display:<?= $twoFactorMethod === 'totp' ? 'block' : 'none' ?>">
            <p class="alert alert-success" style="margin-bottom:.75rem">Activée via une application d'authentification.</p>
            <button type="button" class="btn btn-danger btn-sm" onclick="openTfaDisableModal()">Désactiver la double authentification</button>
        </div>

        <div id="tfa-status-email" style="display:<?= $twoFactorMethod === 'email' ? 'block' : 'none' ?>">
            <p class="alert alert-success" style="margin-bottom:.75rem">Activée par code envoyé à votre email.</p>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                <button type="button" class="btn btn-secondary btn-sm" onclick="startTfaTotpSetup()">📱 Passer à une application</button>
                <button type="button" class="btn btn-danger btn-sm" onclick="openTfaDisableModal()">Désactiver</button>
            </div>
        </div>

        <!-- Enrôlement TOTP : clé affichée pour saisie manuelle dans l'application -->
        <div id="tfa-totp-setup" style="display:none;margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border)">
            <p style="font-size:.85rem">
                Scannez ce QR code avec votre application d'authentification (Google Authenticator, Authy,
                Ente Auth...), ou ajoutez le compte manuellement avec la clé affichée en dessous.
            </p>
            <div id="tfa-totp-qrcode" style="display:flex;justify-content:center;margin:.75rem 0"></div>
            <p style="font-family:monospace;font-size:1.05rem;letter-spacing:2px;background:var(--bg-alt);padding:.6rem;border-radius:8px;word-break:break-all;text-align:center" id="tfa-totp-secret"></p>
            <div class="form-group">
                <label>Code affiché par l'application</label>
                <input type="text" id="tfa-totp-code" inputmode="numeric" maxlength="6" placeholder="123456" style="letter-spacing:3px">
            </div>
            <div style="display:flex;gap:.5rem">
                <button type="button" class="btn btn-primary" onclick="confirmTfaTotpSetup()">Confirmer et activer</button>
                <button type="button" class="btn btn-secondary" onclick="cancelTfaTotpSetup()">Annuler</button>
            </div>
        </div>

        <p id="tfa-message" style="font-size:.8rem;margin-top:.5rem"></p>
    </div>

    <?php if (!empty($vaultwardenEnabled)): ?>
    <!-- Coffre-fort de mots de passe (Vaultwarden) -->
    <div class="card settings-section">
        <h3>🔐 Coffre-fort de mots de passe</h3>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Un coffre-fort chiffré pour vos mots de passe et codes de double authentification,
            hébergé séparément de FamilyBoard (comme Bitwarden) — votre mot de passe maître n'est
            jamais connu de FamilyBoard.
        </p>
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
    </div>
    <?php endif; ?>

    <?php if (!empty($digiposteConfigured)): ?>
    <!-- Import de documents (Digiposte) -->
    <div class="card settings-section">
        <h3>📥 Coffre Digiposte</h3>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Connectez votre coffre-fort Digiposte personnel pour importer vos documents dans le module
            Documents. Compte strictement personnel — jamais partagé avec le reste de la famille.
        </p>
        <?php if ($digiposteConnection): ?>
            <p class="alert alert-success" style="margin-bottom:.75rem">
                Coffre connecté<?= $digiposteConnection['last_synced_at'] ? ' — dernier import le ' . htmlspecialchars(substr($digiposteConnection['last_synced_at'], 0, 16)) : '' ?>.
                <?php if ($digiposteConnection['last_sync_error']): ?><br><small style="color:var(--danger)"><?= htmlspecialchars($digiposteConnection['last_sync_error']) ?></small><?php endif; ?>
            </p>
            <button type="button" class="btn btn-primary btn-sm" id="digiposte-sync-btn" onclick="digiposteSyncNow()">📥 Importer maintenant</button>
            <form method="POST" action="<?= BASE_URL ?>/digiposte/disconnect" style="display:inline"><?= \App\Core\Csrf::field() ?>
                <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Déconnecter votre coffre Digiposte ?')">Déconnecter</button>
            </form>
            <p style="font-size:.8rem;margin-top:.5rem" id="digiposte-sync-message"></p>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/digiposte/connect" class="btn btn-primary">🔗 Connecter mon coffre Digiposte</a>
        <?php endif; ?>
        <p style="color:var(--text-muted);font-size:.78rem;margin-top:.75rem">
            L'import se fait automatiquement toutes les quelques heures, ou à la demande avec le bouton ci-dessus.
        </p>
    </div>
    <?php endif; ?>

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

    </div>
    <!-- ═══ /Onglet : Mon compte ═══ -->

    <?php if (!$isCoparentSettings): ?>
    <!-- ═══ Onglet : Application ═══ -->
    <div class="settings-tab-panel" data-tab="app">

    <!-- Installer l'application (PWA) -->
    <div class="card settings-section" id="pwa-install-section">
        <h3>📲 Installer l'application</h3>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            FamilyBoard s'installe directement depuis votre navigateur, comme une vraie application
            (icône sur l'écran d'accueil, plein écran, notifications) — sans passer par un store.
        </p>
        <p id="pwa-install-status" class="alert alert-success" style="display:none">✅ Application installée sur cet appareil.</p>
        <button type="button" class="btn btn-primary" id="pwa-install-btn-settings" onclick="installPWA()" style="display:none">📲 Installer l'application</button>

        <div style="margin-top:1rem">
            <details>
                <summary style="cursor:pointer;font-weight:600;font-size:.9rem"> Instructions pour iPhone / iPad (Safari)</summary>
                <ol style="font-size:.85rem;color:var(--text-muted);margin:.6rem 0 0 1.1rem;padding:0">
                    <li>Ouvrez FamilyBoard dans <strong>Safari</strong> (obligatoire, pas Chrome sur iOS).</li>
                    <li>Appuyez sur le bouton <strong>Partager</strong> (carré avec une flèche vers le haut).</li>
                    <li>Choisissez <strong>« Sur l'écran d'accueil »</strong>, puis « Ajouter ».</li>
                    <li>Ouvrez FamilyBoard depuis cette nouvelle icône (pas depuis Safari) et activez les notifications ci-dessous.</li>
                </ol>
            </details>
            <details style="margin-top:.5rem">
                <summary style="cursor:pointer;font-weight:600;font-size:.9rem"> Instructions pour Android (Chrome)</summary>
                <ol style="font-size:.85rem;color:var(--text-muted);margin:.6rem 0 0 1.1rem;padding:0">
                    <li>Ouvrez FamilyBoard dans <strong>Chrome</strong>.</li>
                    <li>Appuyez sur le bouton <strong>« Installer l'application »</strong> ci-dessus, ou sur le menu ⋮ puis <strong>« Installer l'application »</strong>.</li>
                    <li>Ouvrez FamilyBoard depuis l'icône ajoutée et activez les notifications ci-dessous.</li>
                </ol>
            </details>
        </div>
    </div>

    <!-- Barre de navigation rapide (bas d'écran, mobile/PWA) -->
    <div class="card settings-section">
        <h3>📱 Barre rapide (mobile)</h3>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Sur mobile et dans l'application installée, une barre en bas d'écran donne un accès
            direct à l'accueil, jusqu'à 3 modules de votre choix, et le reste du menu (« Plus »).
            Choisissez vos 3 modules — ils resteront fixes tant que vous ne les changez pas
            vous-même.
        </p>
        <?php
        $_disabledModules = \App\Models\Family::getDisabledModules($family ?? []);
        $_availableQuick = array_values(array_filter(
            array_keys(\App\Models\Family::QUICK_NAV_ROUTES),
            fn($slug) => !in_array($slug, $_disabledModules, true)
        ));
        $_currentQuick = array_values(array_intersect(
            array_filter(explode(',', $user['quick_nav'] ?? '')),
            $_availableQuick
        ));
        if (!$_currentQuick) $_currentQuick = \App\Models\Family::defaultQuickNav($_availableQuick);
        ?>
        <form method="POST" action="<?= BASE_URL ?>/settings/quick-nav"><?= \App\Core\Csrf::field() ?>
            <div class="form-group" data-quick-nav-group data-max="3">
                <?php foreach ($_availableQuick as $_slug): $_meta = \App\Models\Family::MODULES[$_slug]; ?>
                <label class="radio-option">
                    <input type="checkbox" name="quick_nav[]" value="<?= htmlspecialchars($_slug) ?>"
                        <?= in_array($_slug, $_currentQuick, true) ? 'checked' : '' ?>>
                    <?= $_meta['icon'] ?> <?= htmlspecialchars($_meta['label']) ?>
                </label>
                <?php endforeach; ?>
            </div>
            <p style="font-size:.78rem;color:var(--text-muted);margin:.25rem 0 .75rem" data-quick-nav-hint>3 sélectionnés au maximum.</p>
            <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
        </form>
    </div>

    <!-- Push notifications -->
    <div class="card settings-section" id="push-notifications-section">
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

    </div>
    <!-- ═══ /Onglet : Application ═══ -->
    <?php endif; ?>

    <?php if (!$isCoparentSettings): ?>
    <!-- ═══ Onglet : Famille ═══ -->
    <div class="settings-tab-panel" data-tab="famille">

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
            <div class="form-row">
                <div class="form-group flex-1">
                    <label>🏠 Adresse postale du foyer</label>
                    <input type="text" name="sender_address" value="<?= htmlspecialchars($family['sender_address'] ?? '') ?>" placeholder="12 rue des Lilas">
                </div>
                <div class="form-group flex-1">
                    <label>Code postal et ville</label>
                    <input type="text" name="sender_postal_city" value="<?= htmlspecialchars($family['sender_postal_city'] ?? '') ?>" placeholder="75000 Paris">
                </div>
            </div>
            <small style="color:var(--text-muted);display:block;margin:-.5rem 0 1rem">Utilisée comme adresse d'expéditeur sur les courriers générés (module Courriers).</small>
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

        <?php if ($mailAliasAddress): ?>
        <div class="invite-section" style="margin-top:.75rem">
            <strong>Adresse e-mail famille :</strong>
            <code class="invite-code"><?= htmlspecialchars($mailAliasAddress) ?></code>
            <button onclick="copyCode('<?= htmlspecialchars($mailAliasAddress) ?>')" class="btn btn-secondary btn-sm">📋 Copier</button>
            <div style="color:var(--text-muted);font-size:.8rem;margin-top:.3rem">
                Tout e-mail envoyé à cette adresse est transmis à chaque membre de la famille.
            </div>
        </div>
        <?php endif; ?>

        <!-- Familles amies -->
        <div id="friends" style="margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid var(--border)">
            <h4 style="margin-bottom:.5rem">🤝 Familles amies</h4>
            <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:.75rem">
                Devenez amie avec une autre famille grâce à son code famille : une fois la demande acceptée,
                vous pourrez l'inviter directement à participer à vos événements de calendrier.
            </p>
            <div class="form-row" style="align-items:flex-end;margin-bottom:1rem">
                <div class="form-group flex-2" style="margin-bottom:0">
                    <label>Code famille à ajouter</label>
                    <input type="text" id="friend-code-input" placeholder="Ex. A1B2C3D4" style="text-transform:uppercase">
                </div>
                <button class="btn btn-primary" onclick="sendFriendRequest()" style="white-space:nowrap">Envoyer la demande</button>
            </div>
            <div id="friend-requests-incoming">
                <?php foreach ($friendFamiliesIncoming as $fr): ?>
                <div class="card" style="padding:.75rem 1rem;margin-bottom:.5rem;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
                    <span style="flex:1">Demande reçue de <strong><?= htmlspecialchars($fr['requester_family_name']) ?></strong></span>
                    <button class="btn btn-primary btn-sm" onclick="respondFriendRequest(<?= (int)$fr['id'] ?>, 'accept')">Accepter</button>
                    <button class="btn btn-secondary btn-sm" onclick="respondFriendRequest(<?= (int)$fr['id'] ?>, 'decline')">Refuser</button>
                </div>
                <?php endforeach; ?>
            </div>
            <div id="friend-requests-outgoing">
                <?php foreach ($friendFamiliesOutgoing as $fr): ?>
                <div class="card" style="padding:.75rem 1rem;margin-bottom:.5rem;color:var(--text-muted);font-size:.85rem">
                    Demande envoyée à <strong><?= htmlspecialchars($fr['target_family_name']) ?></strong> — en attente de réponse
                </div>
                <?php endforeach; ?>
            </div>
            <?php if (empty($friendFamiliesAccepted)): ?>
                <p class="empty-state" style="margin:0">Aucune famille amie pour l'instant.</p>
            <?php else: ?>
            <ul id="friend-families-list" style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.5rem">
                <?php foreach ($friendFamiliesAccepted as $ff): ?>
                <li class="card" style="padding:.6rem 1rem;display:flex;align-items:center;gap:.75rem">
                    <span style="flex:1"><?= htmlspecialchars($ff['family_name']) ?></span>
                    <button class="btn btn-secondary btn-sm" onclick="removeFriendFamily(<?= (int)$ff['id'] ?>, '<?= htmlspecialchars(addslashes($ff['family_name'])) ?>')">Retirer</button>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
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

    <!-- Domicile (pour les minuteurs) -->
    <div class="card settings-section">
        <h3>🏠 Domicile</h3>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Utilisé uniquement pour l'alerte différée des minuteurs : si un minuteur se termine et
            que personne n'est géolocalisé chez vous, une notification push est envoyée à la place
            de sonner dans le vide, et l'alarme se déclenche dès le retour de quelqu'un. Repose sur
            le suivi de position de chaque membre (réglable individuellement dans « Mon profil »).
        </p>
        <?php if (!empty($family['home_lat'])): ?>
            <p>✅ Domicile configuré (rayon : <?= (int)($family['home_radius_m'] ?? 150) ?> m).</p>
        <?php else: ?>
            <p style="color:var(--text-muted)">Non configuré — l'alerte différée est inactive, les minuteurs sonnent normalement.</p>
        <?php endif; ?>
        <button type="button" class="btn btn-primary btn-sm" onclick="setHomeLocation()">📍 Utiliser ma position actuelle</button>
        <?php if (!empty($family['home_lat'])): ?>
        <form method="POST" action="<?= BASE_URL ?>/settings/home-location/clear" style="display:inline"><?= \App\Core\Csrf::field() ?>
            <button type="submit" class="btn btn-secondary btn-sm">Effacer</button>
        </form>
        <?php endif; ?>
        <p id="home-location-status" style="font-size:.82rem;margin-top:.5rem"></p>
    </div>

    <!-- Mode sombre programmé (écran mural / kiosque) -->
    <div class="card settings-section">
        <h3>🌙 Mode sombre — écran mural / kiosque</h3>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Bascule automatiquement l'écran mural et le kiosque en sombre, pour ne pas éblouir la
            pièce la nuit. Sans effet sur l'application normale (chacun garde son propre réglage
            clair/sombre).
        </p>
        <?php $darkType = $family['dark_mode_type'] ?? 'off'; ?>
        <form method="POST" action="<?= BASE_URL ?>/settings/family">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="family_name" value="<?= htmlspecialchars($family['name']) ?>">
            <input type="hidden" name="timezone" value="<?= htmlspecialchars($family['timezone'] ?? 'Europe/Paris') ?>">
            <input type="hidden" name="weather_city" value="<?= htmlspecialchars($family['weather_city'] ?? '') ?>">
            <input type="hidden" name="school_zone" value="<?= htmlspecialchars($family['school_zone'] ?? '') ?>">
            <input type="hidden" name="caldav_sync_interval" value="<?= (int)($family['caldav_sync_interval'] ?? 0) ?>">
            <input type="hidden" name="sender_address" value="<?= htmlspecialchars($family['sender_address'] ?? '') ?>">
            <input type="hidden" name="sender_postal_city" value="<?= htmlspecialchars($family['sender_postal_city'] ?? '') ?>">
            <div class="form-group">
                <label><input type="radio" name="dark_mode_type" value="off" <?= $darkType === 'off' ? 'checked' : '' ?> onchange="toggleDarkModeFields()"> Désactivé</label><br>
                <label><input type="radio" name="dark_mode_type" value="fixed" <?= $darkType === 'fixed' ? 'checked' : '' ?> onchange="toggleDarkModeFields()"> Horaires fixes</label><br>
                <label>
                    <input type="radio" name="dark_mode_type" value="sunset" <?= $darkType === 'sunset' ? 'checked' : '' ?> onchange="toggleDarkModeFields()" <?= empty($family['home_lat']) ? 'disabled' : '' ?>>
                    Du coucher au lever du soleil
                    <?php if (empty($family['home_lat'])): ?><small style="color:var(--text-muted)">(configurez d'abord le Domicile ci-dessus)</small><?php endif; ?>
                </label>
            </div>
            <div class="form-row" id="dark-mode-fixed-fields" style="<?= $darkType === 'fixed' ? '' : 'display:none' ?>">
                <div class="form-group">
                    <label>Début</label>
                    <input type="time" name="dark_mode_start" value="<?= htmlspecialchars($family['dark_mode_start'] ?? '20:00') ?>">
                </div>
                <div class="form-group">
                    <label>Fin</label>
                    <input type="time" name="dark_mode_end" value="<?= htmlspecialchars($family['dark_mode_end'] ?? '07:00') ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm" style="margin-top:.5rem">Enregistrer</button>
        </form>
    </div>
    <script>
    function toggleDarkModeFields() {
        const isFixed = document.querySelector('input[name="dark_mode_type"]:checked')?.value === 'fixed';
        document.getElementById('dark-mode-fixed-fields').style.display = isFixed ? '' : 'none';
    }
    </script>

    <!-- Modules (admin only) -->
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
                    <?= \App\Core\Avatar::html($member['avatar'] ?? null, $member['color'], $member['name']) ?>
                    <div class="member-info">
                        <strong><?= htmlspecialchars($member['name']) ?></strong>
                        <small>
                            <?= htmlspecialchars($member['email']) ?> ·
                            <?php if (!empty($member['is_founder'])): ?>
                                👑 Admin fondateur
                            <?php elseif ($member['role'] === 'admin'): ?>
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
                    <?php $founderProtected = !empty($member['is_founder']) && empty($user['is_founder']); ?>
                    <?php if ($member['id'] !== $user['id'] && $user['role'] === 'admin'): ?>
                        <div style="display:flex;gap:.4rem">
                        <?php if ($member['role'] === 'member'): ?>
                            <form method="POST" action="<?= BASE_URL ?>/settings/member/<?= $member['id'] ?>/promote" onsubmit="return confirmSubmit(this,'Promouvoir <?= htmlspecialchars(addslashes($member['name'])) ?> administrateur ?')"><?= \App\Core\Csrf::field() ?>
                                <button type="submit" class="btn btn-secondary btn-sm">👑 Promouvoir admin</button>
                            </form>
                        <?php elseif ($member['role'] === 'admin' && !$founderProtected): ?>
                            <form method="POST" action="<?= BASE_URL ?>/settings/member/<?= $member['id'] ?>/demote" onsubmit="return confirmSubmit(this,'Rétrograder <?= htmlspecialchars(addslashes($member['name'])) ?> au rôle de membre ?')"><?= \App\Core\Csrf::field() ?>
                                <button type="submit" class="btn btn-secondary btn-sm">Rétrograder</button>
                            </form>
                        <?php endif; ?>
                        <?php if (!$founderProtected): ?>
                            <?php $removeConfirm = $member['role'] === 'coparent'
                                ? "Retirer l'accès de {$member['name']} à la garde partagée ? Son compte sera supprimé : il ne pourra plus consulter le planning, le journal parental ni les documents liés à la garde partagée."
                                : "Retirer {$member['name']} de la famille ?"; ?>
                            <form method="POST" action="<?= BASE_URL ?>/settings/member/<?= $member['id'] ?>/remove" onsubmit="return confirmSubmit(this,'<?= htmlspecialchars(addslashes($removeConfirm), ENT_QUOTES) ?>')"><?= \App\Core\Csrf::field() ?>
                                <button type="submit" class="btn btn-danger btn-sm"><?= $member['role'] === 'coparent' ? '🔒 Retirer l\'accès' : 'Retirer' ?></button>
                            </form>
                        <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Family admin: send notification -->
    <?php if ($user['role'] === 'admin'): ?>
    <div class="card settings-section">
        <h3>📣 Envoyer une notification</h3>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Envoyez une notification (push + dans l'application) aux membres de votre famille.
        </p>
        <form id="family-notify-form" onsubmit="sendFamilyNotification(event)">
            <div class="form-group">
                <label>Titre</label>
                <input type="text" id="notify-title" maxlength="150" required>
            </div>
            <div class="form-group">
                <label>Message</label>
                <textarea id="notify-message" rows="3" maxlength="2000" required></textarea>
            </div>
            <?php if (!empty($coparentsForNotify)): ?>
            <label style="display:flex;align-items:flex-start;gap:.55rem;margin-bottom:1rem;cursor:pointer;font-size:.85rem">
                <input type="checkbox" id="notify-include-coparent" style="width:16px;height:16px;margin-top:.15rem;flex:none">
                <span>
                    Inclure le co-parent (<?= htmlspecialchars(implode(', ', array_column($coparentsForNotify, 'name'))) ?>) —
                    dans ce cas, cet envoi sera journalisé de façon <strong>immuable</strong> dans le journal d'activité de la garde partagée.
                </span>
            </label>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">Envoyer</button>
        </form>
        <div id="family-notify-result" style="margin-top:.75rem"></div>
    </div>
    <?php endif; ?>

    </div>
    <!-- ═══ /Onglet : Famille ═══ -->
    <?php endif; ?>

    <?php if (!$isCoparentSettings && $user['role'] === 'admin'): ?>
    <!-- ═══ Onglet : Abonnement ═══ -->
    <div class="settings-tab-panel" data-tab="abonnement">
        <?php
        $subStatus = $subSubscription['status'] ?? 'none';
        $subStatusLabels = [
            'none'      => ['label' => 'Offre Gratuite', 'class' => ''],
            'trialing'  => ['label' => 'Essai gratuit en cours', 'class' => 'badge-success'],
            'active'    => ['label' => 'Abonné·e', 'class' => 'badge-success'],
            'past_due'  => ['label' => 'Paiement en échec', 'class' => 'badge-danger'],
            'canceled'  => ['label' => 'Abonnement résilié', 'class' => 'badge-danger'],
        ];
        $subStatusInfo = $subStatusLabels[$subStatus] ?? $subStatusLabels['none'];
        $subUpsell = trim($_GET['upsell'] ?? '');
        ?>
        <?php if ($subUpsell): ?>
        <div class="card settings-section" style="border-left:4px solid var(--primary)">
            <p style="margin:0">🔒 Le module que vous venez d'ouvrir fait partie de l'offre <strong>Premium</strong>. Abonnez-vous pour en profiter, ainsi que de tous les autres modules avancés.</p>
        </div>
        <?php endif; ?>
        <?php if (!$subBillingEnabled): ?>
        <div class="card settings-section">
            <p>La facturation n'est pas encore activée sur cette instance — tous les modules sont accessibles librement pour le moment.</p>
        </div>
        <?php else: ?>

        <div class="card settings-section">
            <h3>💳 Abonnement</h3>
            <p>
                <strong>Statut actuel : </strong>
                <span class="badge <?= $subStatusInfo['class'] ?>"><?= $subStatusInfo['label'] ?></span>
                <?php if ($subSubscription && $subSubscription['plan_name']): ?>
                    <span class="badge"><?= htmlspecialchars($subSubscription['plan_name']) ?> — <?= $subSubscription['member_limit'] ? $subSubscription['member_limit'] . ' membres max' : 'membres illimités' ?></span>
                <?php endif; ?>
            </p>
            <?php if ($subStatus === 'trialing' && !empty($subSubscription['trial_ends_at'])): ?>
                <p style="color:var(--text-muted);font-size:.85rem">Votre essai se termine le <?= (new DateTime($subSubscription['trial_ends_at']))->format('d/m/Y') ?>. Une carte enregistrée sera débitée à cette date, sauf résiliation avant.</p>
            <?php elseif ($subStatus === 'active' && !empty($subSubscription['current_period_end'])): ?>
                <p style="color:var(--text-muted);font-size:.85rem">Prochain renouvellement le <?= (new DateTime($subSubscription['current_period_end']))->format('d/m/Y') ?>.</p>
            <?php elseif (in_array($subStatus, ['past_due', 'canceled'], true)): ?>
                <p style="color:var(--danger);font-size:.85rem">
                    Les modules Premium sont actuellement bloqués pour toute la famille (paiement en échec ou abonnement résilié).
                    <?php if (!empty($subSubscription['data_purged_at'])): ?>
                        Les données de ces modules ont été définitivement supprimées le <?= (new DateTime($subSubscription['data_purged_at']))->format('d/m/Y') ?>, faute de réabonnement dans le délai imparti.
                    <?php elseif (!empty($subSubscription['grace_ends_at'])): ?>
                        Vos données de ces modules sont conservées jusqu'au <?= (new DateTime($subSubscription['grace_ends_at']))->format('d/m/Y') ?> — réabonnez-vous avant cette date pour les retrouver telles quelles. Passé ce délai, elles seront supprimées définitivement.
                    <?php endif; ?>
                </p>
            <?php endif; ?>
            <?php if ($subSubscription && !empty($subSubscription['stripe_customer_id'])): ?>
            <form method="POST" action="<?= BASE_URL ?>/api/subscription/portal">
                <button type="submit" class="btn btn-secondary btn-sm">Gérer mon abonnement (Stripe)</button>
            </form>
            <?php endif; ?>
        </div>

        <?php if (!$subStripeConfigured): ?>
        <div class="card settings-section"><p>La facturation est activée mais pas encore configurée par l'administrateur système. Revenez bientôt.</p></div>
        <?php else: ?>
        <div class="card settings-section">
            <h3>Paliers disponibles</h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin-top:.75rem">
                <?php foreach ($subPlans as $p): ?>
                    <?php
                    $subMonthly = $p['price_monthly_cents'] / 100;
                    $subYearly  = $p['price_yearly_cents'] / 100;
                    $subIsCurrent = $subSubscription && (int)($subSubscription['plan_id'] ?? 0) === (int)$p['id'] && in_array($subStatus, ['trialing', 'active'], true);
                    ?>
                    <div class="card" style="display:flex;flex-direction:column;gap:.6rem;<?= $subIsCurrent ? 'border:2px solid var(--primary)' : '' ?>">
                        <strong><?= htmlspecialchars($p['name']) ?></strong>
                        <span style="color:var(--text-muted);font-size:.85rem"><?= $p['member_limit'] ? "Jusqu'à {$p['member_limit']} membres" : 'Membres illimités' ?></span>
                        <div><strong style="font-size:1.3rem"><?= number_format($subMonthly, 2, ',', ' ') ?> €</strong> <span style="color:var(--text-muted);font-size:.8rem">/ mois</span></div>
                        <span style="color:var(--text-muted);font-size:.8rem"><?= number_format($subYearly, 2, ',', ' ') ?> € / an<?= $subAnnualDiscount > 0 ? " (-{$subAnnualDiscount}%)" : '' ?></span>
                        <?php if ($subMemberCount > 0 && $p['member_limit'] && $subMemberCount > $p['member_limit']): ?>
                            <span style="color:var(--danger);font-size:.75rem">Votre famille compte déjà <?= $subMemberCount ?> membres.</span>
                        <?php endif; ?>
                        <form method="POST" action="<?= BASE_URL ?>/api/subscription/checkout" style="margin-top:auto">
                            <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
                            <input type="hidden" name="interval" value="monthly">
                            <button type="submit" class="btn <?= $subIsCurrent ? 'btn-secondary' : 'btn-primary' ?> btn-sm" style="width:100%"><?= $subIsCurrent ? 'Palier actuel' : 'S\'abonner (mensuel)' ?></button>
                        </form>
                        <form method="POST" action="<?= BASE_URL ?>/api/subscription/checkout">
                            <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
                            <input type="hidden" name="interval" value="yearly">
                            <button type="submit" class="btn btn-secondary btn-sm" style="width:100%">S'abonner (annuel)</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (!$subHasUsedTrial): ?>
            <p style="color:var(--text-muted);font-size:.85rem;margin-top:1rem">✨ Essai gratuit de <?= $subTrialDays ?> jours sur le premier abonnement, résiliable à tout moment.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <!-- ═══ /Onglet : Abonnement ═══ -->
    <?php endif; ?>

    <?php if (!$isCoparentSettings): ?>
    <!-- ═══ Onglet : Accès partagés ═══ -->
    <div class="settings-tab-panel" data-tab="acces">

    <!-- Sitter access -->
    <?php $_disabledModsForSitter = \App\Models\Family::getDisabledModules($family ?? []); ?>
    <?php if (!in_array('sitter', $_disabledModsForSitter)): ?>
    <div class="card settings-section">
        <h3>👶 Accès baby-sitter</h3>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Générez un lien temporaire donnant un accès limité en lecture seule (contacts des parents,
            numéros de secours, consignes, suivi bébé) — sans connexion, sans accès au reste des
            données familiales.
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
        <div class="form-group">
            <label>Consignes pour la baby-sitter (optionnel)</label>
            <textarea id="sitter-instructions" rows="3" placeholder="Ex : coucher à 20h, pas d'écran après le dîner…"></textarea>
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
                    <button class="btn btn-secondary btn-sm" onclick="showKioskLinkModal('<?= htmlspecialchars($link['token'], ENT_QUOTES) ?>', '<?= htmlspecialchars($link['short_code'], ENT_QUOTES) ?>', '<?= htmlspecialchars($link['label'], ENT_QUOTES) ?>')">🔗 Voir le lien</button>
                    <button class="btn btn-danger btn-sm" onclick="revokeKioskLink(<?= $link['id'] ?>)">Révoquer</button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Kiosk link / QR modal -->
    <div class="modal-overlay" id="kiosk-link-modal" style="display:none">
        <div class="modal">
            <div class="modal-header">
                <h3 id="kiosk-link-modal-title">Écran mural</h3>
                <button onclick="closeModal('kiosk-link-modal')">✕</button>
            </div>
            <div class="modal-body" style="text-align:center">
                <div id="kiosk-qr-container" style="display:inline-block;margin-bottom:1rem"></div>
                <p style="word-break:break-all;font-size:.8rem" id="kiosk-qr-link"></p>
                <div style="display:flex;gap:.5rem;justify-content:center;margin-bottom:1.25rem">
                    <button type="button" class="btn btn-secondary btn-sm" id="kiosk-qr-copy-btn">📋 Copier le lien</button>
                    <a href="#" target="_blank" class="btn btn-secondary btn-sm" id="kiosk-qr-open-link">Ouvrir</a>
                </div>
                <hr style="margin:1rem 0;border-color:var(--border-color)">
                <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:.5rem">
                    Ou, sur une TV connectée : ouvrez <strong><?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? '') ?><?= BASE_URL ?>/tv</strong>
                    et saisissez ce code :
                </p>
                <div id="kiosk-short-code" style="font-size:2rem;letter-spacing:.4rem;font-family:monospace;font-weight:700"></div>
            </div>
        </div>
    </div>

    <!-- Minuteurs de l'écran mural / kiosque -->
    <?php if ($user['role'] === 'admin'): ?>
    <div class="card settings-section">
        <h3>⏰ Minuteurs de l'écran mural</h3>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Créez des minuteurs prédéfinis (ex. « Machine à laver » — 40 min) que n'importe qui peut
            démarrer d'un bouton depuis l'écran mural ou le kiosque. Une alarme sonne à échéance,
            avec un bouton pour l'arrêter.
        </p>
        <form method="POST" action="<?= BASE_URL ?>/settings/timers" class="admin-inline-form"><?= \App\Core\Csrf::field() ?>
            <input type="text" name="label" placeholder="Ex : Machine à laver" required style="width:220px">
            <input type="number" name="duration_minutes" placeholder="Minutes" min="1" max="1440" required style="width:100px">
            <label style="display:flex;align-items:center;gap:.35rem;font-size:.85rem">
                <input type="checkbox" name="show_on_wall" value="1" checked> Afficher sur l'écran mural
            </label>
            <button type="submit" class="btn btn-primary btn-sm">Ajouter</button>
        </form>

        <div style="margin-top:1.25rem">
            <?php if (empty($familyTimers)): ?>
                <p style="color:var(--text-muted);font-size:.85rem">Aucun minuteur créé.</p>
            <?php endif; ?>
            <?php foreach ($familyTimers as $t): ?>
            <div class="member-item">
                <div class="member-info">
                    <strong><?= htmlspecialchars($t['label']) ?></strong>
                    <small><?= (int)$t['duration_minutes'] ?> min · <?= $t['show_on_wall'] ? '✅ Affiché sur l\'écran mural' : '➖ Non affiché' ?></small>
                </div>
                <form method="POST" action="<?= BASE_URL ?>/settings/timers/<?= (int)$t['id'] ?>/delete"><?= \App\Core\Csrf::field() ?>
                    <button type="submit" class="btn btn-danger btn-sm">Supprimer</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    </div>
    <!-- ═══ /Onglet : Accès partagés ═══ -->
    <?php endif; ?>

    <?php if (!empty($emailLogs)): ?>
    <!-- ═══ Onglet : Historique ═══ -->
    <div class="settings-tab-panel" data-tab="historique">

    <!-- Email logs -->
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

    </div>
    <!-- ═══ /Onglet : Historique ═══ -->
    <?php endif; ?>

</div>

<!-- Disable 2FA modal -->
<div class="modal-overlay" id="tfa-disable-modal" style="display:none">
    <div class="modal">
        <div class="modal-header">
            <h3>Désactiver la double authentification</h3>
            <button onclick="closeModal('tfa-disable-modal')">✕</button>
        </div>
        <div class="modal-body">
            <p>Confirmez votre mot de passe pour désactiver la double authentification.</p>
            <div class="form-group">
                <label>Mot de passe</label>
                <input type="password" id="tfa-disable-password" autocomplete="current-password">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('tfa-disable-modal')">Annuler</button>
            <button class="btn btn-danger" onclick="confirmTfaDisable()">Désactiver</button>
        </div>
    </div>
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

// ── Onglets ──────────────────────────────────────────────────────
function switchSettingsTab(tab) {
    document.querySelectorAll('.settings-tab-panel').forEach(p => p.classList.toggle('active', p.dataset.tab === tab));
    document.querySelectorAll('.settings-tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
    if (history.replaceState) history.replaceState(null, '', '#tab-' + tab);
}
(function () {
    // Un lien externe (ex. le bandeau d'installation PWA) peut cibler directement un élément
    // à l'intérieur d'un onglet (#pwa-install-section) : ouvrir l'onglet correspondant avant
    // de faire défiler jusqu'à l'ancre, sinon l'élément reste caché par display:none.
    const hash = location.hash.replace('#', '');
    let targetTab = 'compte';
    if (hash.startsWith('tab-')) {
        targetTab = hash.slice(4);
    } else if (hash) {
        const el = document.getElementById(hash);
        const panel = el && el.closest('.settings-tab-panel');
        if (panel) targetTab = panel.dataset.tab;
    }
    switchSettingsTab(targetTab);
    if (hash && !hash.startsWith('tab-')) {
        const el = document.getElementById(hash);
        if (el) setTimeout(() => el.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
    }
})();

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

// ── Familles amies ────────────────────────────────────────────
async function sendFriendRequest() {
    const input = document.getElementById('friend-code-input');
    const code = input.value.trim();
    if (!code) { Dialog.toast('Entrez un code famille.', 'error'); return; }
    const r = await apiFetch(BASE_URL + '/api/friends/request', { method: 'POST', body: JSON.stringify({ code }) });
    if (r.success) {
        Dialog.toast('Demande envoyée !');
        input.value = '';
        setTimeout(() => location.reload(), 800);
    } else {
        Dialog.toast(r.error || 'Erreur lors de l\'envoi.', 'error');
    }
}
async function respondFriendRequest(id, decision) {
    const r = await apiFetch(BASE_URL + '/api/friends/' + id + '/' + decision, { method: 'POST' });
    if (r.success) location.reload();
    else Dialog.toast('Erreur.', 'error');
}
async function removeFriendFamily(id, name) {
    if (!confirm('Retirer ' + name + ' de vos familles amies ? Les événements déjà partagés entre vous disparaîtront.')) return;
    const r = await apiFetch(BASE_URL + '/api/friends/' + id + '/remove', { method: 'POST' });
    if (r.success) location.reload();
    else Dialog.toast('Erreur.', 'error');
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
