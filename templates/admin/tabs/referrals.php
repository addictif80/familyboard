        <h2>🤝 Programme de parrainage</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            Chaque famille peut partager son propre lien de parrainage (visible dans ses réglages une fois
            le programme activé ici). Quand une nouvelle famille s'inscrit via ce lien, le parrainage est
            tracé ci-dessous ; si un palier de récompense est configuré, il est offert automatiquement au
            parrain (jours cumulables à chaque nouveau filleul).
        </p>
        <form method="POST" action="<?= BASE_URL ?>/admin/referrals/settings" class="card" style="padding:1.25rem;max-width:640px;margin-bottom:1.5rem"><?= \App\Core\Csrf::field() ?>
            <div class="form-group">
                <label><input type="checkbox" name="referral_enabled" value="1" <?= $referralEnabled ? 'checked' : '' ?>> Activer le programme de parrainage</label>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Palier offert au parrain</label>
                    <select name="referral_reward_plan_id">
                        <option value="">— Aucune récompense automatique (parrainage tracé uniquement) —</option>
                        <?php foreach ($plans as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $referralRewardPlanId === (int)$p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Jours offerts par filleul</label>
                    <input type="number" name="referral_reward_days" min="1" max="365" value="<?= $referralRewardDays ?>">
                </div>
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="public_signup_banner_enabled" value="1" <?= $publicSignupBannerEnabled ? 'checked' : '' ?>> Afficher une bannière FamilyBoard sur les pages publiques (liste de cadeaux, liste de naissance, album partagé)</label>
            </div>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
        </form>

        <h3>Parrainages (<?= count($referrals) ?>)</h3>
        <table class="admin-table">
            <thead><tr><th>Parrain</th><th>Filleul</th><th>Date</th><th>Récompense</th></tr></thead>
            <tbody>
            <?php foreach ($referrals as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['referrer_family_name']) ?></td>
                <td><?= htmlspecialchars($r['referred_family_name']) ?></td>
                <td><?= \App\Core\DateHelper::fromUtc($r['created_at'], 'd/m/Y') ?></td>
                <td><?= $r['reward_granted_at'] ? '✅ Offerte' : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($referrals)): ?>
                <tr><td colspan="4" class="empty-state">Aucun parrainage pour le moment.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

