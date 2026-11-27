<?php
return [
    'summary' => 'Administrateur, membre, ado et co-parent : ce que chaque type de compte peut voir et faire.',
    'who' => ['Tout le monde'],
    'sections' => [
        ['id' => 'tableau', 'title' => 'Les quatre types de compte', 'html' => <<<'H'
<table class="hg-tbl">
<tr><th>Profil</th><th>Accès</th></tr>
<tr><td>👑 Administrateur (et fondateur)</td><td>Tout, plus la gestion de la famille : membres, rôles, modules, abonnement, accès baby-sitter, écran mural, notifications. L'<strong>administrateur fondateur</strong> ne peut être retiré ni rétrogradé que par lui-même.</td></tr>
<tr><td>Membre adulte</td><td>Tous les modules actifs de la famille ; pas la gestion de la famille.</td></tr>
<tr><td>🧒 Ado (accès restreint)</td><td>Comme un membre, <strong>sauf</strong> les modules sensibles : Budget, Coffre-fort, Démarches administratives, Litiges, Courriers, Suivi salarié, Suivi nounou, Journal parental et Additions. La famille ne peut pas les réactiver pour un ado.</td></tr>
<tr><td>🔒 Co-parent (accès restreint)</td><td>Un espace dédié, limité aux enfants concernés (voir <a href="{{app_url}}/aide/coparent-espace">Espace co-parent</a>).</td></tr>
</table>
H],
        ['id' => 'changer', 'title' => 'Changer le rôle d\'un membre', 'html' => <<<'H'
<p>Réservé aux administrateurs, dans <a href="{{app_url}}/aide/famille">Famille › Membres</a> :</p>
<ul>
<li><span class="hg-ui">👑 Promouvoir admin</span> / <span class="hg-ui">Rétrograder</span> ;</li>
<li><span class="hg-ui">🧒 Passer en ado</span> / <span class="hg-ui">Rétablir l'accès complet</span>.</li>
</ul>
<p>La personne concernée est notifiée quand le rôle d'administrateur lui est retiré.</p>
H],
        ['id' => 'modules', 'title' => 'Modules masqués', 'html' => <<<'H'
<p>L'administrateur peut aussi désactiver des modules pour toute la famille (voir <a href="{{app_url}}/aide/famille">Famille</a>). Ce guide n'affiche alors que ce que <strong>vous</strong> pouvez utiliser.</p>
H],
    ],
];
