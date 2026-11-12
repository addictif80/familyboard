        <h2>Contenu des emails (global)</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Le style graphique des emails (couleurs, polices, mise en page) est fixe et identique
            au panneau — seuls le sujet et le message peuvent être personnalisés ici, pour l'ensemble des familles.
        </p>
        <?php foreach ($emailContents as $type => $tpl): ?>
        <div class="card" style="padding:1.25rem;max-width:640px;margin-bottom:1rem">
            <h3 style="margin-top:0;display:flex;align-items:center;gap:.5rem">
                <?= htmlspecialchars($tpl['label']) ?>
                <?php if ($tpl['is_custom']): ?><span class="badge-custom">Personnalisé</span><?php endif; ?>
            </h3>
            <div style="margin-bottom:.75rem;font-size:.78rem;color:var(--text-muted)">
                Variables : <?= implode(', ', array_map(fn($v) => '<code>{{' . $v . '}}</code>', \App\Models\EmailContent::variables($type))) ?>
            </div>
            <form method="POST" action="<?= BASE_URL ?>/admin/email-content"><?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
                <div class="form-group">
                    <label>Sujet</label>
                    <input type="text" name="subject" value="<?= htmlspecialchars($tpl['subject']) ?>">
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="message" rows="5"><?= htmlspecialchars($tpl['message']) ?></textarea>
                </div>
                <div style="display:flex;gap:.5rem;align-items:center">
                    <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                    <a href="<?= BASE_URL ?>/admin/email-content/<?= $type ?>/preview" target="_blank" class="btn btn-secondary btn-sm">👁️ Aperçu</a>
                </div>
            </form>
            <?php if ($tpl['is_custom']): ?>
            <form method="POST" action="<?= BASE_URL ?>/admin/email-content/<?= $type ?>/reset" style="margin-top:.5rem"><?= \App\Core\Csrf::field() ?>
                <button type="submit" class="btn btn-secondary btn-sm">Réinitialiser la valeur par défaut</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

