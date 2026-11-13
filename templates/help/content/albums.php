<?php
return [
    'summary' => 'Vos souvenirs de famille classés en albums, partageables par lien, avec le mur familial ou avec l\'autre parent.',
    'who' => ['Tous les membres', 'Accès co-parent : albums partagés avec lui'],
    'coparent' => [
        'summary' => 'Les albums que la famille a choisi de partager avec votre accès co-parent.',
        'sections' => [
            ['id' => 'voir', 'title' => 'Consulter et ajouter', 'html' => <<<'H'
<p>Vous voyez uniquement les albums que la famille a partagés avec votre accès. Vous pouvez y <strong>ajouter vos propres photos</strong> (bouton <span class="hg-ui">📷 Ajouter des photos</span>) ; vous ne pouvez en revanche <strong>ni modifier ni supprimer</strong> les photos des autres.</p>
H],
        ],
    ],
    'sections' => [
        ['id' => 'creer', 'title' => 'Créer un album et ajouter des photos', 'html' => <<<'H'
<ol>
<li>Cliquez sur <span class="hg-ui">+ Nouvel album</span>.</li>
<li>Donnez un <strong>Titre</strong> (« Vacances d'été », « anniversaire »…) et une <strong>Description</strong> facultative, puis <span class="hg-ui">Créer</span>.</li>
<li>Ouvrez l'album, cliquez sur <span class="hg-ui">📷 Ajouter des photos</span> (ou glissez-déposez vos fichiers) et choisissez une ou plusieurs photos.</li>
</ol>
<p>La liste des albums indique le nombre de photos de chacun. Dans un album, la croix ✕ sur une photo la supprime. <span class="hg-ui">Renommer</span> change le titre et la description ; <span class="hg-ui">Supprimer l'album</span> supprime l'album et ses photos.</p>
H],
        ['id' => 'mur', 'title' => 'Montrer un album ou une photo sur le mur', 'html' => <<<'H'
<ul>
<li><strong>Un album entier</strong> : le menu en haut de l'album (par défaut <em>🚫 Pas sur le mur</em>) permet de choisir <em>🏠 Mur — Famille</em>, <em>🙋 Mur — Amis</em> ou <em>🤝 Mur — Familles amies</em>. L'album apparaît alors dans l'onglet <strong>Photos</strong> du <a href="{{app_url}}/aide/wall">mur</a>.</li>
<li><strong>Une photo seule</strong> : cliquez sur 📤 sur la photo, ajoutez une <strong>Légende</strong> facultative, choisissez qui la voit (Amis, Ma famille, Familles amies) puis <span class="hg-ui">Partager</span>. Elle devient une publication du mur.</li>
</ul>
H],
        ['id' => 'public', 'title' => 'Partager par un lien public', 'html' => <<<'H'
<p>Le bouton <span class="hg-ui">🔗 Lien public</span> permet à n'importe qui de voir l'album <strong>sans compte</strong>.</p>
<ol>
<li>Cochez si vous le souhaitez <strong>Autoriser l'ajout de photos par le destinataire</strong> : idéal pour recevoir les photos d'un événement.</li>
<li>Cliquez sur <span class="hg-ui">Générer un lien public</span>, puis <span class="hg-ui">Copier</span> le lien.</li>
</ol>
<p>Quand l'ajout est autorisé, la personne qui reçoit le lien peut indiquer son nom (facultatif) et ajouter des photos. Vous pouvez modifier cette autorisation à tout moment, ou <span class="hg-ui">Révoquer le lien</span> : l'ancien lien affiche alors « Lien invalide ou révoqué ».</p>
<div class="hg-warn">Toute personne qui possède le lien voit l'album. Ne le publiez pas sur un réseau public.</div>
H],
        ['id' => 'coparent', 'title' => 'Partager avec le co-parent', 'html' => <<<'H'
<p>Le bouton <span class="hg-ui">Partager</span> permet de choisir un ou plusieurs <strong>enfants</strong> : le co-parent qui a accès au planning de garde de ces enfants voit l'album et peut y ajouter ses photos (sans pouvoir modifier ni supprimer celles des autres). Un album partagé est signalé par <em>🔒 Partagé avec le co-parent</em>. Il faut avoir configuré au moins un planning de garde (voir <a href="{{app_url}}/aide/custody">Garde alternée</a>).</p>
H],
    ],
];
