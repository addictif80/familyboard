        <h2>Notification système</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Envoie une notification (push + dans l'application) à <strong>tous les utilisateurs</strong> ou à une sélection
            précise, toutes familles confondues. Le clic sur la notification (depuis la cloche du site ou le push du
            navigateur) ouvre une page dédiée affichant le titre, la date et le contenu détaillé.
        </p>
        <form method="POST" action="<?= BASE_URL ?>/admin/notifications/send" class="card" style="padding:1.25rem;max-width:640px" id="system-notify-form" onsubmit="return prepareSystemNotifyForm()"><?= \App\Core\Csrf::field() ?>
            <div class="form-group">
                <label>Destinataires</label>
                <label class="radio-option">
                    <input type="radio" name="recipients" value="all" checked onchange="toggleSystemNotifyRecipients()">
                    <span>Tous les utilisateurs</span>
                </label>
                <label class="radio-option">
                    <input type="radio" name="recipients" value="specific" onchange="toggleSystemNotifyRecipients()">
                    <span>Utilisateurs spécifiques</span>
                </label>
            </div>
            <div class="form-group" id="system-notify-recipient-picker" style="display:none">
                <label>Rechercher (nom, email ou famille)</label>
                <input type="text" id="system-notify-user-search" placeholder="Tapez pour filtrer…" oninput="filterSystemNotifyUsers(this.value)">
                <div id="system-notify-user-list" style="max-height:220px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;padding:.5rem;margin-top:.5rem">
                    <?php foreach ($users as $u): ?>
                    <label class="radio-option system-notify-user-row" data-search="<?= htmlspecialchars(mb_strtolower($u['name'] . ' ' . $u['email'] . ' ' . $u['family_name'])) ?>">
                        <input type="checkbox" name="user_ids[]" value="<?= (int)$u['id'] ?>">
                        <span><?= htmlspecialchars($u['name']) ?> <small style="color:var(--text-muted)"><?= htmlspecialchars($u['email']) ?> · <?= htmlspecialchars($u['family_name']) ?></small></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <small id="system-notify-user-count" style="color:var(--text-muted)">0 destinataire(s) sélectionné(s)</small>
            </div>
            <div class="form-group">
                <label>Titre</label>
                <input type="text" name="title" maxlength="150" required>
            </div>
            <div class="form-group">
                <label>Texte court <span style="color:var(--text-muted);font-size:.8rem">(aperçu affiché dans la cloche de notifications et le push)</span></label>
                <input type="text" name="short_text" maxlength="300" required>
            </div>
            <div class="form-group">
                <label>Contenu détaillé</label>
                <div class="post-quill-wrap">
                    <div id="system-notify-quill-editor"></div>
                </div>
                <textarea name="content" id="system-notify-content" style="display:none"></textarea>
            </div>
            <button type="submit" class="btn btn-primary" id="system-notify-submit-btn">Envoyer à tous les utilisateurs</button>
        </form>
        <script>
        (function () {
            var editor;
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof Quill === 'undefined') return;
                editor = new Quill('#system-notify-quill-editor', {
                    theme: 'snow',
                    placeholder: 'Contenu détaillé de la notification…',
                    modules: { toolbar: [['bold', 'italic', 'underline'], [{ list: 'ordered' }, { list: 'bullet' }], ['link'], ['clean']] },
                });
                document.querySelectorAll('#system-notify-user-list input[type=checkbox]').forEach(function (cb) {
                    cb.addEventListener('change', updateSystemNotifyUserCount);
                });
            });

            window.toggleSystemNotifyRecipients = function () {
                var specific = document.querySelector('input[name="recipients"]:checked').value === 'specific';
                document.getElementById('system-notify-recipient-picker').style.display = specific ? 'block' : 'none';
                document.getElementById('system-notify-submit-btn').textContent = specific
                    ? 'Envoyer aux utilisateurs sélectionnés' : 'Envoyer à tous les utilisateurs';
            };

            window.filterSystemNotifyUsers = function (query) {
                query = query.trim().toLowerCase();
                document.querySelectorAll('.system-notify-user-row').forEach(function (row) {
                    row.style.display = row.dataset.search.includes(query) ? 'flex' : 'none';
                });
            };

            window.updateSystemNotifyUserCount = function () {
                var n = document.querySelectorAll('#system-notify-user-list input[type=checkbox]:checked').length;
                document.getElementById('system-notify-user-count').textContent = n + ' destinataire(s) sélectionné(s)';
            };

            window.prepareSystemNotifyForm = function () {
                if (editor) document.getElementById('system-notify-content').value = editor.root.innerHTML;
                if (!editor || editor.getText().trim() === '') {
                    alert('Le contenu détaillé est requis.');
                    return false;
                }
                var specific = document.querySelector('input[name="recipients"]:checked').value === 'specific';
                if (specific) {
                    var n = document.querySelectorAll('#system-notify-user-list input[type=checkbox]:checked').length;
                    if (n === 0) {
                        alert('Sélectionnez au moins un destinataire.');
                        return false;
                    }
                    return confirm('Envoyer cette notification à ' + n + ' utilisateur(s) sélectionné(s) ?');
                }
                return confirm('Envoyer cette notification à tous les utilisateurs de toutes les familles ?');
            };
        })();
        </script>

        <h2 style="margin-top:2rem">Veille informationnelle — Météo-France Vigilance</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Le bandeau d'alertes (canicule, inondation, alerte climatique, catastrophe industrielle…) repose sur la
            catégorie « familyboard-alert » du blog <a href="https://leblogateur.fr" target="_blank" rel="noopener">leblogateur.fr</a>
            (un article dont le titre commence par <code>[categorie]</code>, ex. <code>[canicule]</code> ou
            <code>[inondation:Bordeaux]</code>, devient une alerte). En complément, renseignez ici une
            clé API Vigilance de Météo-France pour des alertes canicule/météo officielles, précises par
            département. Clé gratuite à créer sur
            <a href="https://portail-api.meteofrance.fr/" target="_blank" rel="noopener">portail-api.meteofrance.fr</a>
            (application « Vigilance », authentification « Clé API »).
        </p>
        <form method="POST" action="<?= BASE_URL ?>/admin/meteofrance-key" class="card" style="padding:1.25rem;max-width:640px"><?= \App\Core\Csrf::field() ?>
            <div class="form-group">
                <label>Clé API Vigilance Météo-France</label>
                <?php if ($meteoFranceApiKey): ?>
                    <input type="text" value="•••••••••••••• <?= htmlspecialchars(substr($meteoFranceApiKey, -4)) ?>" disabled>
                    <input type="hidden" name="keep_existing" value="1">
                    <details style="margin-top:.4rem">
                        <summary style="cursor:pointer;font-size:.8rem;color:var(--text-muted)">Changer ou désactiver la clé</summary>
                        <input type="text" name="api_key" placeholder="Nouvelle clé (laisser vide pour désactiver)" autocomplete="off" style="margin-top:.5rem">
                    </details>
                <?php else: ?>
                    <input type="text" name="api_key" placeholder="Laisser vide pour désactiver ce complément" autocomplete="off">
                <?php endif; ?>
            </div>
            <div style="display:flex;gap:.5rem;align-items:center">
                <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                <?php if ($meteoFranceApiKey): ?>
                <button type="button" class="btn btn-secondary btn-sm" onclick="testMeteoFranceKey()">🔌 Tester la clé</button>
                <?php endif; ?>
            </div>
            <div id="meteofrance-test-result" style="margin-top:.75rem"></div>
        </form>

        <h2 style="margin-top:2rem">Coffre-fort de mots de passe (Vaultwarden)</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Propose aux membres de famille (jamais aux co-parents) de créer un coffre-fort de mots de passe/2FA
            sur votre instance Vaultwarden auto-hébergée. FamilyBoard se contente de déclencher l'invitation par
            e-mail depuis le panneau admin de Vaultwarden — il ne connaît jamais le mot de passe maître du
            coffre, choisi par chaque membre directement sur Vaultwarden. Nécessite une instance configurée en
            <code>SIGNUPS_ALLOWED=false</code> / <code>INVITATIONS_ALLOWED=true</code>.
        </p>
        <form method="POST" action="<?= BASE_URL ?>/admin/vaultwarden" class="card" style="padding:1.25rem;max-width:640px"><?= \App\Core\Csrf::field() ?>
            <div class="form-group">
                <label>URL de l'instance Vaultwarden</label>
                <input type="url" name="url" value="<?= htmlspecialchars($vaultwardenSettings['url'] ?? '') ?>" placeholder="https://pwd.votredomaine.fr">
            </div>
            <div class="form-group">
                <label>Jeton d'administration (ADMIN_TOKEN)</label>
                <?php if (!empty($vaultwardenSettings['token'])): ?>
                    <input type="text" value="•••••••••••••• <?= htmlspecialchars(substr($vaultwardenSettings['token'], -4)) ?>" disabled>
                    <input type="hidden" name="keep_existing" value="1">
                    <details style="margin-top:.4rem">
                        <summary style="cursor:pointer;font-size:.8rem;color:var(--text-muted)">Changer ou désactiver le jeton</summary>
                        <input type="text" name="admin_token" placeholder="Nouveau jeton (laisser vide pour désactiver)" autocomplete="off" style="margin-top:.5rem">
                    </details>
                <?php else: ?>
                    <input type="text" name="admin_token" placeholder="Laisser vide pour désactiver cette fonctionnalité" autocomplete="off">
                <?php endif; ?>
            </div>
            <div style="display:flex;gap:.5rem;align-items:center">
                <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                <?php if (!empty($vaultwardenSettings['token'])): ?>
                <button type="button" class="btn btn-secondary btn-sm" onclick="testVaultwarden()">🔌 Tester la connexion</button>
                <?php endif; ?>
            </div>
            <div id="vaultwarden-test-result" style="margin-top:.75rem"></div>
        </form>

        <h2 style="margin-top:2rem">Assistant IA</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            L'assistant peut créer des tâches, des articles de liste de courses et des événements
            calendrier à la demande — jamais rien d'autre (pas de suppression, pas d'accès aux
            modules sensibles). N'apparaît dans une famille que si le module « Assistant IA » y est
            activé (réglages de la famille).
        </p>

        <form method="POST" action="<?= BASE_URL ?>/admin/ai-provider" class="card" style="padding:1.25rem;max-width:640px;margin-bottom:1rem"><?= \App\Core\Csrf::field() ?>
            <div class="form-group">
                <label>Fournisseur actif</label>
                <label class="radio-option">
                    <input type="radio" name="provider" value="ollama" <?= $aiProvider === 'ollama' ? 'checked' : '' ?>>
                    <span>Ollama auto-hébergé — aucune donnée famille envoyée à un tiers</span>
                </label>
                <label class="radio-option">
                    <input type="radio" name="provider" value="1min" <?= $aiProvider === '1min' ? 'checked' : '' ?>>
                    <span>1min.ai — service externe compatible OpenAI, sans serveur à héberger</span>
                </label>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Enregistrer le fournisseur</button>
        </form>

        <h3>Ollama (auto-hébergé)</h3>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Branche l'assistant sur votre propre serveur Ollama (<code>https://ollama.com</code>) :
            aucune donnée famille n'est envoyée à un service tiers.
        </p>
        <form method="POST" action="<?= BASE_URL ?>/admin/ollama" class="card" style="padding:1.25rem;max-width:640px;margin-bottom:1.5rem"><?= \App\Core\Csrf::field() ?>
            <div class="form-group">
                <label>URL du serveur Ollama</label>
                <input type="url" name="url" value="<?= htmlspecialchars($ollamaSettings['url'] ?? '') ?>" placeholder="http://localhost:11434">
            </div>
            <div class="form-group">
                <label>Modèle</label>
                <input type="text" name="model" value="<?= htmlspecialchars($ollamaSettings['model'] ?? '') ?>" placeholder="llama3.1">
                <small style="color:var(--text-muted)">Doit être un modèle installé sur le serveur (<code>ollama pull &lt;modèle&gt;</code>) supportant l'appel d'outils (llama3.1, qwen2.5, mistral-nemo…).</small>
            </div>
            <div style="display:flex;gap:.5rem;align-items:center">
                <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                <?php if ($ollamaSettings): ?>
                <button type="button" class="btn btn-secondary btn-sm" onclick="testOllama()">🔌 Tester la connexion</button>
                <?php endif; ?>
            </div>
            <div id="ollama-test-result" style="margin-top:.75rem"></div>
        </form>

        <h3>1min.ai (service externe)</h3>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Clé API de votre compte <a href="https://1min.ai" target="_blank" rel="noopener">1min.ai</a>.
            Contrairement à Ollama, les messages de l'assistant sont envoyés à ce service tiers.
        </p>
        <form method="POST" action="<?= BASE_URL ?>/admin/onemin" class="card" style="padding:1.25rem;max-width:640px"><?= \App\Core\Csrf::field() ?>
            <div class="form-group">
                <label>Clé API 1min.ai</label>
                <input type="password" name="api_key" value="<?= htmlspecialchars($oneMinSettings['api_key'] ?? '') ?>" placeholder="sk-...">
            </div>
            <div class="form-group">
                <label>Modèle</label>
                <input type="text" name="model" value="<?= htmlspecialchars($oneMinSettings['model'] ?? '') ?>" placeholder="<?= \App\Models\OneMinAiSettings::DEFAULT_MODEL ?>">
                <small style="color:var(--text-muted)">Un modèle supportant l'appel d'outils (function calling), ex. gpt-4o-mini, gpt-4o.</small>
            </div>
            <div style="display:flex;gap:.5rem;align-items:center">
                <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                <?php if ($oneMinSettings): ?>
                <button type="button" class="btn btn-secondary btn-sm" onclick="testOneMinAi()">🔌 Tester la connexion</button>
                <?php endif; ?>
            </div>
            <div id="onemin-test-result" style="margin-top:.75rem"></div>
        </form>

        <h2 style="margin-top:2rem">Correction de bugs par l'IA (GitHub)</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Permet, depuis un ticket d'erreur technique, de demander à l'IA un correctif pour le
            fichier en cause. Rien n'est jamais appliqué automatiquement : un correctif validé est
            publié comme une <strong>Pull Request</strong> sur
            <code><?= htmlspecialchars(GITHUB_FIX_REPO_OWNER . '/' . GITHUB_FIX_REPO_NAME) ?></code>
            (branche <code><?= htmlspecialchars(GITHUB_FIX_BASE_BRANCH) ?></code>) — jamais un push direct.
            Fusionner la Pull Request reste une action volontaire, sur GitHub.
        </p>
        <form method="POST" action="<?= BASE_URL ?>/admin/github" class="card" style="padding:1.25rem;max-width:640px"><?= \App\Core\Csrf::field() ?>
            <div class="form-group">
                <label>Jeton d'accès personnel GitHub (portée <code>repo</code>)</label>
                <input type="password" name="github_pat" placeholder="<?= $githubConfigured ? '••••••••••••••••' : 'ghp_...' ?>" autocomplete="off">
                <small style="color:var(--text-muted)">Laisser vide pour ne pas changer<?= $githubConfigured ? ' (un jeton est déjà enregistré)' : '' ?>.</small>
            </div>
            <div style="display:flex;gap:.5rem;align-items:center">
                <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                <?php if ($githubConfigured): ?>
                <button type="button" class="btn btn-secondary btn-sm" onclick="testGithub()">🔌 Tester la connexion</button>
                <?php endif; ?>
            </div>
            <div id="github-test-result" style="margin-top:.75rem"></div>
        </form>

        <h2 style="margin-top:2rem">Alias e-mail famille (Mailcow)</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Crée automatiquement, pour chaque famille, un alias <code>&lt;nom-de-famille&gt;@<?= htmlspecialchars($mailcowSettings['domain'] ?? 'votre-domaine.fr') ?></code>
            redirigeant vers les adresses e-mail personnelles de ses membres (co-parents exclus),
            via l'API d'administration de votre instance Mailcow auto-hébergée. Synchronisé
            automatiquement à chaque arrivée/départ de membre. Changer le domaine ci-dessous
            relance la création des alias sur le nouveau domaine et supprime les anciens sur
            l'ancien — utile le jour où vous passerez de <code>board.abhd.fr</code> à un domaine
            dédié comme <code>familyboard.fr</code>.
        </p>
        <form method="POST" action="<?= BASE_URL ?>/admin/mailcow" class="card" style="padding:1.25rem;max-width:640px"><?= \App\Core\Csrf::field() ?>
            <div class="form-group">
                <label>URL de l'instance Mailcow</label>
                <input type="url" name="url" value="<?= htmlspecialchars($mailcowSettings['url'] ?? '') ?>" placeholder="https://mail.abhd.fr">
            </div>
            <div class="form-group">
                <label>Domaine des alias</label>
                <input type="text" name="domain" value="<?= htmlspecialchars($mailcowSettings['domain'] ?? '') ?>" placeholder="board.abhd.fr">
            </div>
            <div class="form-group">
                <label>Clé API</label>
                <?php if (!empty($mailcowSettings['api_key'])): ?>
                    <input type="text" value="•••••••••••••• <?= htmlspecialchars(substr($mailcowSettings['api_key'], -4)) ?>" disabled>
                    <input type="hidden" name="keep_existing" value="1">
                    <details style="margin-top:.4rem">
                        <summary style="cursor:pointer;font-size:.8rem;color:var(--text-muted)">Changer ou désactiver la clé</summary>
                        <input type="text" name="api_key" placeholder="Nouvelle clé (laisser vide pour désactiver)" autocomplete="off" style="margin-top:.5rem">
                    </details>
                <?php else: ?>
                    <input type="text" name="api_key" placeholder="Laisser vide pour désactiver cette fonctionnalité" autocomplete="off">
                <?php endif; ?>
            </div>
            <div style="display:flex;gap:.5rem;align-items:center">
                <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                <?php if (!empty($mailcowSettings['api_key'])): ?>
                <button type="button" class="btn btn-secondary btn-sm" onclick="testMailcow()">🔌 Tester la connexion</button>
                <?php endif; ?>
            </div>
            <div id="mailcow-test-result" style="margin-top:.75rem"></div>
        </form>

        <h2 style="margin-top:2rem">Sécurité — Double authentification</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Si activé, tout utilisateur sans double authentification voit un message l'invitant à
            l'activer (application ou email, à son choix) pendant le délai de grâce ci-dessous ;
            passé ce délai, l'accès à l'application est bloqué jusqu'à activation.
        </p>
        <form method="POST" action="<?= BASE_URL ?>/admin/2fa-policy" class="card" style="padding:1.25rem;max-width:640px"><?= \App\Core\Csrf::field() ?>
            <div class="form-group">
                <label><input type="checkbox" name="require_2fa_all" value="1" <?= $require2faAll ? 'checked' : '' ?>> Exiger la double authentification pour tous les utilisateurs</label>
            </div>
            <div class="form-group">
                <label>Délai de grâce (jours) avant blocage</label>
                <input type="number" name="require_2fa_grace_days" min="0" max="90" value="<?= (int)$require2faGraceDays ?>" style="max-width:120px">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
        </form>

