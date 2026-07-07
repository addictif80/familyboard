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

    <section class="landing-section">
        <div class="section-heading">
            <span class="kicker">Fonctionnalités</span>
            <h2>Tout ce dont votre famille a besoin, réuni au même endroit</h2>
            <p>Chaque module est pensé pour être utile dès la première minute, sans réglages compliqués.</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">📅</div>
                <h3>Calendrier partagé</h3>
                <p>Un agenda commun pour toute la famille, avec synchronisation CalDAV et vue par membre.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📸</div>
                <h3>Mur familial</h3>
                <p>Partagez photos, souvenirs et petits mots comme sur un fil d'actualité, à l'abri des regards extérieurs.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">✅</div>
                <h3>Tâches & listes</h3>
                <p>Listes de courses, corvées et projets partagés, assignables à chaque membre du foyer.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">💰</div>
                <h3>Budget familial</h3>
                <p>Suivez dépenses, objectifs d'épargne et prélèvements récurrents en toute transparence.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">👶</div>
                <h3>Garde alternée</h3>
                <p>Un planning de garde clair, avec propositions automatiques et historique des échanges.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🍼</div>
                <h3>Suivi bébé & grossesse</h3>
                <p>Biberons, sommeil, consultations et suivi de grossesse, centralisés pour les deux parents.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🆘</div>
                <h3>Fiches d'urgence QR</h3>
                <p>Allergies, contacts et informations vitales accessibles en un scan pour les nounous et l'école.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🍽️</div>
                <h3>Repas & recettes</h3>
                <p>Planifiez les repas de la semaine et transformez vos recettes en listes de courses en un clic.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🗂️</div>
                <h3>Coffre-fort documents</h3>
                <p>Garanties, papiers administratifs et documents importants, retrouvés en quelques secondes.</p>
            </div>
        </div>
    </section>

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
