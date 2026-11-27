        <div class="settings-tabs">
            <button type="button" class="settings-tab-btn active" data-tab="sub-reglages" onclick="switchAdminSubTab('sub-reglages')">⚙️ Réglages</button>
            <button type="button" class="settings-tab-btn" data-tab="sub-stripe" onclick="switchAdminSubTab('sub-stripe')">🔑 Clés Stripe</button>
            <button type="button" class="settings-tab-btn" data-tab="sub-plans" onclick="switchAdminSubTab('sub-plans')">📦 Paliers</button>
            <button type="button" class="settings-tab-btn" data-tab="sub-families" onclick="switchAdminSubTab('sub-families')">👪 Abonnements des familles</button>
            <button type="button" class="settings-tab-btn" data-tab="sub-purges" onclick="switchAdminSubTab('sub-purges')">🗑️ Suppressions</button>
            <button type="button" class="settings-tab-btn" data-tab="sub-urssaf" onclick="switchAdminSubTab('sub-urssaf')">📄 Rapport URSSAF</button>
        </div>
        <script>
        function switchAdminSubTab(tab) {
            document.querySelectorAll('[data-admin-subtab-panel]').forEach(p => p.classList.toggle('active', p.dataset.adminSubtabPanel === tab));
            document.querySelectorAll('.settings-tab-btn[data-tab^="sub-"]').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
        }
        </script>

        <div class="settings-tab-panel active" data-admin-subtab-panel="sub-reglages">
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Tant que la facturation est désactivée, tous les modules restent accessibles librement à
            toutes les familles, quel que soit leur abonnement — activer ce réglage ne verrouille
            jamais rétroactivement une famille sans que vous ne l'ayez décidé explicitement ici.
        </p>
        <form method="POST" action="<?= BASE_URL ?>/admin/subscriptions/settings" class="card" style="padding:1.25rem;max-width:640px"><?= \App\Core\Csrf::field() ?>
            <div class="form-group">
                <label><input type="checkbox" name="sub_billing_enabled" value="1" <?= $subBillingEnabled ? 'checked' : '' ?>> Activer la facturation (restreint les modules Premium aux abonnés)</label>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Durée de l'essai gratuit (jours)</label>
                    <input type="number" name="sub_trial_days" min="0" max="90" value="<?= $subTrialDays ?>">
                </div>
                <div class="form-group">
                    <label>Délai de grâce après impayé/résiliation (jours)</label>
                    <input type="number" name="sub_grace_days" min="0" max="180" value="<?= $subGraceDays ?>">
                </div>
                <div class="form-group">
                    <label>Réduction annuelle (%)</label>
                    <input type="number" name="sub_annual_discount_pct" min="0" max="90" value="<?= $subAnnualDiscount ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Modules toujours gratuits (socle de l'offre gratuite)</label>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.3rem;max-height:220px;overflow-y:auto;border:1px solid var(--border);border-radius:6px;padding:.6rem">
                    <?php foreach (\App\Models\Family::MODULES as $slug => $mod): ?>
                        <label style="font-weight:normal"><input type="checkbox" name="sub_free_modules[]" value="<?= $slug ?>" <?= in_array($slug, $subFreeModules, true) ? 'checked' : '' ?>> <?= $mod['icon'] ?> <?= htmlspecialchars($mod['label']) ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
        </form>
        </div>

        <div class="settings-tab-panel" data-admin-subtab-panel="sub-stripe">
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Créez les produits/prix côté Stripe puis reliez-les à chaque palier ci-dessous. L'URL de
            webhook à configurer sur Stripe est <code><?= BASE_URL ?>/stripe/webhook</code>.
        </p>
        <form method="POST" action="<?= BASE_URL ?>/admin/subscriptions/stripe" class="card" style="padding:1.25rem;max-width:640px"><?= \App\Core\Csrf::field() ?>
            <div class="form-group">
                <label>Clé publique (pk_...)</label>
                <input type="text" name="stripe_publishable_key" value="<?= htmlspecialchars($stripePublishableKey) ?>" autocomplete="off">
            </div>
            <div class="form-group">
                <label>Clé secrète (sk_...)</label>
                <?php if ($stripeSecretKey): ?>
                    <input type="text" value="•••••••••••••• <?= htmlspecialchars(substr($stripeSecretKey, -4)) ?>" disabled>
                    <input type="hidden" name="keep_existing_secret" value="1">
                    <details style="margin-top:.4rem">
                        <summary style="cursor:pointer;font-size:.8rem;color:var(--text-muted)">Changer ou désactiver la clé</summary>
                        <input type="text" name="stripe_secret_key" placeholder="Nouvelle clé secrète" autocomplete="off" style="margin-top:.5rem">
                    </details>
                <?php else: ?>
                    <input type="text" name="stripe_secret_key" placeholder="sk_live_… ou sk_test_…" autocomplete="off">
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>Secret de signature du webhook (whsec_...)</label>
                <?php if ($stripeWebhookSecret): ?>
                    <input type="text" value="•••••••••••••• <?= htmlspecialchars(substr($stripeWebhookSecret, -4)) ?>" disabled>
                    <input type="hidden" name="keep_existing_webhook" value="1">
                    <details style="margin-top:.4rem">
                        <summary style="cursor:pointer;font-size:.8rem;color:var(--text-muted)">Changer ou désactiver le secret</summary>
                        <input type="text" name="stripe_webhook_secret" placeholder="Nouveau secret" autocomplete="off" style="margin-top:.5rem">
                    </details>
                <?php else: ?>
                    <input type="text" name="stripe_webhook_secret" placeholder="whsec_…" autocomplete="off">
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
        </form>
        </div>

        <div class="settings-tab-panel" data-admin-subtab-panel="sub-plans">
        <?php $subStripeLinked = (bool)($stripePublishableKey || $stripeSecretKey); ?>
        <table class="admin-table" style="margin-bottom:1rem">
            <thead><tr><th>Code</th><th>Nom</th><th>Membres max</th><th>Prix / mois</th><th>Prix / an</th><?php if ($subStripeLinked): ?><th>Prix Stripe (mensuel)</th><th>Prix Stripe (annuel)</th><?php endif; ?><th>Actif</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($plans as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['code']) ?></td>
                    <td><?= htmlspecialchars($p['name']) ?></td>
                    <td><?= $p['member_limit'] ?: 'Illimité' ?></td>
                    <td><?= number_format($p['price_monthly_cents'] / 100, 2, ',', ' ') ?> €</td>
                    <td><?= number_format($p['price_yearly_cents'] / 100, 2, ',', ' ') ?> €</td>
                    <?php if ($subStripeLinked): ?>
                    <td style="font-size:.75rem;color:var(--text-muted)"><?= htmlspecialchars($p['stripe_price_id_monthly'] ?? '—') ?></td>
                    <td style="font-size:.75rem;color:var(--text-muted)"><?= htmlspecialchars($p['stripe_price_id_yearly'] ?? '—') ?></td>
                    <?php endif; ?>
                    <td><?= $p['active'] ? '✅' : '⛔' ?></td>
                    <td style="display:flex;gap:.3rem">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="editPlan(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)">Modifier</button>
                        <?php if ($p['active']): ?>
                        <form method="POST" action="<?= BASE_URL ?>/admin/plans/<?= $p['id'] ?>/deactivate" onsubmit="return confirm('Désactiver ce palier ? Il ne sera plus proposé aux nouvelles souscriptions.');"><?= \App\Core\Csrf::field() ?>
                            <button type="submit" class="btn btn-danger btn-sm">Désactiver</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$subStripeLinked): ?>
        <p style="color:var(--text-muted);font-size:.8rem;margin:-.5rem 0 1rem">Les colonnes de prix Stripe apparaîtront une fois une clé renseignée dans l'onglet « Clés Stripe ».</p>
        <?php endif; ?>
        <button type="button" class="btn btn-primary btn-sm" style="align-self:flex-start" onclick="newPlan()">+ Nouveau palier</button>

        <form method="POST" action="<?= BASE_URL ?>/admin/plans" id="plan-form" class="card" style="padding:1.25rem;max-width:640px;margin-top:1rem"><?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="id" id="plan-id">
            <div class="form-row">
                <div class="form-group">
                    <label>Code (identifiant technique)</label>
                    <input type="text" name="code" id="plan-code" placeholder="premium_4">
                </div>
                <div class="form-group">
                    <label>Nom affiché</label>
                    <input type="text" name="name" id="plan-name" value="Premium">
                </div>
                <div class="form-group">
                    <label>Membres max (vide = illimité)</label>
                    <input type="number" name="member_limit" id="plan-member-limit" min="1">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Prix mensuel (€)</label>
                    <input type="text" name="price_monthly" id="plan-price-monthly" placeholder="4.99">
                </div>
                <div class="form-group">
                    <label>Prix annuel (€)</label>
                    <input type="text" name="price_yearly" id="plan-price-yearly" placeholder="47.90">
                </div>
                <div class="form-group">
                    <label>Ordre d'affichage</label>
                    <input type="number" name="sort_order" id="plan-sort-order" value="0">
                </div>
            </div>
            <?php if ($subStripeLinked): ?>
            <p style="color:var(--text-muted);font-size:.8rem">Le Produit et les Prix Stripe sont créés/mis à jour automatiquement à l'enregistrement — rien à faire côté Stripe.</p>
            <?php endif; ?>
            <div class="form-group">
                <label><input type="checkbox" name="active" id="plan-active" value="1" checked> Actif (proposé aux familles)</label>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Enregistrer le palier</button>
        </form>
        <script>
        function newPlan() {
            document.getElementById('plan-id').value = '';
            document.getElementById('plan-code').value = '';
            document.getElementById('plan-code').disabled = false;
            document.getElementById('plan-name').value = 'Premium';
            document.getElementById('plan-member-limit').value = '';
            document.getElementById('plan-price-monthly').value = '';
            document.getElementById('plan-price-yearly').value = '';
            document.getElementById('plan-sort-order').value = '0';
            document.getElementById('plan-active').checked = true;
        }
        function editPlan(p) {
            document.getElementById('plan-id').value = p.id;
            document.getElementById('plan-code').value = p.code;
            document.getElementById('plan-code').disabled = true;
            document.getElementById('plan-name').value = p.name;
            document.getElementById('plan-member-limit').value = p.member_limit || '';
            document.getElementById('plan-price-monthly').value = (p.price_monthly_cents / 100).toFixed(2);
            document.getElementById('plan-price-yearly').value = (p.price_yearly_cents / 100).toFixed(2);
            document.getElementById('plan-sort-order').value = p.sort_order;
            document.getElementById('plan-active').checked = !!(p.active * 1);
            document.getElementById('plan-form').scrollIntoView({ behavior: 'smooth' });
        }
        </script>
        </div>

        <div class="settings-tab-panel" data-admin-subtab-panel="sub-families">
        <table class="admin-table">
            <thead><tr><th>Famille</th><th>Palier</th><th>Statut</th><th>Manuel</th><th>Fin de période</th><th>Données supprimées le</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($familySubscriptions as $fs): ?>
                <tr>
                    <td><?= htmlspecialchars($fs['family_name']) ?></td>
                    <td><?= htmlspecialchars($fs['plan_name'] ?? '—') ?></td>
                    <td>
                        <?= htmlspecialchars($fs['status']) ?>
                        <?php if (in_array($fs['status'], ['past_due', 'canceled'], true) && $fs['grace_ends_at'] && !$fs['data_purged_at']): ?>
                            <br><small style="color:var(--danger)">Purge le <?= (new DateTime($fs['grace_ends_at']))->format('d/m/Y') ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= $fs['manual'] ? '✅' : '' ?></td>
                    <td><?= $fs['current_period_end'] ? (new DateTime($fs['current_period_end']))->format('d/m/Y') : '—' ?></td>
                    <td><?= $fs['data_purged_at'] ? (new DateTime($fs['data_purged_at']))->format('d/m/Y') : '—' ?></td>
                    <td>
                        <?php if ($fs['manual']): ?>
                        <form method="POST" action="<?= BASE_URL ?>/admin/families/<?= $fs['family_id'] ?>/subscription/revoke"><?= \App\Core\Csrf::field() ?>
                            <button type="submit" class="btn btn-danger btn-sm">Retirer</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($familySubscriptions)): ?>
                <tr><td colspan="7" style="color:var(--text-muted)">Aucun abonnement pour le moment.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <p style="color:var(--text-muted);font-size:.8rem;margin-top:.5rem">Pour attribuer un palier manuellement à une famille (geste commercial, partenariat…), utilisez le formulaire depuis l'onglet « Familles ».</p>
        </div>

        <div class="settings-tab-panel" data-admin-subtab-panel="sub-purges">
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Un instantané des données est conservé avant chaque suppression, consultable ci-dessous en cas de réclamation.
        </p>
        <table class="admin-table">
            <thead><tr><th>Famille</th><th>Modules purgés</th><th>Date</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($premiumDataPurges as $pp): ?>
                <tr>
                    <td><?= htmlspecialchars($pp['family_name']) ?> (#<?= $pp['family_id'] ?>)</td>
                    <td><?= htmlspecialchars($pp['modules_purged']) ?></td>
                    <td><?= (new DateTime($pp['purged_at']))->format('d/m/Y H:i') ?></td>
                    <td><a href="<?= BASE_URL ?>/admin/premium-purges/<?= $pp['id'] ?>/export" target="_blank" class="btn btn-secondary btn-sm">Voir l'instantané</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($premiumDataPurges)): ?>
                <tr><td colspan="4" style="color:var(--text-muted)">Aucune suppression pour le moment.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>

        <div class="settings-tab-panel" data-admin-subtab-panel="sub-urssaf">
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Chiffre d'affaires de <strong>la plateforme elle-même</strong> (paiements Stripe encaissés auprès des
            familles abonnées) — sans rapport avec le module Budget d'une famille. Chaque mois, au jour choisi
            ci-dessous, un PDF détaillant les transactions du mois civil précédent et le total à déclarer est envoyé
            par e-mail à l'adresse admin (<?= $urssafAdminEmail ? htmlspecialchars($urssafAdminEmail) : 'non configurée — onglet Profil admin' ?>),
            avec un rappel d'effectuer la déclaration URSSAF.
        </p>
        <?php if (!$stripeSecretKey): ?>
        <p style="color:var(--danger);font-size:.85rem">Configurez d'abord une clé secrète Stripe (onglet « Clés Stripe ») — sans elle, aucun paiement ne peut être récupéré.</p>
        <?php endif; ?>
        <form method="POST" action="<?= BASE_URL ?>/admin/subscriptions/urssaf" class="card" style="padding:1.25rem;max-width:520px"><?= \App\Core\Csrf::field() ?>
            <div class="form-group">
                <label><input type="checkbox" name="urssaf_report_enabled" value="1" <?= $urssafReportEnabled ? 'checked' : '' ?>> Envoyer automatiquement le rapport mensuel</label>
            </div>
            <div class="form-group">
                <label>Jour du mois d'envoi</label>
                <input type="number" name="urssaf_report_day" min="1" max="28" value="<?= $urssafReportDay ?>">
                <small style="color:var(--text-muted)">Le rapport envoyé ce jour-là couvre le mois civil précédent (le seul complet à cette date).</small>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
        </form>
        <div class="card" style="padding:1.25rem;max-width:520px;margin-top:1rem">
            <p style="margin-top:0">
                Dernier envoi : <?= $urssafReportLastSent ? htmlspecialchars($urssafReportLastSent) : 'jamais' ?>
            </p>
            <form method="POST" action="<?= BASE_URL ?>/admin/subscriptions/urssaf/send-now" onsubmit="return confirmSubmit(this, 'Générer et envoyer maintenant le rapport du mois précédent ?')"><?= \App\Core\Csrf::field() ?>
                <button type="submit" class="btn btn-secondary btn-sm">📧 Envoyer maintenant (mois précédent)</button>
            </form>
        </div>
        </div>

