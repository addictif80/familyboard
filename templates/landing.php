<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> — Toute votre famille, un seul endroit vivant</title>
    <meta name="description" content="Calendrier partagé, tâches, documents, garde alternée, budget, santé… FamilyBoard organise le quotidien de toute la famille, en toute simplicité.">
    <script>
    (function () {
        try {
            var stored = localStorage.getItem('fb-theme');
            var theme = stored || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', theme);
        } catch (e) {}
    })();
    </script>
    <link rel="icon" href="<?= BASE_URL ?>/public/icons/icon.svg" type="image/svg+xml">
    <link rel="icon" href="<?= BASE_URL ?>/public/icons/icon-192.png" type="image/png">
    <link rel="manifest" href="<?= BASE_URL ?>/public/manifest.json">
    <meta name="theme-color" content="#232A3D">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/app.css?v=<?= APP_VERSION ?>">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/landing.css?v=<?= APP_VERSION ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Baloo+2:wght@500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap">
</head>
<body>
<div class="landing">

    <nav class="landing-nav">
        <div class="landing-logo">
            <span class="logo-icon">🏠</span>
            <span><?= APP_NAME ?></span>
        </div>
        <div class="landing-nav-actions">
            <a href="#features-detail" class="landing-nav-link">Fonctionnalités</a>
            <?php if (!empty($pricingPlans)): ?><a href="#tarifs" class="landing-nav-link">Tarifs</a><?php endif; ?>
            <a href="<?= BASE_URL ?>/faq" class="landing-nav-link">FAQ</a>
            <a href="<?= BASE_URL ?>/login" class="landing-nav-link">Connexion</a>
            <button class="theme-toggle-landing" onclick="toggleTheme()" title="Changer de thème" aria-label="Changer de thème">🌓</button>
            <a href="<?= BASE_URL ?>/register" class="btn btn-primary">Essayer gratuitement</a>
        </div>
    </nav>

    <header class="hero">
        <div class="hero-grid">
        <div class="hero-copy">
            <span class="hero-eyebrow">✨ Nouveau : Assistant IA &amp; Traitement médical</span>
            <h1>Toute votre famille,<br><em>un seul endroit vivant.</em></h1>
            <p class="hero-subtitle">
                Calendrier partagé, tâches, documents, garde alternée, budget, santé…
                FamilyBoard organise le quotidien de toute la famille, en toute simplicité.
            </p>
            <div class="hero-actions">
                <a href="<?= BASE_URL ?>/register" class="btn btn-primary">Créer ma famille — c'est gratuit</a>
                <a href="<?= BASE_URL ?>/login" class="btn btn-secondary">Se connecter</a>
            </div>
            <div class="hero-trust">
                <span>🔒 Hébergement privé</span>
                <span>🇫🇷 Conçu en France</span>
                <span>👨‍👩‍👧‍👦 Pensé pour les familles</span>
            </div>
        </div>

        <div class="hero-mockup-wrap">
        <div class="hero-mockup" aria-hidden="true">
            <div class="hero-mockup-inner">
                <div class="hero-mockup-bar">
                    <strong>Bonjour Camille 👋</strong>
                    <span class="hero-mockup-avatar"></span>
                </div>
                <div class="hero-mockup-stats">
                    <div class="hero-mockup-stat"><span>TÂCHES</span><strong>3</strong></div>
                    <div class="hero-mockup-stat"><span>BUDGET</span><strong>68%</strong></div>
                </div>
                <div class="hero-mockup-agenda">
                    <div class="hero-mockup-ev"><span style="background:var(--accent)"></span>16h30 · École Léo</div>
                    <div class="hero-mockup-ev"><span style="background:var(--success)"></span>14h00 · Pédiatre</div>
                </div>
            </div>
        </div>
        </div>
        </div>
    </header>

    <section class="landing-section overview-section">
        <h2 class="overview-title">Tout ce qu'il faut, rien de superflu</h2>
        <p class="overview-sub">Organisé par thème, pour que chaque membre trouve ce qu'il cherche en un coup d'œil.</p>
        <div class="overview-grid">
            <a href="#features-detail" class="overview-tile">
                <span class="overview-icon">📅</span>
                <h3>Quotidien</h3>
                <p>Calendrier partagé, tâches, courses, chat familial — tout le monde synchronisé.</p>
            </a>
            <a href="#features-detail" class="overview-tile">
                <span class="overview-icon">👨‍👩‍👧</span>
                <h3>Garde partagée</h3>
                <p>Planning de garde, journal parental et espace co-parent, en toute sérénité.</p>
            </a>
            <a href="#features-detail" class="overview-tile">
                <span class="overview-icon">💰</span>
                <h3>Finances</h3>
                <p>Budget familial, bons plans, additions partagées pour les sorties en groupe.</p>
            </a>
            <a href="#features-detail" class="overview-tile">
                <span class="overview-icon">🏥</span>
                <h3>Santé &amp; sécurité</h3>
                <p>Suivi santé, rappels de traitement, fiches d'urgence, position en temps réel.</p>
            </a>
            <a href="#features-detail" class="overview-tile">
                <span class="overview-icon">🗂️</span>
                <h3>Juridique &amp; administratif</h3>
                <p>Documents, garanties, coffre-fort numérique, courriers — tout centralisé et sécurisé.</p>
            </a>
            <a href="#features-detail" class="overview-tile">
                <span class="overview-icon">🤖</span>
                <h3>Assistant IA</h3>
                <p>Posez une question, créez une tâche ou un événement à la voix ou à l'écrit.</p>
            </a>
        </div>
    </section>

    <?php
    // Contenu partagé avec les info-bulles d'aide "Comment ça marche" affichées dans
    // l'application (voir App\Core\ModuleGuide) — une seule source de vérité.
    $features = [];
    foreach (\App\Core\ModuleGuide::all() as $gid => $g) {
        if ($gid === 'global') continue;
        $features[] = array_merge(['id' => $gid], $g);
    }
    unset($gid, $g);
    ?>
    <section class="landing-section" id="features-detail">
        <div class="section-heading reveal">
            <span class="kicker">Le détail, module par module</span>
            <h2>Tout ce dont votre famille a besoin, réuni au même endroit</h2>
            <p>Chaque module est pensé pour être utile dès la première minute, sans réglages compliqués. Cliquez sur un module pour en voir le détail.</p>
        </div>
        <div class="feature-tabs">
            <?php foreach ($features as $i => $f): ?>
                <input type="radio" name="feature-tab" id="ft-<?= $f['id'] ?>" class="feature-tab-radio" <?= $i === 0 ? 'checked' : '' ?>>
            <?php endforeach; ?>

            <div class="feature-tab-nav">
                <?php foreach ($features as $f): ?>
                    <label for="ft-<?= $f['id'] ?>" class="feature-tab-btn feature-tab-btn-<?= $f['id'] ?>">
                        <span class="feature-tab-icon"><?= $f['icon'] ?></span>
                        <span class="feature-tab-label"><?= htmlspecialchars($f['title']) ?></span>
                        <?php if (!empty($f['badge'])): ?><span class="feature-tab-badge"><?= htmlspecialchars($f['badge']) ?></span><?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="feature-tab-panels">
                <?php foreach ($features as $f): ?>
                    <article class="feature-tab-panel feature-tab-panel-<?= $f['id'] ?>" id="ftp-<?= $f['id'] ?>">
                        <div class="feature-tab-watermark" aria-hidden="true"><?= $f['icon'] ?></div>
                        <div class="feature-tab-panel-icon"><?= $f['icon'] ?></div>
                        <h3><?= htmlspecialchars($f['title']) ?><?php if (!empty($f['badge'])): ?> <span class="feature-tab-badge-inline"><?= htmlspecialchars($f['badge']) ?></span><?php endif; ?></h3>
                        <p class="feature-tab-tagline"><?= htmlspecialchars($f['tagline']) ?></p>
                        <ul class="feature-tab-points">
                            <?php foreach ($f['points'] as $p): ?><li><?= htmlspecialchars($p) ?></li><?php endforeach; ?>
                        </ul>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <style>
        /* Règles générées : un radio "coché" affiche son panneau et surligne son onglet. */
        <?php foreach ($features as $f): ?>
        #ft-<?= $f['id'] ?>:checked ~ .feature-tab-nav .feature-tab-btn-<?= $f['id'] ?> { background: var(--card-bg); color: var(--text); border-color: var(--border); }
        #ft-<?= $f['id'] ?>:checked ~ .feature-tab-nav .feature-tab-btn-<?= $f['id'] ?> .feature-tab-icon { background: linear-gradient(145deg, var(--primary-light), var(--primary)); }
        #ft-<?= $f['id'] ?>:checked ~ .feature-tab-panels .feature-tab-panel-<?= $f['id'] ?> { display: block; }
        <?php endforeach; ?>
    </style>

    <section class="landing-section diff-section">
        <div class="section-heading reveal">
            <span class="kicker">Notre différence</span>
            <h2>Ce que les autres applications familiales ne font pas</h2>
            <p>Cozi, FamilyWall, OurHome… la plupart couvrent le calendrier et les tâches. FamilyBoard va bien au-delà, avec des modules pensés pour les situations réelles des familles d'aujourd'hui — recomposées, en litige, avec des enfants scolarisés.</p>
        </div>
        <div class="diff-grid">
            <div class="diff-item reveal">
                <div class="diff-mark">✓</div>
                <div>
                    <h4>La garde alternée la plus flexible du marché</h4>
                    <p>Semaine sur deux, un weekend sur deux + un jour fixe, périodes de vacances avec répartition dédiée, ou peinture jour par jour pour les cas atypiques — avec un journal d'activité horodaté et infalsifiable entre les deux parents, ce qu'aucun concurrent grand public ne propose.</p>
                </div>
            </div>
            <div class="diff-item reveal">
                <div class="diff-mark">✓</div>
                <div>
                    <h4>Un accès co-parent réellement cloisonné</h4>
                    <p>L'autre parent ne voit que ce qui concerne l'enfant partagé — planning, journal, documents liés — jamais le reste de votre vie de famille. Une nuance que les applications de garde partagée classiques ne font pas.</p>
                </div>
            </div>
            <div class="diff-item reveal">
                <div class="diff-mark">✓</div>
                <div>
                    <h4>Des dossiers de litige, une première</h4>
                    <p>Historique d'échanges horodaté, preuves centralisées et lien de partage sécurisé vers un tiers (avocat, médiateur, assurance) — une fonction qu'aucune application familiale grand public n'offre aujourd'hui.</p>
                </div>
            </div>
            <div class="diff-item reveal">
                <div class="diff-mark">✓</div>
                <div>
                    <h4>Un suivi scolaire connecté aux comptes de la famille</h4>
                    <p>Notes, absences et bulletins par enfant, avec la possibilité de lier la fiche d'un enfant au compte d'un membre ou d'un co-parent pour un accès en lecture seule — sans jamais lui donner la main sur les données des autres enfants.</p>
                </div>
            </div>
            <div class="diff-item reveal">
                <div class="diff-mark">✓</div>
                <div>
                    <h4>Un vrai réseau social privé, pas juste un fil d'actualité</h4>
                    <p>Abonnements mutuels, messages privés, et partage possible avec une famille amie si la relation est acceptée des deux côtés — la convivialité d'un réseau social, sans qu'un inconnu ou un annonceur n'y ait jamais accès.</p>
                </div>
            </div>
            <div class="diff-item reveal">
                <div class="diff-mark">✓</div>
                <div>
                    <h4>Zéro publicité tierce, zéro revente de données</h4>
                    <p>FamilyBoard vit de ses abonnements, pas de vos données. Aucune régie publicitaire externe, aucun tracking. Vous pouvez occasionnellement voir un encart présentant un autre service d'ABHD, l'éditeur de FamilyBoard.</p>
                </div>
            </div>
            <div class="diff-item reveal">
                <div class="diff-mark">✓</div>
                <div>
                    <h4>Tout au même endroit</h4>
                    <p>Calendrier, budget, projets, garanties, courriers, additions entre proches… plus besoin de jongler entre une dizaine d'applications différentes pour organiser votre foyer.</p>
                </div>
            </div>
            <div class="diff-item reveal">
                <div class="diff-mark">✓</div>
                <div>
                    <h4>Un design soigné, pas surchargé</h4>
                    <p>Une interface épurée pensée dans le détail, sans pop-ups qui parasitent votre quotidien.</p>
                </div>
            </div>
            <div class="diff-item reveal">
                <div class="diff-mark">✓</div>
                <div>
                    <h4>Installable comme une app native</h4>
                    <p>Ajoutez FamilyBoard à votre écran d'accueil et recevez des notifications, sans passer par un store.</p>
                </div>
            </div>
            <div class="diff-item reveal">
                <div class="diff-mark">✓</div>
                <div>
                    <h4>Mode sombre & clair</h4>
                    <p>Une expérience agréable à toute heure de la journée, sur mobile comme sur ordinateur.</p>
                </div>
            </div>
            <div class="diff-item reveal">
                <div class="diff-mark">✓</div>
                <div>
                    <h4>Un compte, toute la famille</h4>
                    <p>Invitez vos proches par simple code et gérez les accès de chacun — y compris un accès co-parent restreint — depuis les réglages.</p>
                </div>
            </div>
        </div>
    </section>

    <?php if (!empty($testimonials)): ?>
    <section class="landing-section">
        <div class="section-heading reveal">
            <span class="kicker">Témoignages</span>
            <h2>Ce qu'en disent les familles</h2>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1.25rem;max-width:1100px;margin:0 auto">
            <?php foreach ($testimonials as $t): ?>
            <div class="card reveal" style="padding:1.25rem;display:flex;flex-direction:column;gap:.5rem">
                <?php if ($t['rating']): ?><div><?= str_repeat('⭐', (int)$t['rating']) ?></div><?php endif; ?>
                <p style="margin:0;font-style:italic">« <?= htmlspecialchars($t['content']) ?> »</p>
                <div style="color:var(--text-muted);font-size:.85rem;margin-top:auto">
                    <strong><?= htmlspecialchars($t['author_name']) ?></strong><?= $t['author_role'] ? ' — ' . htmlspecialchars($t['author_role']) : '' ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($pricingPlans)): ?>
    <?php $highlightIdx = intdiv(count($pricingPlans), 2); ?>
    <section class="landing-section pricing-section" id="tarifs">
        <div class="section-heading reveal">
            <span class="kicker">Tarifs</span>
            <h2>Un tarif pour chaque famille</h2>
            <p>Aucune carte requise pour commencer. Passez à un abonnement payant quand votre famille est prête, avec <?= $trialDays ?> jours d'essai gratuit.</p>
        </div>
        <div class="pricing-grid">
            <div class="pricing-card reveal">
                <h3>Gratuit</h3>
                <div class="pricing-amount">0 €</div>
                <p class="pricing-desc">Mur, calendrier, tâches, chat, répertoire et anniversaires — sans limite de temps.</p>
                <a href="<?= BASE_URL ?>/register" class="btn btn-secondary pricing-cta">Commencer</a>
            </div>
            <?php foreach ($pricingPlans as $i => $p): ?>
            <div class="pricing-card reveal <?= $i === $highlightIdx ? 'pricing-card-featured' : '' ?>">
                <?php if ($i === $highlightIdx): ?><span class="pricing-badge">LE PLUS CHOISI</span><?php endif; ?>
                <h3><?= htmlspecialchars($p['name']) ?></h3>
                <div class="pricing-amount"><?= number_format($p['price_monthly_cents'] / 100, 2, ',', ' ') ?> €<span>/mois</span></div>
                <p class="pricing-desc">
                    <?= $p['member_limit'] ? "Jusqu'à {$p['member_limit']} membres" : 'Membres illimités' ?> · tous les modules<br>
                    <?= number_format($p['price_yearly_cents'] / 100, 2, ',', ' ') ?> € / an<?= $annualDiscount > 0 ? " (-{$annualDiscount}%)" : '' ?>
                </p>
                <a href="<?= BASE_URL ?>/register" class="btn <?= $i === $highlightIdx ? 'btn-primary' : 'btn-secondary' ?> pricing-cta">Essayer <?= $trialDays ?> jours gratuits</a>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="pricing-footnote">Stockage additionnel dès 1,99€/mois · Nextcloud partagé par famille</p>
    </section>
    <?php endif; ?>

    <section class="cta-section">
        <h2>Prêt à organiser votre famille ?</h2>
        <p>Créez votre espace en moins de 2 minutes. Aucune carte bancaire requise.</p>
        <div class="hero-actions">
            <a href="<?= BASE_URL ?>/register" class="btn btn-cta-final">Créer ma famille gratuitement</a>
        </div>
    </section>

    <footer class="landing-footer">
        <p>&copy; <?= date('Y') ?> <?= APP_NAME ?> — <a href="<?= BASE_URL ?>/login">Se connecter</a> · <a href="<?= BASE_URL ?>/register">Créer ma famille</a></p>
        <p><a href="<?= BASE_URL ?>/faq">FAQ</a> · <a href="<?= BASE_URL ?>/confidentialite">Confidentialité</a> · <a href="<?= BASE_URL ?>/cgu">CGU</a></p>
    </footer>
</div>
<script src="<?= ASSETS_URL ?>/js/app.js?v=<?= APP_VERSION ?>"></script>
<script>
(function () {
    var els = document.querySelectorAll('.reveal');
    if (!('IntersectionObserver' in window) || !els.length) {
        els.forEach(function (el) { el.classList.add('is-visible'); });
        return;
    }
    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
    els.forEach(function (el) { observer.observe(el); });
})();
</script>
</body>
</html>
