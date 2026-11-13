<?php
return [
    'summary' => 'Installer {{app}} sur votre téléphone ou ordinateur comme une vraie application, avec notifications et barre rapide.',
    'who' => ['Tous les membres'],
    'sections' => [
        ['id' => 'installer', 'title' => 'Installer l\'application', 'html' => <<<'H'
<p>{{app}} s'installe <strong>directement depuis votre navigateur</strong>, sans store : icône sur l'écran d'accueil, plein écran, notifications. Dans <strong>Réglages › Application</strong>, le bouton <span class="hg-ui">📲 Installer l'application</span> lance l'installation ; « ✅ Application installée sur cet appareil » s'affiche ensuite.</p>
<h3>iPhone / iPad (Safari)</h3>
<ol>
<li>Ouvrez {{app}} dans <strong>Safari</strong> (obligatoire, pas Chrome sur iOS).</li>
<li>Appuyez sur le bouton <strong>Partager</strong> (carré avec une flèche vers le haut).</li>
<li>Choisissez « Sur l'écran d'accueil », puis « Ajouter ».</li>
<li>Ouvrez {{app}} depuis la nouvelle icône (pas depuis Safari) et activez les notifications.</li>
</ol>
<h3>Android (Chrome)</h3>
<ol>
<li>Ouvrez {{app}} dans Chrome.</li>
<li>Appuyez sur « Installer l'application » (bouton ci-dessus ou menu ⋮).</li>
<li>Ouvrez l'application depuis l'icône ajoutée et activez les notifications.</li>
</ol>
H],
        ['id' => 'notifs', 'title' => 'Activer les notifications', 'html' => <<<'H'
<p>Dans <strong>🔔 Notifications push</strong>, cliquez sur <span class="hg-ui">Activer les notifications push</span> sur chaque appareil. Sur iPhone, installez d'abord l'application.</p>
H],
        ['id' => 'barre', 'title' => '📱 Barre rapide', 'html' => <<<'H'
<p>Sur mobile et dans l'application installée, une barre en bas de l'écran donne accès à l'<strong>accueil</strong>, à <strong>3 modules de votre choix</strong> et au reste du menu (« Plus »). Cochez vos 3 modules dans <strong>Réglages › Application › Barre rapide</strong> puis <span class="hg-ui">Enregistrer</span> : ils restent fixes tant que vous ne les changez pas.</p>
H],
    ],
];
