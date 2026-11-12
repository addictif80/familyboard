        <h2>Contenu légal</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Politique de confidentialité et conditions générales d'utilisation, affichées sur
            <code><?= BASE_URL ?>/confidentialite</code> et <code><?= BASE_URL ?>/cgu</code> —
            accessibles sans compte, et liées depuis le formulaire d'inscription. Un texte par
            défaut est fourni ; personnalisez-le pour refléter votre situation réelle (identité
            du responsable de traitement, éventuels sous-traitants supplémentaires…). Texte brut
            uniquement (pas de HTML) : une ligne vide sépare deux paragraphes, une ligne
            commençant par « 1. », « 2. »… devient un titre de section.
        </p>
        <div class="card" style="padding:1.25rem;max-width:760px;margin-bottom:1rem">
            <h3 style="margin-top:0;display:flex;align-items:center;gap:.5rem">
                Politique de confidentialité
                <?php if ($legalPrivacyIsCustom): ?><span class="badge-custom">Personnalisé</span><?php endif; ?>
            </h3>
            <form method="POST" action="<?= BASE_URL ?>/admin/legal"><?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="type" value="privacy">
                <div class="form-group">
                    <textarea name="content" rows="16" style="font-family:monospace;font-size:.82rem"><?= htmlspecialchars($legalPrivacy) ?></textarea>
                </div>
                <div style="display:flex;gap:.5rem;align-items:center">
                    <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                    <a href="<?= BASE_URL ?>/confidentialite" target="_blank" class="btn btn-secondary btn-sm">👁️ Aperçu</a>
                </div>
            </form>
            <?php if ($legalPrivacyIsCustom): ?>
            <form method="POST" action="<?= BASE_URL ?>/admin/legal/privacy/reset" style="margin-top:.5rem"><?= \App\Core\Csrf::field() ?>
                <button type="submit" class="btn btn-secondary btn-sm">Réinitialiser la valeur par défaut</button>
            </form>
            <?php endif; ?>
        </div>
        <div class="card" style="padding:1.25rem;max-width:760px;margin-bottom:1rem">
            <h3 style="margin-top:0;display:flex;align-items:center;gap:.5rem">
                Conditions générales d'utilisation
                <?php if ($legalTermsIsCustom): ?><span class="badge-custom">Personnalisé</span><?php endif; ?>
            </h3>
            <form method="POST" action="<?= BASE_URL ?>/admin/legal"><?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="type" value="terms">
                <div class="form-group">
                    <textarea name="content" rows="16" style="font-family:monospace;font-size:.82rem"><?= htmlspecialchars($legalTerms) ?></textarea>
                </div>
                <div style="display:flex;gap:.5rem;align-items:center">
                    <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                    <a href="<?= BASE_URL ?>/cgu" target="_blank" class="btn btn-secondary btn-sm">👁️ Aperçu</a>
                </div>
            </form>
            <?php if ($legalTermsIsCustom): ?>
            <form method="POST" action="<?= BASE_URL ?>/admin/legal/terms/reset" style="margin-top:.5rem"><?= \App\Core\Csrf::field() ?>
                <button type="submit" class="btn btn-secondary btn-sm">Réinitialiser la valeur par défaut</button>
            </form>
            <?php endif; ?>
        </div>

