<?php
return [
    'summary' => 'Le réseau social privé de la famille : publiez, commentez, réagissez et suivez les proches de votre choix.',
    'who' => ['Tous les membres', 'Administrateur : validation des publications « Famille »'],
    'sections' => [
        ['id' => 'principe', 'title' => 'Comment ça marche', 'html' => <<<'H'
<p>Le mur est un fil d'actualité entièrement privé. Rien n'est visible en dehors de {{app}}, et chaque publication a une <strong>portée</strong> qui décide qui peut la voir.</p>
<table class="hg-tbl">
<tr><th>Portée</th><th>Qui voit la publication</th><th>Validation</th></tr>
<tr><td>🙋 <strong>Amis</strong> (mes abonnés)</td><td>Les membres qui vous suivent et dont vous avez accepté la demande, y compris dans une famille amie.</td><td>Publiée immédiatement</td></tr>
<tr><td>🏠 <strong>Ma famille</strong></td><td>Tous les membres de votre famille, publiée au nom de la famille.</td><td>Un administrateur doit la valider, sauf si c'est lui qui publie</td></tr>
<tr><td>🤝 <strong>Familles amies</strong></td><td>Votre famille et toutes vos familles amies.</td><td>Publiée immédiatement</td></tr>
</table>
H],
        ['id' => 'publier', 'title' => 'Publier', 'html' => <<<'H'
<ol>
<li>Dans le champ en haut du fil, écrivez votre message.</li>
<li>Ajoutez si vous le souhaitez une photo avec <span class="hg-ui">📷 Photo</span> (depuis votre appareil) ou <span class="hg-ui">🖼️ Depuis un album</span> (une photo déjà présente dans vos albums).</li>
<li>Choisissez la portée dans le menu déroulant : <span class="hg-ui">🙋 Amis (mes abonnés)</span>, <span class="hg-ui">🏠 Ma famille</span> ou <span class="hg-ui">🤝 Familles amies</span>.</li>
<li>Cliquez sur <span class="hg-ui">Publier</span>.</li>
</ol>
<div class="hg-note">Une publication ne peut pas être vide : il faut au moins du texte ou une photo.</div>
<p>Si vous publiez « Ma famille » sans être administrateur, un message vous informe que la publication est <strong>envoyée à l'administrateur pour validation</strong>. Les administrateurs reçoivent une notification « Publication à valider ».</p>
H],
        ['id' => 'valider', 'title' => 'Valider les publications « famille » (administrateur)', 'html' => <<<'H'
<p>Les administrateurs voient en haut du mur un encadré <strong>🕓 Publications « famille » en attente</strong>. Pour chacune : <span class="hg-ui">✓ Publier</span> la rend visible à toute la famille, <span class="hg-ui">✕ Refuser</span> la supprime.</p>
H],
        ['id' => 'interagir', 'title' => 'Réagir, commenter, modifier', 'html' => <<<'H'
<ul>
<li><strong>Réagir</strong> : appuyez sur le ❤️ d'une publication. Appuyez de nouveau pour retirer votre réaction. L'auteur reçoit une notification.</li>
<li><strong>Commenter</strong> : écrivez dans <span class="hg-ui">Ajouter un commentaire…</span> puis <span class="hg-ui">Envoyer</span>.</li>
<li><strong>Modifier ou supprimer</strong> : les icônes ✏️ et 🗑 apparaissent sur vos propres publications. Un administrateur de la famille peut aussi modifier ou supprimer les publications de sa famille.</li>
<li><strong>Charger plus</strong> : tout en bas du fil, le bouton affiche les publications plus anciennes.</li>
</ul>
H],
        ['id' => 'suivre', 'title' => 'Suivre des membres (abonnements)', 'html' => <<<'H'
<p>Pour voir les publications « Amis » de quelqu'un, il faut s'abonner à lui.</p>
<ol>
<li>Cliquez sur <span class="hg-ui">👥 Membres</span>.</li>
<li>Dans <strong>Tous les membres</strong> (votre famille) ou <strong>Familles amies</strong>, cliquez sur <span class="hg-ui">S'abonner</span>. Le bouton devient <span class="hg-ui">Demande envoyée</span>.</li>
<li>La personne reçoit la demande dans <strong>Demandes reçues</strong> et l'accepte ou la refuse. Une fois acceptée, le bouton affiche <span class="hg-ui">Abonné(e) ✓</span>.</li>
</ol>
<p>Pour vous désabonner ou retirer un abonné, utilisez les mêmes boutons dans cette fenêtre. La mention <em>vous suit</em> indique qu'une personne est déjà abonnée à vous.</p>
<div class="hg-note">Les abonnements ne concernent que votre famille et vos <strong>familles amies</strong>. Il n'existe aucune recherche libre parmi les autres utilisateurs, et un accès co-parent n'est jamais concerné.</div>
<p>Quand deux membres sont abonnés l'un à l'autre, une conversation privée devient possible : voir <a href="{{app_url}}/aide/messages">Messages privés</a>.</p>
H],
        ['id' => 'photos', 'title' => 'L\'onglet Photos', 'html' => <<<'H'
<p>L'onglet <span class="hg-ui">📷 Photos</span> du mur affiche les albums que leurs propriétaires ont choisi de mettre en avant. Pour y faire apparaître un album, ouvrez-le dans <a href="{{app_url}}/aide/albums">Albums photo</a> et choisissez une portée dans le menu <em>Pas sur le mur</em> (Famille, Amis ou Familles amies).</p>
H],
        ['id' => 'amies', 'title' => 'Familles amies', 'html' => <<<'H'
<p>Deux familles deviennent amies grâce au code famille (voir <a href="{{app_url}}/aide/famille">Gérer la famille</a>). Des familles amies peuvent se suivre sur le mur, partager des événements du calendrier et voir mutuellement les listes de cadeaux en lecture seule.</p>
H],
        ['id' => 'notifications', 'title' => 'Notifications', 'html' => <<<'H'
<p>Les nouvelles publications préviennent les personnes concernées (cloche, et e-mail selon la configuration) : toute la famille pour une publication « famille », votre famille et vos familles amies pour « familles amies », seulement vos abonnés acceptés pour « Amis ».</p>
H],
    ],
];
