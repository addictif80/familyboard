<?php
return [
    'summary' => 'Un accès permanent sans compte pour une tablette ou une télé fixée au mur : tâches, courses, agenda et repas.',
    'who' => ['Administrateur : création des accès', 'Tout appareil avec le lien ou le code'],
    'sections' => [
        ['id' => 'creer', 'title' => 'Créer un accès kiosque (administrateur)', 'html' => <<<'H'
<ol>
<li>Allez dans <em>Réglages › Accès partagés › Écran mural (mode kiosque)</em>.</li>
<li>Saisissez un <strong>Libellé</strong> (« Tablette cuisine »).</li>
<li>Cliquez sur <span class="hg-ui">+ Générer un accès</span>.</li>
<li>Utilisez <span class="hg-ui">🔗 Voir le lien</span> pour obtenir le lien et un code à 6 chiffres.</li>
</ol>
<p>Le lien d'un accès est permanent tant que vous ne le révoquez pas (<span class="hg-ui">Révoquer</span>). Un lien révoqué affiche « Accès invalide ».</p>
H],
        ['id' => 'ouvrir', 'title' => 'Ouvrir l\'écran sur l\'appareil', 'html' => <<<'H'
<ul>
<li><strong>Tablette ou ordinateur</strong> : ouvrez le lien (<span class="hg-ui">📋 Copier le lien</span> puis <span class="hg-ui">Ouvrir</span>).</li>
<li><strong>Télé connectée</strong> : ouvrez l'adresse <code>/tv</code> du site et saisissez le <strong>code à 6 chiffres</strong> affiché dans les réglages, puis <span class="hg-ui">Afficher</span>.</li>
</ul>
H],
        ['id' => 'utiliser', 'title' => 'Ce qu\'on y fait', 'html' => <<<'H'
<ul>
<li>Consulter les <strong>tâches et courses</strong>, les <strong>événements</strong>, les <strong>repas</strong> et les <strong>contacts</strong>, actualisés automatiquement.</li>
<li><strong>Ajouter et cocher</strong> des tâches directement sur l'écran tactile, sans se connecter.</li>
<li>Démarrer et arrêter les <strong>minuteurs</strong> de la maison.</li>
</ul>
<p>Touchez l'écran pour arrêter une alarme.</p>
H],
        ['id' => 'sitter', 'title' => 'Pendant une garde par une baby-sitter', 'html' => <<<'H'
<p>Quand un <a href="{{app_url}}/aide/sitter">accès baby-sitter</a> est actif, l'écran affiche <strong>« 👶 Mode baby-sitter actif »</strong> avec un QR code à la place des informations familiales, qui sont masquées pendant la garde. Le bouton <span class="hg-ui">Afficher les infos de la famille</span> permet de les réafficher.</p>
H],
        ['id' => 'minuteurs', 'title' => 'Minuteurs et alarmes', 'html' => <<<'H'
<p>Les minuteurs prédéfinis se créent dans <em>Réglages › Accès partagés › Minuteurs de l'écran mural</em> : nom, durée en minutes, case « Afficher sur l'écran mural ». N'importe qui peut en démarrer un d'un bouton depuis l'écran mural ou le kiosque ; une alarme sonne à l'échéance, avec un bouton pour l'arrêter.</p>
<h3>Alerte différée</h3>
<p>Si personne n'est à la maison quand un minuteur se termine, une notification push est envoyée à la place de l'alarme, qui se déclenche dès le retour de quelqu'un. Cela repose sur :</p>
<ol>
<li>le <strong>Domicile</strong>, configuré par un administrateur dans <em>Réglages › Famille</em> (<span class="hg-ui">📍 Utiliser ma position actuelle</span>) ;</li>
<li>le <strong>suivi de position</strong> de chaque membre, activable ou désactivable dans <em>Mon profil</em>. La position n'est jamais conservée en historique.</li>
</ol>
<p>Sans Domicile configuré, l'alerte différée est inactive et les minuteurs sonnent normalement.</p>
H],
    ],
];
