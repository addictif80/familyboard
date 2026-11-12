        <div class="settings-tabs">
            <button type="button" class="settings-tab-btn active" data-tab="storage-reglages" onclick="switchAdminSubTab('storage-reglages')">⚙️ Réglages</button>
            <button type="button" class="settings-tab-btn" data-tab="storage-produits" onclick="switchAdminSubTab('storage-produits')">📦 Paliers</button>
            <button type="button" class="settings-tab-btn" data-tab="storage-consommation" onclick="switchAdminSubTab('storage-consommation')">📊 Consommation</button>
            <button type="button" class="settings-tab-btn" data-tab="storage-rattrapage" onclick="switchAdminSubTab('storage-rattrapage')">🔄 Rattrapage fichiers</button>
            <button type="button" class="settings-tab-btn" data-tab="storage-nextcloud-migration" onclick="switchAdminSubTab('storage-nextcloud-migration')">☁️ Migration Nextcloud</button>
            <button type="button" class="settings-tab-btn" data-tab="storage-infra" onclick="switchAdminSubTab('storage-infra')">🖥️ LDAP / Synology</button>
        </div>
        <script>
        function switchAdminSubTab(tab) {
            document.querySelectorAll('[data-admin-subtab-panel]').forEach(p => p.classList.toggle('active', p.dataset.adminSubtabPanel === tab));
            document.querySelectorAll('.settings-tab-btn[data-tab^="storage-"]').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
        }
        <?php if ($sub = ($_GET['sub'] ?? '')): ?>
        // Lien direct depuis le menu latéral ("Stockage & fichiers") vers un sous-onglet précis —
        // prime sur tout appel switchAdminSubTab(...) de rapport flash plus bas dans cette page,
        // car exécuté avant eux dans l'ordre du document.
        switchAdminSubTab(<?= json_encode($sub) ?>);
        <?php endif; ?>
        </script>

        <div class="settings-tab-panel active" data-admin-subtab-panel="storage-reglages">
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Tant que la facturation du stockage est désactivée, aucun quota n'est restreint —
            activer ce réglage ne verrouille jamais rétroactivement un membre déjà en place sans
            action explicite ici. L'espace perso payant est séparé de l'espace familial partagé
            ci-dessous et vit sur le compte Stripe du payeur, jamais sur la famille.
        </p>
        <form method="POST" action="<?= BASE_URL ?>/admin/storage/settings" class="card" style="padding:1.25rem;max-width:640px"><?= \App\Core\Csrf::field() ?>
            <div class="form-group">
                <label><input type="checkbox" name="storage_billing_enabled" value="1" <?= $storageBillingEnabled ? 'checked' : '' ?>> Activer la facturation du stockage perso</label>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Quota gratuit par membre (Go)</label>
                    <input type="number" name="storage_free_quota_gb" min="0" value="<?= $storageFreeQuotaGb ?>">
                </div>
                <div class="form-group">
                    <label>Quota familial partagé (Go)</label>
                    <input type="number" name="storage_family_quota_gb" min="0" value="<?= $storageFamilyQuotaGb ?>">
                    <label style="font-size:.78rem;display:flex;align-items:center;gap:.3rem;margin-top:.3rem">
                        <input type="checkbox" name="storage_family_quota_unlimited" value="1" <?= $storageFamilyQuotaUnlimited ? 'checked' : '' ?>> Illimité (ignore la valeur ci-dessus)
                    </label>
                </div>
                <div class="form-group">
                    <label>Plafond d'add-ons par membre (Go)</label>
                    <input type="number" name="storage_member_addon_cap_gb" min="0" value="<?= $storageMemberAddonCapGb ?>">
                </div>
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="storage_nextcloud_files_enabled" value="1" <?= $storageNextcloudFilesEnabled ? 'checked' : '' ?>> Stocker les documents des modules sur Nextcloud (espace familial partagé, Group Folders) au lieu du disque FamilyBoard</label>
                <small style="color:var(--text-muted)">Chaque famille obtient son propre dossier Nextcloud partagé en lecture/écriture à tous ses membres, avec un quota natif indépendant (le réglage « Quota familial partagé » ci-dessus). Nécessite l'app <strong>Group Folders</strong> activée côté serveur Nextcloud. Les documents déjà existants ne sont pas déplacés automatiquement — voir « Migration vers Nextcloud » ci-dessous.</small>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Délai de grâce après impayé (jours)</label>
                    <input type="number" name="storage_grace_days" min="0" max="180" value="<?= $storageGraceDays ?>">
                </div>
                <div class="form-group">
                    <label>Seuil d'alerte capacité vendue/disponible (%)</label>
                    <input type="number" name="storage_capacity_alert_threshold_pct" min="1" max="100" value="<?= $storageCapacityThresholdPct ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Modules exclus du quota familial (cosmétique/avatars)</label>
                <input type="text" name="storage_quota_excluded_modules" value="<?= htmlspecialchars($storageExcludedModules) ?>" placeholder="avatar">
                <small style="color:var(--text-muted)">Liste de slugs de module séparés par des virgules (ex. <code>avatar</code>). Ces fichiers restent comptés dans le détail par module mais jamais dans le quota.</small>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
        </form>
        </div>

        <div class="settings-tab-panel" data-admin-subtab-panel="storage-consommation">
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Consommation du quota familial partagé (<?= $storageFamilyQuotaUnlimited ? 'illimité' : $storageFamilyQuotaGb . ' Go' ?>), modules exclus non comptés dans le total ni le %, mais listés dans le détail. Seules les familles utilisant au moins un fichier enregistré apparaissent — avant le script de rattrapage (<code>scripts/backfill_stored_files.php</code>), les fichiers déjà existants n'y figurent pas encore.
        </p>
        <table class="admin-table">
            <thead><tr><th>Famille</th><th>Utilisé</th><th>% du quota</th><th>Détail par module</th></tr></thead>
            <tbody>
            <?php foreach ($familyStorageUsage as $fu): ?>
                <tr>
                    <td><?= htmlspecialchars($fu['family_name']) ?></td>
                    <td><?= number_format($fu['used_bytes'] / 1024 / 1024 / 1024, 2, ',', ' ') ?> Go</td>
                    <td style="<?= (!$fu['unlimited'] && $fu['pct'] >= $storageCapacityThresholdPct) ? 'color:var(--danger);font-weight:600' : '' ?>"><?= $fu['unlimited'] ? 'Illimité' : $fu['pct'] . '%' ?></td>
                    <td style="font-size:.8rem;color:var(--text-muted)">
                        <?php foreach ($fu['breakdown'] as $b): ?>
                            <?= htmlspecialchars($b['module']) ?> (<?= number_format($b['total_bytes'] / 1024 / 1024, 1, ',', ' ') ?> Mo)<?= in_array($b['module'], \App\Core\FileStore::excludedModules(), true) ? ' <em>hors quota</em>' : '' ?><br>
                        <?php endforeach; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($familyStorageUsage)): ?>
                <tr><td colspan="4" style="color:var(--text-muted)">Aucune famille avec des fichiers enregistrés pour le moment.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>

        <div class="settings-tab-panel" data-admin-subtab-panel="storage-rattrapage">
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Enregistre dans stored_files tous les fichiers déjà sur disque avant l'introduction du
            quota (documents, garanties, avatars, photos...) — sans effet destructif, sans jamais
            bloquer rétroactivement une famille déjà au-delà du quota (seulement signalée ici).
            Lancez d'abord la simulation pour voir le rapport avant d'appliquer.
        </p>
        <div style="display:flex;gap:.6rem;margin-bottom:1.25rem">
            <form method="POST" action="<?= BASE_URL ?>/admin/storage/backfill"><?= \App\Core\Csrf::field() ?>
                <button type="submit" class="btn btn-secondary btn-sm">▶️ Lancer en simulation</button>
            </form>
            <form method="POST" action="<?= BASE_URL ?>/admin/storage/backfill" onsubmit="return confirm('Appliquer le rattrapage ? Les fichiers legacy (avatars, photos) seront déplacés hors de public/uploads/ et les colonnes correspondantes mises à jour. Action idempotente, sans suppression de données.');"><?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="apply" value="1">
                <button type="submit" class="btn btn-primary btn-sm">✅ Appliquer</button>
            </form>
        </div>

        <?php if ($storageBackfillReport): ?>
        <div class="card" style="padding:1.25rem">
            <h4 style="margin-top:0"><?= $storageBackfillReport['apply'] ? 'Rapport — appliqué' : 'Rapport — simulation' ?></h4>
            <ul style="margin:0 0 1rem;padding-left:1.2rem;font-size:.9rem">
                <li>Fichiers enregistrés : <?= $storageBackfillReport['stats']['registered'] ?></li>
                <li>Déjà enregistrés (ignorés) : <?= $storageBackfillReport['stats']['skipped_existing'] ?></li>
                <li>Fichiers manquants sur disque (ignorés) : <?= $storageBackfillReport['stats']['skipped_missing_file'] ?></li>
                <li>Fichiers déplacés hors webroot : <?= $storageBackfillReport['stats']['moved'] ?></li>
                <li>Volume total : <?= number_format($storageBackfillReport['stats']['bytes'] / 1024 / 1024, 1, ',', ' ') ?> Mo</li>
            </ul>

            <?php if (!empty($storageBackfillReport['per_module'])): ?>
            <p style="font-weight:600;margin-bottom:.3rem">Par module :</p>
            <ul style="margin:0 0 1rem;padding-left:1.2rem;font-size:.9rem">
                <?php foreach ($storageBackfillReport['per_module'] as $module => $bytes): ?>
                    <li><?= htmlspecialchars($module) ?> : <?= number_format($bytes / 1024 / 1024, 1, ',', ' ') ?> Mo</li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <p style="font-weight:600;margin-bottom:.3rem">Familles dépassant le quota (non bloquées, grandfathering) :</p>
            <?php if (empty($storageBackfillReport['over_quota'])): ?>
                <p style="color:var(--text-muted);font-size:.9rem">Aucune.</p>
            <?php else: ?>
                <ul style="margin:0 0 1rem;padding-left:1.2rem;font-size:.9rem">
                    <?php foreach ($storageBackfillReport['over_quota'] as $fam): ?>
                        <li style="color:var(--danger)">Famille #<?= $fam['family_id'] ?> : <?= number_format($fam['bytes'] / 1024 / 1024 / 1024, 2, ',', ' ') ?> Go</li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <details>
                <summary style="cursor:pointer;color:var(--text-muted);font-size:.85rem">Journal détaillé</summary>
                <pre style="font-size:.75rem;white-space:pre-wrap;max-height:300px;overflow-y:auto;background:var(--bg-secondary);padding:.75rem;border-radius:6px;margin-top:.5rem"><?= htmlspecialchars(implode("\n", $storageBackfillReport['log'])) ?></pre>
            </details>
        </div>
        <script>switchAdminSubTab('storage-rattrapage');</script>
        <?php endif; ?>
        </div>

        <div class="settings-tab-panel" data-admin-subtab-panel="storage-nextcloud-migration">
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Transfère les documents déjà enregistrés sur le disque FamilyBoard vers le Group
            Folder Nextcloud de leur famille (créé automatiquement si besoin) — le fichier local
            n'est supprimé qu'après confirmation que le contenu est bien arrivé intact sur
            Nextcloud, jamais avant. Rejouable : un fichier déjà migré n'est plus repris par un
            nouveau passage. Nécessite « Stocker les documents des modules sur Nextcloud » activé
            dans l'onglet Réglages. Lancez d'abord la simulation.
        </p>
        <div style="display:flex;gap:.6rem;margin-bottom:1.25rem">
            <form method="POST" action="<?= BASE_URL ?>/admin/storage/nextcloud-file-migration"><?= \App\Core\Csrf::field() ?>
                <button type="submit" class="btn btn-secondary btn-sm">▶️ Lancer en simulation</button>
            </form>
            <form method="POST" action="<?= BASE_URL ?>/admin/storage/nextcloud-file-migration" onsubmit="return confirm('Transférer réellement les documents vers Nextcloud ? Chaque fichier local n\'est supprimé qu\'après confirmation du transfert, jamais avant.');"><?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="apply" value="1">
                <button type="submit" class="btn btn-primary btn-sm">✅ Appliquer</button>
            </form>
        </div>

        <?php if ($nextcloudFileMigrationReport): ?>
        <div class="card" style="padding:1.25rem">
            <h4 style="margin-top:0"><?= $nextcloudFileMigrationReport['apply'] ? 'Rapport — appliqué' : 'Rapport — simulation' ?></h4>
            <ul style="margin:0 0 1rem;padding-left:1.2rem;font-size:.9rem">
                <li>Fichiers migrés : <?= $nextcloudFileMigrationReport['stats']['migrated'] ?></li>
                <li>Introuvables sur disque (ignorés) : <?= $nextcloudFileMigrationReport['stats']['skipped_missing'] ?></li>
                <li>Échecs : <?= $nextcloudFileMigrationReport['stats']['failed'] ?></li>
                <li>Volume total : <?= number_format($nextcloudFileMigrationReport['stats']['bytes'] / 1024 / 1024, 1, ',', ' ') ?> Mo</li>
            </ul>

            <?php if (!empty($nextcloudFileMigrationReport['failures'])): ?>
            <p style="font-weight:600;margin-bottom:.3rem;color:var(--danger)">Échecs :</p>
            <ul style="margin:0 0 1rem;padding-left:1.2rem;font-size:.9rem">
                <?php foreach ($nextcloudFileMigrationReport['failures'] as $f): ?>
                    <li style="color:var(--danger)"><?= htmlspecialchars($f) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <details>
                <summary style="cursor:pointer;color:var(--text-muted);font-size:.85rem">Journal détaillé</summary>
                <pre style="font-size:.75rem;white-space:pre-wrap;max-height:300px;overflow-y:auto;background:var(--bg-secondary);padding:.75rem;border-radius:6px;margin-top:.5rem"><?= htmlspecialchars(implode("\n", $nextcloudFileMigrationReport['log'])) ?></pre>
            </details>
        </div>
        <script>switchAdminSubTab('storage-nextcloud-migration');</script>
        <?php endif; ?>

        <hr style="margin:1.5rem 0;border-color:var(--border)">
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Corrige, sur le Group Folder lui-même, les fichiers déjà migrés vers Nextcloud avant
            l'introduction d'un nom de fichier et de dossier de module lisibles (upload ou
            migration antérieure à ces correctifs) — ils portent sinon un nom aléatoire et/ou se
            trouvent dans un dossier nommé en anglais (ex. "warranties" au lieu de "garanties"),
            visible tel quel par les membres de la famille dans leur client Nextcloud. Un ancien
            dossier de module devenu vide après déplacement de tout son contenu est supprimé.
            Rejouable : un fichier déjà conforme n'est pas retraité.
            <strong>Ne renommez/déplacez jamais ces fichiers manuellement depuis l'interface
            Nextcloud</strong> : FamilyBoard chercherait ensuite le fichier à son ancien
            emplacement et le perdrait (404) — passez toujours par ce bouton, qui met à jour la
            base en même temps que l'emplacement réel.
        </p>
        <div style="display:flex;gap:.6rem;margin-bottom:1.25rem">
            <form method="POST" action="<?= BASE_URL ?>/admin/storage/nextcloud-file-rename"><?= \App\Core\Csrf::field() ?>
                <button type="submit" class="btn btn-secondary btn-sm">▶️ Lancer en simulation</button>
            </form>
            <form method="POST" action="<?= BASE_URL ?>/admin/storage/nextcloud-file-rename" onsubmit="return confirm('Renommer réellement ces fichiers sur Nextcloud ?');"><?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="apply" value="1">
                <button type="submit" class="btn btn-primary btn-sm">✅ Appliquer</button>
            </form>
        </div>

        <?php if ($nextcloudFileRenameReport): ?>
        <div class="card" style="padding:1.25rem">
            <h4 style="margin-top:0"><?= $nextcloudFileRenameReport['apply'] ? 'Rapport — appliqué' : 'Rapport — simulation' ?></h4>
            <ul style="margin:0 0 1rem;padding-left:1.2rem;font-size:.9rem">
                <li>Fichiers déplacés/renommés : <?= $nextcloudFileRenameReport['stats']['renamed'] ?></li>
                <li>Déjà conformes (ignorés) : <?= $nextcloudFileRenameReport['stats']['already_ok'] ?></li>
                <li>Anciens dossiers vidés supprimés : <?= $nextcloudFileRenameReport['stats']['folders_cleaned'] ?></li>
                <li>Échecs : <?= $nextcloudFileRenameReport['stats']['failed'] ?></li>
            </ul>

            <?php if (!empty($nextcloudFileRenameReport['failures'])): ?>
            <p style="font-weight:600;margin-bottom:.3rem;color:var(--danger)">Échecs :</p>
            <ul style="margin:0 0 1rem;padding-left:1.2rem;font-size:.9rem">
                <?php foreach ($nextcloudFileRenameReport['failures'] as $f): ?>
                    <li style="color:var(--danger)"><?= htmlspecialchars($f) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <details>
                <summary style="cursor:pointer;color:var(--text-muted);font-size:.85rem">Journal détaillé</summary>
                <pre style="font-size:.75rem;white-space:pre-wrap;max-height:300px;overflow-y:auto;background:var(--bg-secondary);padding:.75rem;border-radius:6px;margin-top:.5rem"><?= htmlspecialchars(implode("\n", $nextcloudFileRenameReport['log'])) ?></pre>
            </details>
        </div>
        <script>switchAdminSubTab('storage-nextcloud-migration');</script>
        <?php endif; ?>

        <hr style="margin:1.5rem 0;border-color:var(--border)">
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Rattrape immédiatement les membres déjà provisionnés LDAP mais jamais ajoutés au
            groupe Nextcloud de leur famille (ex. provisionnés avant l'activation du backend
            fichiers) — sans effet destructif, sans attendre qu'un document soit enregistré.
        </p>
        <form method="POST" action="<?= BASE_URL ?>/admin/storage/nextcloud-group-resync"><?= \App\Core\Csrf::field() ?>
            <button type="submit" class="btn btn-secondary btn-sm">🔄 Resynchroniser les groupes Nextcloud</button>
        </form>
        <?php if ($nextcloudGroupResyncReport): ?>
        <div class="card" style="padding:1.25rem;margin-top:1rem">
            <p style="margin:0">Familles traitées : <?= $nextcloudGroupResyncReport['count'] ?></p>
            <?php if (!empty($nextcloudGroupResyncReport['errors'])): ?>
            <p style="font-weight:600;margin:.75rem 0 .3rem;color:var(--danger)">Échecs :</p>
            <ul style="margin:0;padding-left:1.2rem;font-size:.9rem">
                <?php foreach ($nextcloudGroupResyncReport['errors'] as $e): ?>
                    <li style="color:var(--danger)"><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <script>switchAdminSubTab('storage-nextcloud-migration');</script>
        <?php endif; ?>
        </div>

        <div class="settings-tab-panel" data-admin-subtab-panel="storage-produits">
        <?php $storageStripeLinked = (bool)($stripePublishableKey || $stripeSecretKey); ?>
        <table class="admin-table" style="margin-bottom:1rem">
            <thead><tr><th>Code</th><th>Nom</th><th>Portée</th><th>Volume</th><th>Prix / mois</th><?php if ($storageStripeLinked): ?><th>Prix Stripe</th><?php endif; ?><th>Actif</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($storageProducts as $sp): ?>
                <tr>
                    <td><?= htmlspecialchars($sp['code']) ?></td>
                    <td><?= htmlspecialchars($sp['name']) ?></td>
                    <td><?= $sp['scope'] === 'family' ? '👪 Famille' : '👤 Membre' ?></td>
                    <td><?= $sp['quota_gb'] >= 1024 ? number_format($sp['quota_gb'] / 1024, 1, ',', ' ') . ' To' : $sp['quota_gb'] . ' Go' ?></td>
                    <td><?= number_format($sp['price_monthly_cents'] / 100, 2, ',', ' ') ?> €</td>
                    <?php if ($storageStripeLinked): ?>
                    <td style="font-size:.75rem;color:var(--text-muted)"><?= htmlspecialchars($sp['stripe_price_id_monthly'] ?? '—') ?></td>
                    <?php endif; ?>
                    <td><?= $sp['active'] ? '✅' : '⛔' ?></td>
                    <td style="display:flex;gap:.3rem">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="editStorageProduct(<?= htmlspecialchars(json_encode($sp), ENT_QUOTES) ?>)">Modifier</button>
                        <?php if ($sp['active']): ?>
                        <form method="POST" action="<?= BASE_URL ?>/admin/storage/products/<?= $sp['id'] ?>/deactivate" onsubmit="return confirm('Désactiver ce palier ? Il ne sera plus proposé aux nouvelles souscriptions.');"><?= \App\Core\Csrf::field() ?>
                            <button type="submit" class="btn btn-danger btn-sm">Désactiver</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$storageStripeLinked): ?>
        <p style="color:var(--text-muted);font-size:.8rem;margin:-.5rem 0 1rem">La colonne de prix Stripe apparaîtra une fois une clé renseignée dans l'onglet « Abonnements → Clés Stripe ».</p>
        <?php endif; ?>
        <button type="button" class="btn btn-primary btn-sm" style="align-self:flex-start" onclick="newStorageProduct()">+ Nouveau palier</button>

        <form method="POST" action="<?= BASE_URL ?>/admin/storage/products" id="storage-product-form" class="card" style="padding:1.25rem;max-width:640px;margin-top:1rem"><?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="id" id="storage-product-id">
            <div class="form-row">
                <div class="form-group">
                    <label>Code (identifiant technique)</label>
                    <input type="text" name="code" id="storage-product-code" placeholder="storage_100gb">
                </div>
                <div class="form-group">
                    <label>Nom affiché</label>
                    <input type="text" name="name" id="storage-product-name" value="+100 Go">
                </div>
                <div class="form-group">
                    <label>Volume (Go)</label>
                    <input type="number" name="quota_gb" id="storage-product-quota" min="1" value="100">
                </div>
                <div class="form-group">
                    <label>Portée</label>
                    <select name="scope" id="storage-product-scope">
                        <option value="member">👤 Stockage perso (membre)</option>
                        <option value="family">👪 Stockage familial partagé</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Prix mensuel (€)</label>
                    <input type="text" name="price_monthly" id="storage-product-price" placeholder="1.99">
                </div>
                <div class="form-group">
                    <label>Ordre d'affichage</label>
                    <input type="number" name="sort_order" id="storage-product-sort-order" value="0">
                </div>
            </div>
            <?php if ($storageStripeLinked): ?>
            <p style="color:var(--text-muted);font-size:.8rem">Le Produit et le Prix Stripe sont créés/mis à jour automatiquement à l'enregistrement — rien à faire côté Stripe.</p>
            <?php endif; ?>
            <div class="form-group">
                <label><input type="checkbox" name="active" id="storage-product-active" value="1" checked> Actif (proposé aux membres)</label>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Enregistrer le palier</button>
        </form>
        <script>
        function newStorageProduct() {
            document.getElementById('storage-product-id').value = '';
            document.getElementById('storage-product-code').value = '';
            document.getElementById('storage-product-code').disabled = false;
            document.getElementById('storage-product-name').value = '+100 Go';
            document.getElementById('storage-product-quota').value = '100';
            document.getElementById('storage-product-scope').value = 'member';
            document.getElementById('storage-product-price').value = '';
            document.getElementById('storage-product-sort-order').value = '0';
            document.getElementById('storage-product-active').checked = true;
        }
        function editStorageProduct(p) {
            document.getElementById('storage-product-id').value = p.id;
            document.getElementById('storage-product-code').value = p.code;
            document.getElementById('storage-product-code').disabled = true;
            document.getElementById('storage-product-name').value = p.name;
            document.getElementById('storage-product-quota').value = p.quota_gb;
            document.getElementById('storage-product-scope').value = p.scope || 'member';
            document.getElementById('storage-product-price').value = (p.price_monthly_cents / 100).toFixed(2);
            document.getElementById('storage-product-sort-order').value = p.sort_order;
            document.getElementById('storage-product-active').checked = !!(p.active * 1);
            document.getElementById('storage-product-form').scrollIntoView({ behavior: 'smooth' });
        }
        </script>
        </div>

        <div class="settings-tab-panel" data-admin-subtab-panel="storage-infra">
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Les comptes des membres dépendent toujours du Serveur LDAP Synology (identité) ;
            le stockage perso et familial peut ensuite être poussé vers Synology et/ou
            Nextcloud au choix — les deux peuvent être actifs en même temps (Nextcloud
            reconnaît le même compte via sa propre configuration LDAP côté NAS, FamilyBoard ne
            fait que lui pousser le quota en plus). Mots de passe chiffrés en base, jamais
            journalisés en clair. Tant que rien n'est activé ci-dessous, rien n'est
            provisionné : la comptabilité applicative (quota, consommation) continue de
            fonctionner indépendamment.
        </p>
        <form method="POST" action="<?= BASE_URL ?>/admin/storage/infra" class="card" style="padding:1.25rem;max-width:640px"><?= \App\Core\Csrf::field() ?>
            <div class="form-group">
                <label><input type="checkbox" name="storage_ldap_enabled" value="1" <?= $storageLdapEnabled ? 'checked' : '' ?>> Activer les comptes LDAP (Serveur LDAP Synology)</label>
            </div>
            <div class="form-group">
                <label>Groupe LDAP (posixGroup) des membres FamilyBoard</label>
                <input type="text" name="storage_ldap_group" value="<?= htmlspecialchars($storageLdapGroup) ?>" placeholder="familyboard">
            </div>
            <div class="form-group">
                <label>Backend(s) de quota de stockage</label>
                <label style="font-weight:normal;display:block"><input type="checkbox" name="storage_quota_backends[]" value="synology" <?= in_array('synology', $storageQuotaBackends, true) ? 'checked' : '' ?>> Synology (quota DSM par dossier partagé)</label>
                <label style="font-weight:normal;display:block"><input type="checkbox" name="storage_quota_backends[]" value="nextcloud" <?= in_array('nextcloud', $storageQuotaBackends, true) ? 'checked' : '' ?>> Nextcloud (quota via l'API OCS)</label>
            </div>

            <h4 style="margin-top:0">LDAP</h4>
            <div class="form-group">
                <label>Hôte (ex. ldaps://ldap.example.com:636)</label>
                <input type="text" name="ldap_host" value="<?= htmlspecialchars($storageSettings['ldap_host'] ?? '') ?>" autocomplete="off">
            </div>
            <div class="form-group">
                <label>DN de connexion (bind DN)</label>
                <input type="text" name="ldap_bind_dn" value="<?= htmlspecialchars($storageSettings['ldap_bind_dn'] ?? '') ?>" autocomplete="off">
            </div>
            <div class="form-group">
                <label>Mot de passe</label>
                <?php if (!empty($storageSettings['ldap_bind_password'])): ?>
                    <input type="text" value="•••••••••••••• (enregistré)" disabled>
                    <details style="margin-top:.4rem">
                        <summary style="cursor:pointer;font-size:.8rem;color:var(--text-muted)">Changer le mot de passe</summary>
                        <input type="password" name="ldap_bind_password" placeholder="Nouveau mot de passe" autocomplete="new-password" style="margin-top:.5rem">
                    </details>
                <?php else: ?>
                    <input type="password" name="ldap_bind_password" autocomplete="new-password">
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>DN de base</label>
                <input type="text" name="ldap_base_dn" value="<?= htmlspecialchars($storageSettings['ldap_base_dn'] ?? '') ?>" autocomplete="off" placeholder="dc=example,dc=com">
                <small style="color:var(--text-muted)">La détection d'un compte/groupe déjà existant cherche dans tout l'arbre sous ce DN — inutile de connaître le conteneur exact pour ça.</small>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Conteneur de création des comptes (optionnel)</label>
                    <input type="text" name="storage_ldap_people_dn" value="<?= htmlspecialchars($storageLdapPeopleDn) ?>" autocomplete="off" placeholder="cn=users,dc=example,dc=com (vide = DN de base)">
                </div>
                <div class="form-group">
                    <label>Conteneur de création des groupes (optionnel)</label>
                    <input type="text" name="storage_ldap_groups_dn" value="<?= htmlspecialchars($storageLdapGroupsDn) ?>" autocomplete="off" placeholder="cn=group,dc=example,dc=com (vide = DN de base)">
                </div>
            </div>
            <p style="color:var(--text-muted);font-size:.8rem;margin-top:-.5rem">
                À renseigner seulement si une <strong>création</strong> de compte échoue parce que le serveur exige un conteneur dédié (ex. le Serveur LDAP Synology place ses comptes sous "cn=users,&lt;DN de base&gt;"). Sans effet sur la détection d'un compte déjà existant.
            </p>
            <?php if (!empty($storageSettings['ldap_host'])): ?>
            <div style="margin-bottom:1rem">
                <button type="button" class="btn btn-secondary btn-sm" onclick="testStorageConnection('ldap')">🔌 Tester la connexion LDAP</button>
                <div id="storage-ldap-test-result" style="margin-top:.5rem"></div>
            </div>
            <div style="margin-bottom:1rem;padding:.75rem;border:1px solid var(--border);border-radius:6px">
                <label style="display:block;margin-bottom:.4rem">Tester un compte (recherche un uid existant, sans son mot de passe)</label>
                <div style="display:flex;gap:.5rem">
                    <input type="text" id="storage-ldap-account-username" placeholder="ex. camille" style="flex:1">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="testStorageLdapAccount()">🔎 Tester</button>
                </div>
                <div id="storage-ldap-account-test-result" style="margin-top:.5rem"></div>
            </div>
            <?php endif; ?>

            <h4>Synology (quotas)</h4>
            <div class="form-group">
                <label>URL du DSM (ex. https://nas.example.com:5001)</label>
                <input type="text" name="synology_url" value="<?= htmlspecialchars($storageSettings['synology_url'] ?? '') ?>" autocomplete="off">
            </div>
            <div class="form-group">
                <label>Nom d'utilisateur</label>
                <input type="text" name="synology_username" value="<?= htmlspecialchars($storageSettings['synology_username'] ?? '') ?>" autocomplete="off">
            </div>
            <div class="form-group">
                <label>Mot de passe</label>
                <?php if (!empty($storageSettings['synology_password'])): ?>
                    <input type="text" value="•••••••••••••• (enregistré)" disabled>
                    <details style="margin-top:.4rem">
                        <summary style="cursor:pointer;font-size:.8rem;color:var(--text-muted)">Changer le mot de passe</summary>
                        <input type="password" name="synology_password" placeholder="Nouveau mot de passe" autocomplete="new-password" style="margin-top:.5rem">
                    </details>
                <?php else: ?>
                    <input type="password" name="synology_password" autocomplete="new-password">
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>Dossier partagé (quotas par membre)</label>
                <input type="text" name="synology_shared_folder" value="<?= htmlspecialchars($storageSettings['synology_shared_folder'] ?? '') ?>" autocomplete="off">
            </div>
            <?php if (!empty($storageSettings['synology_url'])): ?>
            <div style="margin-bottom:1rem">
                <button type="button" class="btn btn-secondary btn-sm" onclick="testStorageConnection('synology')">🔌 Tester la connexion Synology</button>
                <div id="storage-synology-test-result" style="margin-top:.5rem"></div>
            </div>
            <?php endif; ?>

            <h4>Nextcloud (quotas)</h4>
            <div class="form-group">
                <label>URL de l'instance (ex. https://cloud.example.com)</label>
                <input type="text" name="nextcloud_url" value="<?= htmlspecialchars($storageSettings['nextcloud_url'] ?? '') ?>" autocomplete="off">
            </div>
            <div class="form-group">
                <label>Utilisateur administrateur</label>
                <input type="text" name="nextcloud_admin_user" value="<?= htmlspecialchars($storageSettings['nextcloud_admin_user'] ?? '') ?>" autocomplete="off">
            </div>
            <div class="form-group">
                <label>Mot de passe (de préférence un mot de passe d'application Nextcloud dédié)</label>
                <?php if (!empty($storageSettings['nextcloud_admin_password'])): ?>
                    <input type="text" value="•••••••••••••• (enregistré)" disabled>
                    <details style="margin-top:.4rem">
                        <summary style="cursor:pointer;font-size:.8rem;color:var(--text-muted)">Changer le mot de passe</summary>
                        <input type="password" name="nextcloud_admin_password" placeholder="Nouveau mot de passe" autocomplete="new-password" style="margin-top:.5rem">
                    </details>
                <?php else: ?>
                    <input type="password" name="nextcloud_admin_password" autocomplete="new-password">
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="nextcloud_calendar_sync_enabled" value="1" <?= $nextcloudCalendarSyncEnabled ? 'checked' : '' ?>> Synchroniser le calendrier (événements, garde alternée, congés) vers Nextcloud, à sens unique</label>
                <small style="color:var(--text-muted)">Pousse vers un calendrier "FamilyBoard" dédié par membre (compte technique ci-dessus, partagé en lecture seule) — jamais l'inverse, aucune écriture depuis Nextcloud ne revient dans FamilyBoard. Nécessite les comptes LDAP activés ci-dessus.</small>
            </div>
            <?php if (!empty($storageSettings['nextcloud_url'])): ?>
            <div style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap">
                <button type="button" class="btn btn-secondary btn-sm" onclick="testStorageConnection('nextcloud')">🔌 Tester la connexion Nextcloud (OCS)</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="testStorageConnection('nextcloud-caldav')">🔌 Tester l'accès calendrier (CalDAV)</button>
            </div>
            <div id="storage-nextcloud-test-result" style="margin-bottom:.5rem"></div>
            <div id="storage-nextcloud-caldav-test-result" style="margin-bottom:1rem"></div>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
        </form>

        <?php if ($nextcloudCalendarSyncEnabled): ?>
        <div class="card" style="padding:1.25rem;max-width:640px;margin-top:1rem">
            <h4 style="margin-top:0">Synchronisation calendrier → Nextcloud</h4>
            <form method="POST" action="<?= BASE_URL ?>/admin/nextcloud/calendar-sync/run"><?= \App\Core\Csrf::field() ?>
                <button type="submit" class="btn btn-secondary btn-sm">🔄 Synchroniser maintenant</button>
            </form>
            <?php if ($nextcloudCalendarSyncReport): ?>
            <p style="margin-top:.75rem;font-size:.9rem">
                <?= $nextcloudCalendarSyncReport['members'] ?> membre(s) synchronisé(s) —
                <?= $nextcloudCalendarSyncReport['pushed'] ?> événement(s) créé(s),
                <?= $nextcloudCalendarSyncReport['updated'] ?> mis à jour,
                <?= $nextcloudCalendarSyncReport['deleted'] ?> supprimé(s).
            </p>
            <?php if (!empty($nextcloudCalendarSyncReport['errors'])): ?>
            <ul style="margin:.5rem 0 0;padding-left:1.2rem;font-size:.85rem;color:var(--danger)">
                <?php foreach ($nextcloudCalendarSyncReport['errors'] as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="card" style="padding:1.25rem;max-width:640px;margin-top:1rem">
            <h4 style="margin-top:0">File de provisioning</h4>
            <p style="color:var(--text-muted);font-size:.85rem">
                Traitée automatiquement par le cron toutes les minutes. État : <strong><?= htmlspecialchars(\App\Core\StorageProvisionerFactory::summaryLabel()) ?></strong>.
            </p>
            <form method="POST" action="<?= BASE_URL ?>/admin/storage/provisioning/run"><?= \App\Core\Csrf::field() ?>
                <button type="submit" class="btn btn-secondary btn-sm">▶️ Traiter la file maintenant</button>
            </form>
            <?php if ($storageProvisioningReport): ?>
            <p style="margin-top:.75rem;font-size:.9rem">
                <?= $storageProvisioningReport['skipped_no_backend']
                    ? 'Aucun backend actif — rien à traiter.'
                    : $storageProvisioningReport['processed'] . ' job(s) traité(s), ' . $storageProvisioningReport['succeeded'] . ' réussi(s), ' . $storageProvisioningReport['failed'] . ' échoué(s).' ?>
            </p>
            <?php endif; ?>

            <table class="admin-table" style="margin-top:1rem">
                <thead><tr><th>Membre</th><th>Type</th><th>Statut</th><th>Tentatives</th><th>Dernière erreur</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($storageOutstandingJobs as $job): ?>
                    <tr>
                        <td><?= htmlspecialchars($job['user_name']) ?></td>
                        <td><?= htmlspecialchars($job['job_type']) ?></td>
                        <td><?= htmlspecialchars($job['status']) ?></td>
                        <td><?= $job['attempts'] ?></td>
                        <td style="font-size:.8rem;color:var(--danger)"><?= htmlspecialchars($job['last_error'] ?? '') ?></td>
                        <td>
                            <?php if ((int)$job['attempts'] >= 3): ?>
                            <form method="POST" action="<?= BASE_URL ?>/admin/storage/provisioning/<?= $job['id'] ?>/retry"><?= \App\Core\Csrf::field() ?>
                                <button type="submit" class="btn btn-secondary btn-sm" title="Tentatives épuisées : ce job n'est plus jamais repris par le cron ni par 'Traiter la file maintenant' tant qu'il n'est pas remis à zéro ici.">🔄 Réessayer</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($storageOutstandingJobs)): ?>
                    <tr><td colspan="6" style="color:var(--text-muted)">Aucun job en attente ou en échec.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card" style="padding:1.25rem;max-width:720px;margin-top:1rem">
            <h4 style="margin-top:0">Migration des comptes créés avant l'intégration LDAP</h4>
            <p style="color:var(--text-muted);font-size:.85rem">
                FamilyBoard n'a jamais accès au mot de passe en clair des membres existants
                (seulement son hash) — leur compte LDAP ne peut donc être créé qu'à leur
                <strong>prochaine connexion réussie</strong> (le mot de passe est brièvement en
                clair côté serveur à cet instant). Cette simulation ne crée rien : elle détecte à
                l'avance les conflits de nom (uid déjà pris par un e-mail différent) pour que tu
                puisses les résoudre avant que le membre ne se reconnecte.
            </p>
            <form method="POST" action="<?= BASE_URL ?>/admin/storage/ldap/migration/analyze"><?= \App\Core\Csrf::field() ?>
                <button type="submit" class="btn btn-secondary btn-sm">🔎 Analyser les comptes sans LDAP</button>
            </form>

            <?php if ($storageLdapMigrationReport !== null): ?>
            <table class="admin-table" style="margin-top:1rem">
                <thead><tr><th>Membre</th><th>Famille</th><th>uid attendu</th><th>Statut</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($storageLdapMigrationReport as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['name']) ?><br><small style="color:var(--text-muted)"><?= htmlspecialchars($row['email']) ?></small></td>
                        <td><?= htmlspecialchars($row['family_name']) ?></td>
                        <td><code><?= htmlspecialchars($row['ldap_resolved_username'] ?: $row['candidate']) ?></code></td>
                        <td>
                            <?php if ($row['ldap_resolved_username']): ?>
                                <span style="color:var(--success)">Résolu — sera créé/adopté à la prochaine connexion</span>
                            <?php elseif (!$row['exists']): ?>
                                <span style="color:var(--text-muted)">Aucun conflit — sera créé à la prochaine connexion</span>
                            <?php elseif ($row['matches']): ?>
                                <span style="color:var(--success)">Compte existant, même e-mail — sera associé automatiquement</span>
                            <?php else: ?>
                                <span style="color:var(--danger)">⚠️ Conflit — uid pris par <?= htmlspecialchars($row['mail'] ?: '(e-mail inconnu)') ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <details>
                                <summary style="cursor:pointer;font-size:.8rem">Résoudre</summary>
                                <form method="POST" action="<?= BASE_URL ?>/admin/storage/ldap/migration/<?= $row['id'] ?>/resolve" style="margin-top:.4rem;display:flex;gap:.4rem;flex-wrap:wrap"><?= \App\Core\Csrf::field() ?>
                                    <?php if ($row['exists']): ?>
                                    <button type="submit" name="adopt_username" value="<?= htmlspecialchars($row['candidate']) ?>" class="btn btn-secondary btn-sm" title="Associer ce compte existant, sans toucher à son mot de passe ni ses données — le prochain mot de passe FamilyBoard de ce membre l'écrasera.">Associer « <?= htmlspecialchars($row['candidate']) ?> »</button>
                                    <?php endif; ?>
                                    <input type="text" name="ldap_username" placeholder="uid réel (ex. identifiant LDAP existant)" style="width:220px">
                                    <button type="submit" class="btn btn-primary btn-sm">Utiliser cet uid</button>
                                </form>
                                <p style="color:var(--text-muted);font-size:.75rem;margin:.3rem 0 0">
                                    L'uid attendu est deviné depuis la partie locale de l'e-mail — s'il ne correspond pas au vrai identifiant LDAP de ce membre (ex. compte créé avant FamilyBoard, sous un autre nom), indique-le ici pour qu'il soit associé à la prochaine connexion plutôt qu'un nouveau compte créé à tort.
                                </p>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($storageLdapMigrationReport)): ?>
                    <tr><td colspan="5" style="color:var(--text-muted)">Tous les membres ont déjà un compte LDAP.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        </div>

