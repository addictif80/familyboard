<?php
return [
    'summary' => 'L\'agenda commun de la famille : événements, récurrences, congés, vacances scolaires, calendriers externes et partage avec des familles amies.',
    'who' => ['Tous les membres', 'Accès co-parent : événements liés à l\'enfant'],
    'coparent' => [
        'summary' => 'Les événements liés à l\'enfant pour lequel vous avez un accès de garde partagée.',
        'sections' => [
            ['id' => 'voir', 'title' => 'Ce que vous voyez', 'html' => <<<'H'
<p>Vous voyez uniquement les événements rattachés à l'enfant pour lequel vous avez un accès de garde partagée. Les autres événements de la famille, et les événements d'autres enfants, ne vous sont jamais montrés.</p>
H],
            ['id' => 'ajouter', 'title' => 'Ajouter un événement', 'html' => <<<'H'
<p>Vous pouvez ajouter un événement concernant cet enfant (rendez-vous médical, activité…). Il devient visible par l'autre parent ayant accès au même enfant. Les champs sont ceux du formulaire d'événement : titre, dates, couleur, récurrence, informations sur le lieu.</p>
H],
        ],
    ],
    'sections' => [
        ['id' => 'vues', 'title' => 'Parcourir le calendrier', 'html' => <<<'H'
<p>Le calendrier affiche le mois en cours sous forme de grille ; sur mobile, une liste d'agenda en dessous liste les événements du jour sélectionné. Cliquez sur un jour pour ouvrir la fenêtre <strong>Journée</strong> : elle liste tous les événements de ce jour et propose <span class="hg-ui">+ Ajouter un événement</span>.</p>
<h3>Choisir ce qui s'affiche</h3>
<p>La barre au-dessus du calendrier contient des cases à cocher :</p>
<ul>
<li>une case par <strong>membre</strong>, avec sa couleur : décochez-en une pour masquer ses événements ;</li>
<li><strong>👶 Garde alternée</strong> : les jours de garde (voir <a href="{{app_url}}/aide/custody">Garde alternée</a>) ;</li>
<li><strong>📋 Projets</strong> : les échéances de vos projets ;</li>
<li><strong>🏖 Congés</strong> : les congés enregistrés ;</li>
<li><strong>🎓 Vacances scolaires</strong> : les vacances de votre zone (si une zone est choisie dans les réglages de la famille).</li>
</ul>
<p>Les anniversaires des membres, des enfants et des contacts s'affichent aussi automatiquement.</p>
H],
        ['id' => 'creer', 'title' => 'Créer un événement', 'html' => <<<'H'
<ol>
<li>Cliquez sur <span class="hg-ui">+ Événement</span>, ou sur un jour puis <span class="hg-ui">+ Ajouter un événement</span>.</li>
<li>Remplissez le <strong>Titre</strong> (obligatoire), éventuellement une <strong>Description</strong>.</li>
<li>Choisissez le <strong>Début</strong> et la <strong>Fin</strong> (obligatoires, la fin doit suivre le début), ou cochez <strong>Toute la journée</strong>.</li>
<li>Choisissez une <strong>Couleur</strong>.</li>
<li>Réglez la <strong>Récurrence</strong> : <em>Aucune, Quotidienne, Hebdomadaire, Mensuelle ou Annuelle</em>.</li>
<li>Cliquez sur <span class="hg-ui">Enregistrer</span>.</li>
</ol>
<p>Toute la famille est prévenue par une notification de l'ajout d'un événement.</p>
<h3>Options avancées du formulaire</h3>
<ul>
<li><strong>Garde alternée</strong> : rattache l'événement à un ou plusieurs enfants suivis en garde alternée. Il devient visible par le ou les co-parents à accès restreint de ces enfants.</li>
<li><strong>Congé / absence salarié</strong> : choisissez un profil et un type (<em>Congé payé, RTT, Absence non payée</em>). L'événement est alors compté dans le <a href="{{app_url}}/aide/employment">Suivi salarié</a> de ce profil.</li>
<li><strong>Inviter une famille amie</strong> : voir plus bas. Disponible uniquement à la création.</li>
<li><strong>Ajouter plus d'infos</strong> : nom du professionnel (« Dr Martin », « Garage Dupont »…) et adresse, avec des liens directs <span class="hg-ui">📍 Google Maps</span> et <span class="hg-ui">🚗 Waze</span>.</li>
</ul>
H],
        ['id' => 'modifier', 'title' => 'Modifier ou supprimer', 'html' => <<<'H'
<p>Cliquez sur un événement pour le rouvrir, puis <span class="hg-ui">Enregistrer</span> pour valider vos changements ou <span class="hg-ui">Supprimer</span> (une confirmation est demandée). Tout membre de la famille peut modifier ou supprimer un événement de la famille ; les autres membres reçoivent une notification. Les vacances scolaires ne se modifient pas.</p>
H],
        ['id' => 'conges', 'title' => 'Ajouter un congé', 'html' => <<<'H'
<p>Le bouton <span class="hg-ui">+ Congé</span> ouvre <strong>Ajouter un congé</strong> : renseignez un intitulé (« Congé », « vacances », « RTT »…), un début et une fin (obligatoires, la fin doit suivre le début). Les congés s'affichent avec la case <em>🏖 Congés</em>. Le module <a href="{{app_url}}/aide/leave">Congés familiaux</a> propose une vue dédiée avec détection des chevauchements.</p>
H],
        ['id' => 'caldav', 'title' => 'Importer un calendrier externe (CalDAV / iCal)', 'html' => <<<'H'
<p>Vous pouvez afficher dans {{app}} un agenda tenu ailleurs (Google, Nextcloud, iCloud, calendrier scolaire…).</p>
<ol>
<li>Cliquez sur <span class="hg-ui">🔗 CalDAV</span>.</li>
<li>Indiquez un <strong>Nom</strong> et l'<strong>URL CalDAV / iCal</strong> (adresse publique en .ics, ou protégée par identifiants).</li>
<li>Renseignez si nécessaire l'<strong>Identifiant</strong> et le <strong>Mot de passe</strong>, et une <strong>Couleur</strong>.</li>
<li>Cliquez sur <span class="hg-ui">Ajouter et synchroniser</span>.</li>
</ol>
<p>Les calendriers ajoutés sont listés sous le calendrier (« Calendriers CalDAV ») avec la date de dernière synchronisation. <span class="hg-ui">🔄</span> relance la synchronisation à la demande ; <span class="hg-ui">🗑</span> supprime le calendrier <strong>et ses événements</strong>.</p>
<div class="hg-note">Un administrateur peut activer la <strong>synchronisation automatique</strong> (toutes les 15 minutes à une fois par jour) dans <em>Réglages › Famille</em>.</div>
H],
        ['id' => 'amies', 'title' => 'Inviter une famille amie à un événement', 'html' => <<<'H'
<ol>
<li>Devenez d'abord amis avec l'autre famille avec son code famille : <a href="{{app_url}}/aide/famille">Gérer la famille</a>.</li>
<li>À la <strong>création</strong> de l'événement, cochez <strong>Inviter une famille amie</strong> et choisissez la ou les familles.</li>
<li>La famille invitée reçoit l'invitation et doit l'<strong>accepter</strong> avant de voir l'événement. Elle peut la refuser en indiquant un motif (facultatif).</li>
</ol>
<div class="hg-warn">On ne peut inviter qu'à la création. Pour inviter à un événement existant, recréez-le.</div>
<p>Si vous modifiez ou supprimez ensuite un événement déjà partagé, chaque famille participante reçoit une alerte lui demandant d'accepter ou de refuser le changement : rien n'est jamais répercuté sans son accord. Les événements partagés portent les mentions « partagé avec une famille amie » ou « reçu de la famille … ».</p>
H],
        ['id' => 'telephone', 'title' => 'Synchroniser avec son téléphone', 'html' => <<<'H'
<p>Dans <em>Réglages › Application</em>, le bloc <strong>Synchroniser le calendrier avec mon mobile</strong> fournit un lien d'abonnement privé. Collez-le dans l'application Calendrier de votre téléphone (iPhone : <em>Réglages › Calendrier › Comptes › Ajouter un compte › Autre › Calendrier à abonnement</em> ; Google Agenda : <em>Autres agendas › À partir de l'URL</em>, depuis un ordinateur). Votre téléphone se met ensuite à jour tout seul. Si votre téléphone est perdu ou revendu, <span class="hg-ui">🔄 Régénérer le lien</span> invalide l'ancien. Détails : <a href="{{app_url}}/aide/mon-compte">Mon compte et mes réglages</a>.</p>
H],
        ['id' => 'rappels', 'title' => 'Rappels automatiques', 'html' => <<<'H'
<ul>
<li><strong>Rappel par e-mail</strong> : chaque membre dont l'adresse est renseignée reçoit un e-mail pour les événements qui commencent dans les 24 heures (sauf ceux importés par CalDAV).</li>
<li><strong>Récapitulatif de la veille</strong> : un e-mail par membre listant les événements du lendemain.</li>
<li><strong>Résumé hebdomadaire</strong> : le dimanche soir, la semaine à venir de toute la famille.</li>
</ul>
<p>Ces e-mails nécessitent que l'envoi de messages soit configuré sur votre plateforme.</p>
H],
    ],
];
