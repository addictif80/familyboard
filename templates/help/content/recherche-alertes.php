<?php
return [
    'summary' => 'Retrouver n\'importe quoi, comprendre les notifications, les alertes officielles et tous les rappels automatiques.',
    'who' => ['Tous les membres'],
    'sections' => [
        ['id' => 'recherche', 'title' => 'La recherche rapide', 'html' => <<<'H'
<p>Appuyez sur <kbd>Ctrl</kbd>+<kbd>K</kbd> (<kbd>⌘</kbd>+<kbd>K</kbd> sur Mac), ou cliquez sur la loupe 🔎, puis tapez quelques lettres. La recherche porte sur :</p>
<ul>
<li>les <strong>pages et modules</strong> de l'application (aller directement quelque part) ;</li>
<li>le <strong>contenu réel</strong> que vous avez le droit de voir : tâches, événements, contacts, documents…</li>
</ul>
<p>Elle ne montre que ce qui vous est accessible : un module désactivé ou masqué pour votre rôle n'apparaît pas. Un accès co-parent n'a pas cette recherche.</p>
<p>Une barre de recherche équivalente est aussi disponible en haut de la barre de gauche (« Rechercher un module ou un enregistrement… »).</p>
H],
        ['id' => 'notifications', 'title' => 'Notifications dans l\'application', 'html' => <<<'H'
<p>La cloche 🔔 liste les événements qui vous concernent (nouvel événement, nouveau message, publication à valider…).</p>
<ul>
<li>Cliquez sur une notification pour l'ouvrir : un aperçu s'affiche, avec un lien vers l'élément concerné.</li>
<li>Une notification se marque comme lue à l'ouverture ; <strong>Tout marquer comme lu</strong> les marque toutes.</li>
</ul>
<p>Pour recevoir ces notifications même application fermée, activez les <strong>notifications push</strong> : voir <a href="{{app_url}}/aide/mon-compte">Mon compte et mes réglages</a> et <a href="{{app_url}}/aide/appli-mobile">Application mobile</a>.</p>
H],
        ['id' => 'alertes', 'title' => 'Alertes officielles', 'html' => <<<'H'
<p>Un bandeau défilant en haut de l'application et un encart <strong>🚨 Alertes officielles</strong> sur le tableau de bord signalent des alertes publiques, par catégories :</p>
<ul>
<li>🚨 Alerte enlèvement ; 🌡️ Canicule ; 🌊 Inondation ; 🔥 Feu de forêt ; ⛈️ Alerte climatique ; ☣️ Catastrophe industrielle.</li>
</ul>
<p>Certaines catégories sont <strong>localisées</strong> (canicule, inondation, alerte climatique, catastrophe industrielle) : elles ne vous concernent que si votre ville le justifie, d'où l'intérêt de renseigner la <em>Ville pour la météo</em> dans les réglages de la famille. Le bouton <strong>S'informer</strong> ouvre la page d'historique d'une catégorie, qui liste les articles des 30 derniers jours avec un lien vers la source.</p>
<p>Les familles concernées reçoivent aussi une notification, sans qu'elle se répète à chaque détection.</p>
H],
        ['id' => 'annonces', 'title' => 'Annonces', 'html' => <<<'H'
<p>L'icône 📣 en haut ouvre les <strong>Annonces</strong> publiées par l'équipe du service (nouveautés, informations). Un compteur indique celles que vous n'avez pas lues ; ouvrir la page les marque comme lues.</p>
H],
        ['id' => 'rappels', 'title' => 'Tous les rappels automatiques', 'html' => <<<'H'
<p>{{app}} vous prévient avant les échéances. Les e-mails nécessitent que l'envoi de messages soit configuré sur la plateforme et qu'une adresse e-mail soit renseignée dans votre profil.</p>
<table class="hg-tbl">
<tr><th>Rappel</th><th>Quand</th><th>Canal</th></tr>
<tr><td>Événement du calendrier</td><td>Dans les 24 h avant le début (hors événements importés par CalDAV)</td><td>E-mail</td></tr>
<tr><td>Événements du lendemain</td><td>La veille</td><td>E-mail (un par membre)</td></tr>
<tr><td>Résumé de la semaine</td><td>Dimanche soir</td><td>E-mail</td></tr>
<tr><td>Anniversaires</td><td>7 jours avant</td><td>E-mail</td></tr>
<tr><td>Tâches en attente</td><td>Après 7 jours sans être terminées</td><td>E-mail</td></tr>
<tr><td>Liste de courses oubliée</td><td>Éléments en attente depuis plus de 7 jours</td><td>E-mail</td></tr>
<tr><td>Stock frigo/congélo</td><td>3 jours avant la péremption, puis le jour même</td><td>E-mail</td></tr>
<tr><td>Garanties et documents</td><td>30 jours puis 7 jours avant l'échéance</td><td>E-mail</td></tr>
<tr><td>Charges récurrentes du budget</td><td>Nombre de jours choisi avant la date</td><td>E-mail</td></tr>
<tr><td>Prise de traitement</td><td>À l'heure prévue (marge de quelques minutes)</td><td>Notification push uniquement, jamais par e-mail</td></tr>
<tr><td>Minuteur terminé alors que personne n'est à la maison</td><td>À l'échéance</td><td>Notification push</td></tr>
</table>
<p>Les modules <a href="{{app_url}}/aide/admin_procedures">Démarches administratives</a>, <a href="{{app_url}}/aide/vehicles">Véhicules</a> et <a href="{{app_url}}/aide/pets">Animaux</a> décrivent aussi leurs propres rappels.</p>
H],
    ],
];
