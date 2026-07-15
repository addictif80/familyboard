<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> — L'espace privé de votre famille</title>
    <meta name="description" content="Calendrier partagé, mur familial, tâches, budget, garde alternée, suivi bébé... Toute la vie de votre famille réunie dans une application pensée dans le moindre détail.">
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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&display=swap">
</head>
<body>
<div class="landing">

    <nav class="landing-nav">
        <div class="landing-logo">
            <span class="logo-icon">🏠</span>
            <span><?= APP_NAME ?></span>
        </div>
        <div class="landing-nav-actions">
            <button class="theme-toggle-landing" onclick="toggleTheme()" title="Changer de thème" aria-label="Changer de thème">🌓</button>
            <a href="<?= BASE_URL ?>/login" class="btn btn-secondary">Se connecter</a>
            <a href="<?= BASE_URL ?>/register" class="btn btn-primary">Créer ma famille</a>
        </div>
    </nav>

    <header class="hero">
        <span class="hero-eyebrow">✨ Nouveau · pensé pour les familles modernes</span>
        <h1>L'espace privé où <em>toute votre famille</em> s'organise, enfin sans le bazar</h1>
        <p class="hero-subtitle">
            Calendrier, mur de souvenirs, tâches, budget, garde alternée, suivi de bébé...
            FamilyBoard réunit l'essentiel de votre quotidien familial dans une seule application,
            claire, rapide et sans publicité.
        </p>
        <div class="hero-actions">
            <a href="<?= BASE_URL ?>/register" class="btn btn-primary">Créer ma famille</a>
            <a href="<?= BASE_URL ?>/login" class="btn btn-secondary">Se connecter</a>
        </div>
        <div class="hero-trust">
            <span>🔒 Vos données restent privées</span>
            <span>🚫 Aucune publicité</span>
            <span>⚡ Installable comme une app (PWA)</span>
        </div>

        <div class="hero-mockup" aria-hidden="true">
            <div class="hero-mockup-bar"><span></span><span></span><span></span></div>
            <div class="hero-mockup-grid">
                <div class="hero-mockup-cell">
                    <span class="mm-icon">📅</span>
                    <span class="mm-title">Calendrier</span>
                    <span class="mm-line"></span>
                    <span class="mm-line short"></span>
                </div>
                <div class="hero-mockup-cell">
                    <span class="mm-icon">📸</span>
                    <span class="mm-title">Mur familial</span>
                    <span class="mm-line"></span>
                    <span class="mm-line short"></span>
                </div>
                <div class="hero-mockup-cell">
                    <span class="mm-icon">✅</span>
                    <span class="mm-title">Tâches</span>
                    <span class="mm-line"></span>
                    <span class="mm-line short"></span>
                </div>
                <div class="hero-mockup-cell">
                    <span class="mm-icon">💰</span>
                    <span class="mm-title">Budget</span>
                    <span class="mm-line"></span>
                    <span class="mm-line short"></span>
                </div>
                <div class="hero-mockup-cell">
                    <span class="mm-icon">👶</span>
                    <span class="mm-title">Garde alternée</span>
                    <span class="mm-line"></span>
                    <span class="mm-line short"></span>
                </div>
                <div class="hero-mockup-cell">
                    <span class="mm-icon">🍼</span>
                    <span class="mm-title">Suivi bébé</span>
                    <span class="mm-line"></span>
                    <span class="mm-line short"></span>
                </div>
            </div>
        </div>
    </header>

    <?php
    $features = [
        ['id' => 'dashboard', 'icon' => '🏠', 'title' => 'Tableau de bord', 'tagline' => 'Une vue d\'ensemble sur mesure, dès la connexion.', 'points' => [
            'Widgets personnalisables : tâches du jour, prochains événements, derniers messages…',
            'Réorganisation des widgets en glisser-déposer',
            'Accès rapide à tous les modules activés pour votre famille',
        ]],
        ['id' => 'calendar', 'icon' => '📅', 'title' => 'Calendrier partagé', 'tagline' => 'Un agenda commun, sans doublons ni confusion.', 'points' => [
            'Synchronisation CalDAV avec vos calendriers existants (Google, Apple…)',
            'Vue par membre, avec une couleur dédiée à chacun',
            'Événements récurrents, rappels et vacances scolaires intégrées',
        ]],
        ['id' => 'custody', 'icon' => '👶', 'title' => 'Garde alternée', 'badge' => 'Nouveau', 'tagline' => 'Un planning de garde qui s\'adapte à toutes les organisations.', 'points' => [
            'Récurrences automatiques : semaine sur deux, un weekend sur deux…',
            'Nouveau : un weekend sur deux + un jour fixe en semaine (ex. le mercredi)',
            'Périodes de vacances avec répartition dédiée (1 semaine sur 2, semaines paires/impaires…)',
            'Propositions de garde peintes jour par jour pour les cas les plus atypiques',
            'Nouveau : journal d\'activité horodaté (fuseau horaire de la famille) et IP, activé automatiquement dès la première connexion du co-parent invité',
        ]],
        ['id' => 'coparent', 'icon' => '🔒', 'title' => 'Garde partagée', 'badge' => 'Nouveau', 'tagline' => 'Un accès restreint et sécurisé pour l\'autre parent.', 'points' => [
            'Le co-parent ne voit que le planning, le journal, les documents et évènements liés à l\'enfant',
            'Nouveau : envoi de messages vocaux dans le journal parental',
            'Peut proposer des jours de garde directement depuis son accès',
            'Peut créer son propre espace FamilyBoard complet tout en gardant cet accès',
        ]],
        ['id' => 'chat', 'icon' => '💬', 'title' => 'Chat familial', 'badge' => 'Nouveau', 'tagline' => 'Une messagerie privée, rien que pour votre foyer.', 'points' => [
            'Messages instantanés entre tous les membres de la famille',
            'Nouveau : messages vocaux, enregistrés au micro depuis le navigateur',
            'Historique complet, sans publicité ni tiers',
        ]],
        ['id' => 'commlog', 'icon' => '📝', 'title' => 'Journal parental', 'badge' => 'Nouveau', 'tagline' => 'Une trace de communication fiable entre parents.', 'points' => [
            'Messages horodatés, jamais modifiables ni supprimables',
            'Nouveau : messages vocaux, pour un ton plus clair qu\'à l\'écrit',
            'Accusés de lecture pour savoir qui a vu quoi',
        ]],
        ['id' => 'wall', 'icon' => '📸', 'title' => 'Mur familial', 'tagline' => 'Le fil d\'actualité de votre foyer, à l\'abri des regards extérieurs.', 'points' => [
            'Partagez photos, souvenirs et petits mots',
            'Réactions et commentaires entre membres',
            'Aucune donnée revendue, aucune publicité',
        ]],
        ['id' => 'familywall', 'icon' => '📺', 'title' => 'Écran mural', 'tagline' => 'Affichez l\'essentiel de votre organisation sur un écran fixe.', 'points' => [
            'Vue calendrier, tâches et météo en un coup d\'œil',
            'Idéal sur une tablette ou un écran dans la cuisine',
            'Se met à jour automatiquement',
        ]],
        ['id' => 'tasks', 'icon' => '✅', 'title' => 'Tâches & Courses', 'tagline' => 'Listes de tâches et de courses, assignables à chacun.', 'points' => [
            'Corvées, courses et listes partagées entre membres',
            'Assignation par personne, avec priorités et échéances',
            'Synchronisées en temps réel pour toute la famille',
        ]],
        ['id' => 'budget', 'icon' => '💰', 'title' => 'Budget familial', 'tagline' => 'Vos finances de famille, en toute transparence.', 'points' => [
            'Suivi des dépenses et revenus par catégorie',
            'Objectifs d\'épargne et prélèvements récurrents',
            'Graphiques mensuels pour visualiser vos tendances',
        ]],
        ['id' => 'projects', 'icon' => '📋', 'title' => 'Projets', 'tagline' => 'Pilotez vos projets familiaux de A à Z.', 'points' => [
            'Tâches, budget et matériel liés à un même projet (travaux, voyages…)',
            'Suivi des dépenses et des achats',
            'Statuts d\'avancement clairs',
        ]],
        ['id' => 'contacts', 'icon' => '📒', 'title' => 'Répertoire', 'tagline' => 'Tous vos contacts importants au même endroit.', 'points' => [
            'Médecins, école, nounou, famille élargie…',
            'Accessible à tous les membres autorisés',
            'Recherche rapide',
        ]],
        ['id' => 'warranties', 'icon' => '🛡️', 'title' => 'Garanties', 'tagline' => 'Ne perdez plus jamais une preuve d\'achat.', 'points' => [
            'Date d\'achat, durée de garantie et rappel d\'expiration',
            'Photo du ticket ou de la facture jointe',
            'Alerte avant l\'expiration',
        ]],
        ['id' => 'documents', 'icon' => '🗂️', 'title' => 'Documents', 'tagline' => 'Un coffre-fort numérique pour vos papiers importants.', 'points' => [
            'OCR automatique et classement par type',
            'Recherche plein texte instantanée',
            'Partage ciblé pour un enfant en garde alternée',
        ]],
        ['id' => 'cameras', 'icon' => '🎥', 'title' => 'Caméras', 'tagline' => 'Vos caméras IP, réunies dans une seule vue.', 'points' => [
            'Compatible flux MJPEG, snapshot, HLS et RTSP (via go2rtc)',
            'Accès rapide depuis le tableau de bord',
            'Vos identifiants restent privés, jamais partagés à des tiers',
        ]],
        ['id' => 'baby', 'icon' => '🍼', 'title' => 'Bébé & grossesse', 'tagline' => 'Le quotidien de bébé, suivi par les deux parents.', 'points' => [
            'Biberons, sommeil et changes en un geste',
            'Suivi de grossesse partagé',
            'Historique consultable par toute la famille',
        ]],
        ['id' => 'location', 'icon' => '📍', 'title' => 'Position', 'tagline' => 'Savoir que tout le monde est bien arrivé, sans intrusion.', 'points' => [
            'Partage de position volontaire entre membres',
            'Utile pour les trajets école/activités',
            'Désactivable à tout moment',
        ]],
        ['id' => 'emergency', 'icon' => '🆘', 'title' => 'Fiches d\'urgence', 'tagline' => 'Les informations vitales, accessibles en un scan.', 'points' => [
            'Allergies, traitements, contacts d\'urgence',
            'QR code à imprimer pour la nounou ou l\'école',
            'Toujours à jour, jamais périmé dans un tiroir',
        ]],
        ['id' => 'meals', 'icon' => '🍽️', 'title' => 'Repas & recettes', 'tagline' => 'Planifiez la semaine sans y penser.', 'points' => [
            'Planning des repas en glisser-déposer',
            'Recettes transformées en listes de courses en un clic',
            'Idées de repas partagées par toute la famille',
        ]],
    ];
    ?>
    <section class="landing-section">
        <div class="section-heading">
            <span class="kicker">Fonctionnalités</span>
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
        <div class="section-heading">
            <span class="kicker">Notre différence</span>
            <h2>Une application familiale, pas un logiciel d'entreprise</h2>
            <p>Nous avons conçu FamilyBoard comme un produit premium : épuré, rapide, et respectueux de votre vie privée.</p>
        </div>
        <div class="diff-grid">
            <div class="diff-item">
                <div class="diff-mark">✓</div>
                <div>
                    <h4>Un design soigné, pas surchargé</h4>
                    <p>Une interface épurée pensée dans le détail, sans bannières ni pop-ups qui parasitent votre quotidien.</p>
                </div>
            </div>
            <div class="diff-item">
                <div class="diff-mark">✓</div>
                <div>
                    <h4>Zéro publicité, zéro revente de données</h4>
                    <p>FamilyBoard vit de ses abonnements, pas de vos données. Votre vie de famille reste privée.</p>
                </div>
            </div>
            <div class="diff-item">
                <div class="diff-mark">✓</div>
                <div>
                    <h4>Tout au même endroit</h4>
                    <p>Plus besoin de jongler entre cinq applications différentes pour organiser votre foyer.</p>
                </div>
            </div>
            <div class="diff-item">
                <div class="diff-mark">✓</div>
                <div>
                    <h4>Installable comme une app native</h4>
                    <p>Ajoutez FamilyBoard à votre écran d'accueil et recevez des notifications, sans passer par un store.</p>
                </div>
            </div>
            <div class="diff-item">
                <div class="diff-mark">✓</div>
                <div>
                    <h4>Mode sombre & clair</h4>
                    <p>Une expérience agréable à toute heure de la journée, sur mobile comme sur ordinateur.</p>
                </div>
            </div>
            <div class="diff-item">
                <div class="diff-mark">✓</div>
                <div>
                    <h4>Un compte, toute la famille</h4>
                    <p>Invitez vos proches par simple code et gérez les accès de chacun depuis les réglages.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="cta-section">
        <h2>Prêt à simplifier la vie de votre famille ?</h2>
        <p>Créez votre espace en moins d'une minute, invitez vos proches, et retrouvez enfin votre organisation au même endroit.</p>
        <div class="hero-actions">
            <a href="<?= BASE_URL ?>/register" class="btn btn-primary">Créer ma famille</a>
            <a href="<?= BASE_URL ?>/login" class="btn btn-secondary">Se connecter</a>
        </div>
    </section>

    <footer class="landing-footer">
        <p>&copy; <?= date('Y') ?> <?= APP_NAME ?> — <a href="<?= BASE_URL ?>/login">Se connecter</a> · <a href="<?= BASE_URL ?>/register">Créer ma famille</a></p>
    </footer>
</div>
<script src="<?= ASSETS_URL ?>/js/app.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>
