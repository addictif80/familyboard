<?php
return [
    'summary' => 'Votre page d\'accueil : l\'essentiel de la journée, vos widgets préférés et des raccourcis pour ajouter en un geste.',
    'who' => ['Tous les membres'],
    'coparent' => [
        'summary' => 'Un accès co-parent n\'a pas de tableau de bord classique : à la connexion, vous arrivez directement dans votre espace de garde partagée.',
        'sections' => [
            ['id' => 'espace', 'title' => 'Votre page d\'accueil', 'html' => <<<'H'
<p>Votre page d'accueil est l'<a href="{{app_url}}/aide/coparent-espace">espace co-parent</a>.</p>
H],
        ],
    ],
    'sections' => [
        ['id' => 'haut', 'title' => 'Le haut de page', 'html' => <<<'H'
<p>Le tableau de bord vous accueille avec un « Bonjour » et la date, la fête du jour et l'éphéméride. Quatre cartes résument votre journée :</p>
<ul>
<li><strong>Tâches du jour ✅</strong> : les échéances d'aujourd'hui ;</li>
<li><strong>Événements à venir 📅</strong> : le prochain rendez-vous ;</li>
<li><strong>Budget du mois 💰</strong> : revenus et dépenses du mois ;</li>
<li><strong>Messages non lus 💬</strong> : vos messages privés en attente.</li>
</ul>
<p>Plus bas : <strong>Aujourd'hui & demain</strong> (les événements des deux jours, avec un lien « Voir le calendrier »), <strong>Votre famille</strong> (les membres, avec <span class="hg-ui">+ Inviter un membre</span> pour un administrateur), <strong>🚨 Alertes officielles</strong> et <strong>📣 Actualités</strong> (voir <a href="{{app_url}}/aide/recherche-alertes">Recherche, alertes et notifications</a>).</p>
H],
        ['id' => 'widgets', 'title' => 'Personnaliser vos widgets', 'html' => <<<'H'
<p>Sous les cartes, vos <strong>widgets</strong> résument les modules que vous utilisez. La personnalisation est <strong>propre à chaque utilisateur</strong> : vos choix n'affectent pas les autres membres.</p>
<ol>
<li>Cliquez sur <span class="hg-ui">✨ Personnaliser</span>.</li>
<li>Activez ou désactivez chaque widget avec sa case à cocher.</li>
<li>Cliquez sur <span class="hg-ui">Enregistrer ✨</span>.</li>
</ol>
<p>Pour changer l'<strong>ordre</strong>, faites glisser les cartes directement sur le tableau de bord. Un widget n'apparaît que si son module est activé pour la famille.</p>
<table class="hg-tbl">
<tr><th>Catégorie</th><th>Widgets disponibles</th></tr>
<tr><td>Quotidien</td><td>Calendrier, Mur familial, Tâches, Stock frigo/congélo, Sondages, Chat</td></tr>
<tr><td>Famille & santé</td><td>Garde alternée, Bébé, Anniversaires, Liste de cadeaux, Traitements en cours</td></tr>
<tr><td>Biens & déplacements</td><td>Budget, Projets, Véhicules, Animaux de compagnie, Voyages & réservations, Compteurs</td></tr>
</table>
<p>Le widget <strong>Anniversaires</strong> affiche ceux des 60 prochains jours (membres, enfants, contacts) ; <strong>Traitements en cours</strong> ne montre que vos propres traitements ; <strong>Garde alternée</strong> indique où est l'enfant aujourd'hui.</p>
H],
        ['id' => 'rapide', 'title' => 'Ajouter rapidement', 'html' => <<<'H'
<p>Le bouton <span class="hg-ui">+</span> ouvre <strong>Ajouter rapidement</strong> avec quatre raccourcis : <span class="hg-ui">✅ Tâche</span>, <span class="hg-ui">📅 Événement</span>, <span class="hg-ui">🗂️ Document</span> et <span class="hg-ui">📸 Photo</span>.</p>
H],
        ['id' => 'point', 'title' => 'Le point de la semaine', 'html' => <<<'H'
<p>Quand l'assistant d'IA est configuré, un résumé rédigé de votre semaine à venir est préparé le dimanche soir. Il s'affiche dans une fenêtre <strong>✨ Le point de la semaine</strong> à votre prochaine connexion (ou au prochain lancement de l'application installée). Fermez-la avec <span class="hg-ui">Compris, merci !</span> : elle ne revient pas pour cette semaine.</p>
H],
        ['id' => 'barre', 'title' => 'La barre du haut', 'html' => <<<'H'
<ul>
<li><strong>🔎 Recherche rapide</strong> (<kbd>Ctrl</kbd>+<kbd>K</kbd> ou <kbd>⌘</kbd>+<kbd>K</kbd>) : retrouver un module ou un enregistrement.</li>
<li><strong>🐞 Signaler un problème</strong> : envoyer une description au support.</li>
<li><strong>📣 Annonces</strong> : nouveautés publiées par l'équipe, avec un compteur de non lues.</li>
<li><strong>☀️ / 🌙 Thème</strong> : basculer entre le mode clair et le mode sombre, pour vous seul.</li>
<li><strong>🔔 Notifications</strong> : votre liste de notifications.</li>
<li><strong>🤖 Assistant</strong> : voir <a href="{{app_url}}/aide/ai_assistant">Assistant IA</a>.</li>
</ul>
H],
    ],
];
