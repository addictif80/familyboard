<?php
return [
    'summary' => 'L\'espace restreint de l\'autre parent : ce qu\'il voit, ce qu\'il peut faire, et ce qui reste hors de sa portée.',
    'who' => ['Co-parents invités', 'Administrateur et adultes qui invitent'],
    'sections' => [
        ['id' => 'acces', 'title' => 'Principe', 'html' => <<<'H'
<p>Un co-parent a un compte à <strong>accès volontairement restreint</strong>. Il n'a ni menu complet ni accès à la vie privée de la famille (budget, autres membres, documents non liés…). Il ne voit que ce qui concerne les enfants pour lesquels il a été invité.</p>
H],
        ['id' => 'inviter', 'title' => 'Comment l\'inviter', 'html' => <<<'H'
<p>Depuis <a href="{{app_url}}/aide/custody">Garde alternée</a> : bouton 🔒 sur le planning, e-mail, enfants concernés. Un écran de bienvenue lui explique ensuite ce qu'il peut faire.</p>
H],
        ['id' => 'actions', 'title' => 'Ce qu\'il peut faire', 'html' => <<<'H'
<ul>
<li><strong>📅 Calendrier de garde</strong> : consulter les prochains jours de garde et <strong>proposer des jours</strong>.</li>
<li><strong>📝 Journal parental</strong> : écrire des messages courts (texte ou vocal) ; voir <a href="{{app_url}}/aide/comm_log">Journal parental</a>.</li>
<li><strong>🖼️ Albums</strong> : voir les albums partagés et y ajouter ses photos.</li>
<li><strong>🗂️ Documents</strong> : consulter les documents rattachés au planning et en ajouter (titre + fichier).</li>
<li><strong>📆 Évènements</strong> : consulter et ajouter des évènements (rendez-vous médecin, sortie scolaire…).</li>
<li><strong>📜 Journal d'activité</strong> : historique horodaté, avec adresse IP, des actions liées à l'enfant, des deux côtés, visible par les deux parents.</li>
<li><strong>🔗 Liens utiles</strong> : proposer un lien.</li>
<li><strong>🧾 Additions</strong> et <strong>🔔 Notifications</strong>.</li>
</ul>
H],
        ['id' => 'limites', 'title' => 'Sa propre famille', 'html' => <<<'H'
<p>Un co-parent peut créer sa propre famille complète depuis son espace (<span class="hg-ui">Créer ma propre famille</span>) ; il conserve son accès « Garde partagée » en plus.</p>
<div class="hg-warn">Si l'abonnement de la famille n'est plus à jour, le co-parent garde son accès un temps, avec un avertissement : sans régularisation, les données du planning sont supprimées définitivement à la date indiquée.</div>
H],
    ],
];
