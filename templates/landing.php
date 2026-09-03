<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> — L'espace privé de votre famille</title>
    <meta name="description" content="Calendrier partagé, mur familial, tâches, budget, garde alternée avec journal légal, suivi scolaire, dossiers de litige, suivi bébé... Toute la vie de votre famille réunie dans une application pensée dans le moindre détail.">
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
            <a href="<?= BASE_URL ?>/faq" class="landing-nav-link">FAQ</a>
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
            claire et rapide.
        </p>
        <div class="hero-actions">
            <a href="<?= BASE_URL ?>/register" class="btn btn-primary">Créer ma famille</a>
            <a href="<?= BASE_URL ?>/login" class="btn btn-secondary">Se connecter</a>
        </div>
        <div class="hero-trust">
            <span>🔒 Vos données restent privées</span>
            <span>🚫 Aucune publicité tierce, aucune revente de données</span>
            <span>⚡ Installable comme une app (PWA)</span>
        </div>

        <div class="hero-mockup-wrap">
        <div class="hero-mockup" aria-hidden="true">
            <div class="hero-mockup-bar">
                <span></span><span></span><span></span>
                <strong class="hero-mockup-bar-title">FamilyBoard</strong>
            </div>
            <div class="hero-mockup-grid">
                <div class="hero-mockup-cell">
                    <div class="mm-head"><span class="mm-icon" style="--mm-a:#6BA6E8;--mm-b:#3D6BAF">📅</span><span class="mm-title">Calendrier</span></div>
                    <div class="mm-cal">
                        <span></span><span></span><span class="mm-on mm-c1"></span><span></span>
                        <span class="mm-on mm-c2"></span><span></span><span></span>
                    </div>
                </div>
                <div class="hero-mockup-cell">
                    <div class="mm-head"><span class="mm-icon" style="--mm-a:#F5A9C1;--mm-b:#D65B84">📸</span><span class="mm-title">Mur familial</span></div>
                    <div class="mm-photos">
                        <span style="background:linear-gradient(135deg,#F7C873,#E67E22)"></span>
                        <span style="background:linear-gradient(135deg,#8FD3C6,#1E9C7A)"></span>
                        <span style="background:linear-gradient(135deg,#B9A6F0,#7B5FE0)"></span>
                    </div>
                </div>
                <div class="hero-mockup-cell">
                    <div class="mm-head"><span class="mm-icon" style="--mm-a:#7BD79A;--mm-b:#2FA968">✅</span><span class="mm-title">Tâches</span></div>
                    <div class="mm-tasks">
                        <span class="mm-task done"></span>
                        <span class="mm-task done"></span>
                        <span class="mm-task"></span>
                    </div>
                </div>
                <div class="hero-mockup-cell">
                    <div class="mm-head"><span class="mm-icon" style="--mm-a:#F0C267;--mm-b:#D69A2E">💰</span><span class="mm-title">Budget</span></div>
                    <div class="mm-bars">
                        <span style="height:38%"></span><span style="height:72%"></span><span style="height:52%"></span><span style="height:88%"></span><span style="height:60%"></span>
                    </div>
                </div>
                <div class="hero-mockup-cell">
                    <div class="mm-head"><span class="mm-icon" style="--mm-a:#8FB8E8;--mm-b:#4A7FC7">👶</span><span class="mm-title">Garde alternée</span></div>
                    <div class="mm-custody">
                        <span class="mm-avatar" style="background:#4A90D9"></span>
                        <span class="mm-custody-swap">⇄</span>
                        <span class="mm-avatar" style="background:#E67E22"></span>
                    </div>
                </div>
                <div class="hero-mockup-cell">
                    <div class="mm-head"><span class="mm-icon" style="--mm-a:#F0A6B8;--mm-b:#D6667F">🍼</span><span class="mm-title">Suivi bébé</span></div>
                    <div class="mm-baby">
                        <span class="mm-baby-stat">🍼 <b>4</b></span>
                        <span class="mm-baby-stat">💤 <b>7h20</b></span>
                    </div>
                </div>
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
            'Historique complet, aucun accès par un tiers',
        ]],
        ['id' => 'commlog', 'icon' => '📝', 'title' => 'Journal parental', 'badge' => 'Nouveau', 'tagline' => 'Une trace de communication fiable entre parents.', 'points' => [
            'Messages horodatés, jamais modifiables ni supprimables',
            'Nouveau : messages vocaux, pour un ton plus clair qu\'à l\'écrit',
            'Accusés de lecture pour savoir qui a vu quoi',
        ]],
        ['id' => 'wall', 'icon' => '📸', 'title' => 'Mur familial', 'badge' => 'Nouveau', 'tagline' => 'Votre propre réseau social, rien que pour la famille.', 'points' => [
            'Publications personnelles visibles par vos abonnés, ou au nom de la famille (validées par un admin)',
            'Abonnements mutuels validés par la personne suivie, messages privés une fois l\'abonnement accepté',
            'Partage possible avec une famille amie, uniquement si la relation est acceptée des deux côtés',
            'Partagez vos photos d\'album directement sur le mur',
        ]],
        ['id' => 'albums', 'icon' => '🖼️', 'title' => 'Albums photo', 'tagline' => 'Vos souvenirs de famille, organisés et jamais perdus.', 'points' => [
            'Albums thématiques partagés par toute la famille',
            'Lien public en lecture seule ou en dépôt (pour recevoir les photos d\'un événement)',
            'Publication directe d\'une photo d\'album sur le mur familial',
        ]],
        ['id' => 'wishlist', 'icon' => '🎁', 'title' => 'Liste de cadeaux', 'badge' => 'Nouveau', 'tagline' => 'Fini les cadeaux en double — la surprise reste intacte.', 'points' => [
            'Chacun note ce qui lui ferait plaisir',
            'Réservation secrète : invisible pour la personne concernée',
            'Suivi des cadeaux déjà achetés par la famille',
        ]],
        ['id' => 'polls', 'icon' => '🗳️', 'title' => 'Sondages familiaux', 'badge' => 'Nouveau', 'tagline' => 'Décidez ensemble, en quelques secondes.', 'points' => [
            'Question à choix multiple, résultats en temps réel',
            'Idéal pour les petites décisions du quotidien',
            'Clôturable par son auteur ou un administrateur',
        ]],
        ['id' => 'birthdays', 'icon' => '🎂', 'title' => 'Anniversaires', 'badge' => 'Nouveau', 'tagline' => 'Plus aucun anniversaire oublié.', 'points' => [
            'Détection automatique depuis les membres, enfants et contacts',
            'Compte à rebours sur le tableau de bord',
            'Rappel par e-mail 7 jours avant',
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
        ['id' => 'links', 'icon' => '🔗', 'title' => 'Portail de liens', 'badge' => 'Nouveau', 'tagline' => 'Tous vos liens utiles, réunis dans de belles cartes.', 'points' => [
            'Aperçu automatique du site, titre et nombre de clics',
            'Propositions des membres soumises à validation',
            'Liens dédiés visibles pour un accès co-parent',
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
            'Toute une semaine de recettes ajoutée à la liste de courses en un clic, sans doublon',
            'Idées de repas partagées par toute la famille',
        ]],
        ['id' => 'additions', 'icon' => '🧾', 'title' => 'Additions', 'badge' => 'Nouveau', 'tagline' => 'Partagez une dépense sans sortir la calculette.', 'points' => [
            'Répartition entre membres de la famille, invités inclus via un espace dédié',
            'Chaque participant accepte, refuse ou règle sa part',
            'Suivi en temps réel de qui a payé quoi',
        ]],
        ['id' => 'letters', 'icon' => '✉️', 'title' => 'Courriers', 'badge' => 'Nouveau', 'tagline' => 'Rédigez un courrier officiel en quelques minutes.', 'points' => [
            'Modèles réutilisables et variables (destinataire, civilité, société…)',
            'Aperçu impression / export PDF prêt à envoyer',
            'Bibliothèque de modèles propre à votre famille',
        ]],
        ['id' => 'disputes', 'icon' => '⚖️', 'title' => 'Dossiers de litige', 'badge' => 'Nouveau', 'tagline' => 'Gardez une trace solide de chaque litige, du premier au dernier échange.', 'points' => [
            'Historique chronologique des échanges avec l\'autre partie',
            'Documents justificatifs centralisés (preuves, courriers, factures…)',
            'Lien de partage sécurisé pour transmettre le dossier à un tiers (avocat, médiateur…), sans jamais exposer le journal interne',
        ]],
        ['id' => 'school', 'icon' => '🎓', 'title' => 'Suivi scolaire', 'badge' => 'Nouveau', 'tagline' => 'Emploi du temps, notes, absences et bulletins, pour chaque enfant.', 'points' => [
            'Emploi du temps, matières, professeurs et notes avec moyennes calculées automatiquement',
            'Absences justifiées ou non, activités extra-scolaires et bulletins numérisés',
            'Nouveau : liez la fiche d\'un enfant au compte d\'un membre ou d\'un co-parent pour lui donner un accès en lecture seule à ses notes, absences et bulletins',
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
        <div class="section-heading">
            <span class="kicker">Notre différence</span>
            <h2>Ce que les autres applications familiales ne font pas</h2>
            <p>Cozi, FamilyWall, OurHome… la plupart couvrent le calendrier et les tâches. FamilyBoard va bien au-delà, avec des modules pensés pour les situations réelles des familles d'aujourd'hui — recomposées, en litige, avec des enfants scolarisés.</p>
        </div>
        <div class="diff-grid">
            <div class="diff-item">
                <div class="diff-mark">✓</div>
                <div>
                    <h4>La garde alternée la plus flexible du marché</h4>
                    <p>Semaine sur deux, un weekend sur deux + un jour fixe, périodes de vacances avec répartition dédiée, ou peinture jour par jour pour les cas atypiques — avec un journal d'activité horodaté et infalsifiable entre les deux parents, ce qu'aucun concurrent grand public ne propose.</p>
                </div>
            </div>
            <div class="diff-item">
                <div class="diff-mark">✓</div>
                <div>
                    <h4>Un accès co-parent réellement cloisonné</h4>
                    <p>L'autre parent ne voit que ce qui concerne l'enfant partagé — planning, journal, documents liés — jamais le reste de votre vie de famille. Une nuance que les applications de garde partagée classiques ne font pas.</p>
                </div>
            </div>
            <div class="diff-item">
                <div class="diff-mark">✓</div>
                <div>
                    <h4>Des dossiers de litige, une première</h4>
                    <p>Historique d'échanges horodaté, preuves centralisées et lien de partage sécurisé vers un tiers (avocat, médiateur, assurance) — une fonction qu'aucune application familiale grand public n'offre aujourd'hui.</p>
                </div>
            </div>
            <div class="diff-item">
                <div class="diff-mark">✓</div>
                <div>
                    <h4>Un suivi scolaire connecté aux comptes de la famille</h4>
                    <p>Notes, absences et bulletins par enfant, avec la possibilité de lier la fiche d'un enfant au compte d'un membre ou d'un co-parent pour un accès en lecture seule — sans jamais lui donner la main sur les données des autres enfants.</p>
                </div>
            </div>
            <div class="diff-item">
                <div class="diff-mark">✓</div>
                <div>
                    <h4>Un vrai réseau social privé, pas juste un fil d'actualité</h4>
                    <p>Abonnements mutuels, messages privés, et partage possible avec une famille amie si la relation est acceptée des deux côtés — la convivialité d'un réseau social, sans qu'un inconnu ou un annonceur n'y ait jamais accès.</p>
                </div>
            </div>
            <div class="diff-item">
                <div class="diff-mark">✓</div>
                <div>
                    <h4>Zéro publicité tierce, zéro revente de données</h4>
                    <p>FamilyBoard vit de ses abonnements, pas de vos données. Aucune régie publicitaire externe, aucun tracking. Vous pouvez occasionnellement voir un encart présentant un autre service d'ABHD, l'éditeur de FamilyBoard.</p>
                </div>
            </div>
            <div class="diff-item">
                <div class="diff-mark">✓</div>
                <div>
                    <h4>Tout au même endroit</h4>
                    <p>Calendrier, budget, projets, garanties, courriers, additions entre proches… plus besoin de jongler entre une dizaine d'applications différentes pour organiser votre foyer.</p>
                </div>
            </div>
            <div class="diff-item">
                <div class="diff-mark">✓</div>
                <div>
                    <h4>Un design soigné, pas surchargé</h4>
                    <p>Une interface épurée pensée dans le détail, sans pop-ups qui parasitent votre quotidien.</p>
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
                    <p>Invitez vos proches par simple code et gérez les accès de chacun — y compris un accès co-parent restreint — depuis les réglages.</p>
                </div>
            </div>
        </div>
    </section>

    <?php if (!empty($pricingPlans)): ?>
    <section class="landing-section pricing-section">
        <div class="section-heading">
            <span class="kicker">Tarifs</span>
            <h2>Une offre gratuite pour découvrir, un seul abonnement Premium pour tout débloquer</h2>
            <p>Aucune carte requise pour commencer. Passez à Premium quand votre famille est prête, avec <?= $trialDays ?> jours d'essai gratuit.</p>
        </div>
        <div class="pricing-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1.25rem;max-width:960px;margin:0 auto">
            <div class="card" style="display:flex;flex-direction:column;gap:.6rem;padding:1.5rem">
                <h3 style="margin:0">Gratuit</h3>
                <p style="color:var(--text-muted);margin:0">Jusqu'à 4 membres</p>
                <div><strong style="font-size:1.5rem">0 €</strong> <span style="color:var(--text-muted)">/ mois</span></div>
                <p style="color:var(--text-muted);font-size:.85rem;margin:0">Calendrier, tâches, mur familial, chat, répertoire et anniversaires — sans limite de temps.</p>
                <a href="<?= BASE_URL ?>/register" class="btn btn-secondary" style="margin-top:auto">Créer ma famille</a>
            </div>
            <?php foreach ($pricingPlans as $p): ?>
            <div class="card" style="display:flex;flex-direction:column;gap:.6rem;padding:1.5rem;border:2px solid var(--primary)">
                <h3 style="margin:0"><?= htmlspecialchars($p['name']) ?></h3>
                <p style="color:var(--text-muted);margin:0"><?= $p['member_limit'] ? "Jusqu'à {$p['member_limit']} membres" : 'Membres illimités' ?></p>
                <div><strong style="font-size:1.5rem"><?= number_format($p['price_monthly_cents'] / 100, 2, ',', ' ') ?> €</strong> <span style="color:var(--text-muted)">/ mois</span></div>
                <p style="color:var(--text-muted);font-size:.85rem;margin:0"><?= number_format($p['price_yearly_cents'] / 100, 2, ',', ' ') ?> € / an<?= $annualDiscount > 0 ? " (-{$annualDiscount}%)" : '' ?></p>
                <p style="color:var(--text-muted);font-size:.85rem;margin:0">Tous les modules débloqués : garde alternée, suivi scolaire, dossiers de litige, budget, projets…</p>
                <a href="<?= BASE_URL ?>/register" class="btn btn-primary" style="margin-top:auto">Essayer <?= $trialDays ?> jours gratuits</a>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

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
        <p><a href="<?= BASE_URL ?>/faq">FAQ</a> · <a href="<?= BASE_URL ?>/confidentialite">Confidentialité</a> · <a href="<?= BASE_URL ?>/cgu">CGU</a></p>
    </footer>
</div>
<script src="<?= ASSETS_URL ?>/js/app.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>
