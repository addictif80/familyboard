<?php
return [
    'summary' => 'Qui voit quoi, ce qui est partagé hors de la famille, ce qui est journalisé et comment protéger votre compte.',
    'who' => ['Tout le monde'],
    'sections' => [
        ['id' => 'famille', 'title' => 'Ce que voit chaque personne', 'html' => <<<'H'
<ul>
<li>Un <strong>membre</strong> voit les données communes de la famille (calendrier, tâches, documents, albums…), selon les modules actifs.</li>
<li>Votre <strong>traitement médical</strong> (module <a href="{{app_url}}/aide/medication">Médicaments</a>) est <strong>privé</strong>.</li>
<li>Un <strong>ado</strong> n'a pas accès aux modules sensibles (voir <a href="{{app_url}}/aide/profils">Profils</a>).</li>
<li>Un <strong>co-parent</strong> ne voit jamais le mur, le chat, le budget ni les autres données de la famille.</li>
<li>Les <strong>mots de passe</strong> du coffre sont chiffrés : le mot de passe maître n'est jamais connu de {{app}}.</li>
</ul>
H],
        ['id' => 'public', 'title' => 'Ce qui sort de la famille', 'html' => <<<'H'
<p>Certaines fonctions créent un <strong>lien que n'importe qui peut ouvrir sans compte</strong> : voir <a href="{{app_url}}/aide/liens-publics">Liens publics</a> pour la liste complète, avec comment les couper.</p>
H],
        ['id' => 'traces', 'title' => 'Ce qui est journalisé', 'html' => <<<'H'
<ul>
<li>Le <a href="{{app_url}}/aide/comm_log">journal parental</a> : messages horodatés et immuables.</li>
<li>Le <strong>journal d'activité de la garde</strong> : historique horodaté, avec adresse IP, visible par les deux parents.</li>
<li>Les ouvertures des liens publics des <a href="{{app_url}}/aide/disputes">dossiers de litige</a> et des <a href="{{app_url}}/aide/emergency">fiches urgence</a>.</li>
<li>L'<strong>historique des e-mails</strong> envoyés (Réglages › Historique).</li>
</ul>
H],
        ['id' => 'position', 'title' => 'Position géographique', 'html' => <<<'H'
<p>Le partage de position est ponctuel et à durée choisie (voir <a href="{{app_url}}/aide/location">Position</a>). Le suivi pour les minuteurs est facultatif, non conservé en historique et désactivable dans votre profil.</p>
H],
        ['id' => 'securite', 'title' => 'Protéger votre compte', 'html' => <<<'H'
<ol>
<li>Choisissez un mot de passe unique et d'au moins 8 caractères.</li>
<li>Activez la <a href="{{app_url}}/aide/mon-compte">double authentification</a>.</li>
<li>Utilisez <span class="hg-ui">🔌 Déconnecter tous les appareils</span> si un appareil est perdu.</li>
<li>Régénérez les liens (calendrier, fiche urgence, litige) s'ils ont été diffusés trop largement.</li>
</ol>
H],
        ['id' => 'donnees', 'title' => 'Vos données', 'html' => <<<'H'
<p>Vous pouvez exporter vos données à tout moment et supprimer votre compte (voir <a href="{{app_url}}/aide/mon-compte">Mon compte</a>). Les détails figurent dans la politique de confidentialité.</p>
H],
    ],
];
