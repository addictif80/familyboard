<?php
$pageTitle = 'Configurateur familial';
$extraJs = ['onboarding.js'];
ob_start();

$proMembers = array_values(array_filter($members, fn($m) => $m['role'] !== 'coparent'));
?>
<div class="tasks-main" style="width:100%;max-width:760px;margin:0 auto">

    <div class="card settings-section" style="margin-bottom:1rem">
        <h2 style="margin-top:0">🚀 Configurons votre espace</h2>
        <p style="color:var(--text-muted)">Quelques étapes pour démarrer du bon pied — tout est modifiable plus tard depuis les modules eux-mêmes, et vous pouvez passer à tout moment.</p>
        <div id="onboarding-steps-nav" style="display:flex;gap:.4rem;flex-wrap:wrap;margin-top:1rem">
            <button type="button" class="settings-tab-btn active" data-step="children" onclick="goToStep('children')">🧒 Enfants</button>
            <button type="button" class="settings-tab-btn" data-step="members" onclick="goToStep('members')">✉️ Membres</button>
            <button type="button" class="settings-tab-btn" data-step="school" onclick="goToStep('school')">🎓 Scolarité<?= $schoolEntitled ? '' : ' <span class="badge">Premium</span>' ?></button>
            <button type="button" class="settings-tab-btn" data-step="employment" onclick="goToStep('employment')">💼 Activité pro<?= $employmentEntitled ? '' : ' <span class="badge">Premium</span>' ?></button>
            <button type="button" class="settings-tab-btn" data-step="budget" onclick="goToStep('budget')">💰 Budget<?= $budgetEntitled ? '' : ' <span class="badge">Premium</span>' ?></button>
            <button type="button" class="settings-tab-btn" data-step="done" onclick="goToStep('done')">✅ Terminé</button>
        </div>
    </div>

    <!-- Étape : Enfants -->
    <div class="onboarding-step-panel card settings-section active" data-step="children">
        <h3>🧒 Les enfants de la famille</h3>
        <p style="color:var(--text-muted);font-size:.85rem">Créez-les une seule fois ici : ils seront ensuite disponibles dans Scolarité, Garde alternée, Suivi nounou et Bébé, sans avoir à les ressaisir.</p>
        <div id="onboarding-children-list" style="margin-bottom:1rem">
            <?php foreach ($familyChildren as $c): ?>
            <div class="member-item" data-child-id="<?= $c['id'] ?>">
                <div class="member-info">
                    <span class="list-dot" style="background:<?= htmlspecialchars($c['color']) ?>"></span>
                    <strong><?= htmlspecialchars($c['name']) ?></strong>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="form-row">
            <div class="form-group flex-2">
                <label>Prénom</label>
                <input type="text" id="ob-child-name" placeholder="Emma, Lucas…">
            </div>
            <div class="form-group">
                <label>Date de naissance</label>
                <input type="date" id="ob-child-birthdate">
            </div>
            <div class="form-group">
                <label>Couleur</label>
                <input type="color" id="ob-child-color" value="#4A90D9">
            </div>
        </div>
        <button class="btn btn-secondary btn-sm" onclick="addOnboardingChild()">+ Ajouter cet enfant</button>
        <div class="onboarding-step-footer">
            <button class="btn btn-link btn-sm" onclick="finishOnboarding()">Terminer plus tard</button>
            <button class="btn btn-primary btn-sm" onclick="goToStep('members')">Suivant →</button>
        </div>
    </div>

    <!-- Étape : Membres -->
    <div class="onboarding-step-panel card settings-section" data-step="members" style="display:none">
        <h3>✉️ Inviter les autres membres</h3>
        <p style="color:var(--text-muted);font-size:.85rem">Chaque personne invitée reçoit un lien par e-mail pour créer son compte et rejoindre votre famille.</p>
        <div id="onboarding-invites-list" style="margin-bottom:1rem"></div>
        <div class="form-row">
            <div class="form-group flex-2">
                <label>Adresse e-mail</label>
                <input type="email" id="ob-invite-email" placeholder="prenom@exemple.fr">
            </div>
        </div>
        <button class="btn btn-secondary btn-sm" onclick="sendOnboardingInvite()">+ Envoyer l'invitation</button>
        <div class="onboarding-step-footer">
            <button class="btn btn-link btn-sm" onclick="finishOnboarding()">Terminer plus tard</button>
            <button class="btn btn-secondary btn-sm" onclick="goToStep('children')">← Précédent</button>
            <button class="btn btn-primary btn-sm" onclick="goToStep('school')">Suivant →</button>
        </div>
    </div>

    <!-- Étape : Scolarité -->
    <div class="onboarding-step-panel card settings-section" data-step="school" style="display:none">
        <h3>🎓 Scolarité<?= $schoolEntitled ? '' : ' <span class="badge">Module Premium</span>' ?></h3>
        <p style="color:var(--text-muted);font-size:.85rem">Amorcez la fiche scolaire d'un enfant — vous pourrez compléter emploi du temps, notes et absences plus tard depuis le module.</p>
        <?php if (empty($familyChildren)): ?>
            <p class="empty-state">Ajoutez d'abord un enfant à l'étape précédente.</p>
        <?php else: ?>
        <div class="form-row">
            <div class="form-group">
                <label>Enfant</label>
                <select id="ob-school-child">
                    <?php foreach ($familyChildren as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group flex-2">
                <label>École / établissement</label>
                <input type="text" id="ob-school-name">
            </div>
            <div class="form-group">
                <label>Classe</label>
                <input type="text" id="ob-school-class" placeholder="CM2, 6ème…">
            </div>
        </div>
        <button class="btn btn-secondary btn-sm" onclick="addOnboardingSchool()">+ Ajouter la fiche scolaire</button>
        <div id="onboarding-school-list" style="margin-top:1rem"></div>
        <?php endif; ?>
        <div class="onboarding-step-footer">
            <button class="btn btn-link btn-sm" onclick="finishOnboarding()">Terminer plus tard</button>
            <button class="btn btn-secondary btn-sm" onclick="goToStep('members')">← Précédent</button>
            <button class="btn btn-primary btn-sm" onclick="goToStep('employment')">Suivant →</button>
        </div>
    </div>

    <!-- Étape : Activité pro -->
    <div class="onboarding-step-panel card settings-section" data-step="employment" style="display:none">
        <h3>💼 Activité pro<?= $employmentEntitled ? '' : ' <span class="badge">Module Premium</span>' ?></h3>
        <p style="color:var(--text-muted);font-size:.85rem">Amorcez le suivi salarié d'un membre — congés, planning et estimation de paie se complètent ensuite depuis le module.</p>
        <div class="form-row">
            <div class="form-group">
                <label>Membre</label>
                <select id="ob-emp-user">
                    <?php foreach ($proMembers as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group flex-2">
                <label>SIREN de l'employeur (optionnel)</label>
                <div style="display:flex;gap:.4rem">
                    <input type="text" id="ob-emp-siren" placeholder="9 chiffres" maxlength="9">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="lookupOnboardingSiren()">Rechercher</button>
                </div>
                <small id="ob-siren-result"></small>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group flex-2">
                <label>Nom de l'employeur</label>
                <input type="text" id="ob-emp-name">
            </div>
            <div class="form-group flex-2">
                <label>Poste</label>
                <input type="text" id="ob-emp-job">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Rémunération</label>
                <select id="ob-emp-pay-mode" onchange="toggleOnboardingPayMode()">
                    <option value="hourly">Taux horaire</option>
                    <option value="monthly">Salaire mensuel brut</option>
                </select>
            </div>
            <div class="form-group" id="ob-emp-hourly-group">
                <label>Taux horaire (€)</label>
                <input type="text" id="ob-emp-hourly-rate" placeholder="11,88">
            </div>
            <div class="form-group" id="ob-emp-monthly-group" style="display:none">
                <label>Salaire brut mensuel (€)</label>
                <input type="text" id="ob-emp-monthly-gross" placeholder="1800">
            </div>
        </div>
        <button class="btn btn-secondary btn-sm" onclick="addOnboardingEmployment()">+ Créer le profil</button>
        <div id="onboarding-employment-list" style="margin-top:1rem"></div>
        <div class="onboarding-step-footer">
            <button class="btn btn-link btn-sm" onclick="finishOnboarding()">Terminer plus tard</button>
            <button class="btn btn-secondary btn-sm" onclick="goToStep('school')">← Précédent</button>
            <button class="btn btn-primary btn-sm" onclick="goToStep('budget')">Suivant →</button>
        </div>
    </div>

    <!-- Étape : Budget -->
    <div class="onboarding-step-panel card settings-section" data-step="budget" style="display:none">
        <h3>💰 Budget<?= $budgetEntitled ? '' : ' <span class="badge">Module Premium</span>' ?></h3>
        <p style="color:var(--text-muted);font-size:.85rem">Ajoutez vos revenus et charges récurrents (salaire, loyer, abonnements…) — le solde et les catégories se peaufinent ensuite depuis le module.</p>
        <div class="form-row">
            <div class="form-group flex-2">
                <label>Libellé</label>
                <input type="text" id="ob-budget-title" placeholder="Salaire, Loyer, Netflix…">
            </div>
            <div class="form-group">
                <label>Type</label>
                <select id="ob-budget-type">
                    <option value="income">Revenu</option>
                    <option value="expense">Charge</option>
                </select>
            </div>
            <div class="form-group">
                <label>Montant (€)</label>
                <input type="text" id="ob-budget-amount" placeholder="1500">
            </div>
            <div class="form-group">
                <label>Jour du mois</label>
                <input type="number" id="ob-budget-day" min="1" max="28" value="1">
            </div>
        </div>
        <button class="btn btn-secondary btn-sm" onclick="addOnboardingBudget()">+ Ajouter</button>
        <div id="onboarding-budget-list" style="margin-top:1rem"></div>
        <div class="onboarding-step-footer">
            <button class="btn btn-link btn-sm" onclick="finishOnboarding()">Terminer plus tard</button>
            <button class="btn btn-secondary btn-sm" onclick="goToStep('employment')">← Précédent</button>
            <button class="btn btn-primary btn-sm" onclick="goToStep('done')">Suivant →</button>
        </div>
    </div>

    <!-- Étape : Terminé -->
    <div class="onboarding-step-panel card settings-section" data-step="done" style="display:none">
        <h3>✅ Tout est prêt !</h3>
        <p style="color:var(--text-muted)">Votre espace familial est configuré. Vous pouvez continuer à tout ajuster à tout moment depuis les modules et les réglages.</p>
        <div class="onboarding-step-footer">
            <button class="btn btn-secondary btn-sm" onclick="goToStep('budget')">← Précédent</button>
            <button class="btn btn-primary" onclick="finishOnboarding()">Aller au tableau de bord →</button>
        </div>
    </div>

</div>

<style>
.onboarding-step-footer { display:flex; justify-content:flex-end; gap:.5rem; margin-top:1.5rem; padding-top:1rem; border-top:1px solid var(--border); }
.onboarding-step-footer .btn-link { margin-right:auto; }
.btn-link { background:none; border:none; color:var(--text-muted); text-decoration:underline; cursor:pointer; }
</style>

<script>
const BASE_URL = <?= json_encode(BASE_URL) ?>;
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
