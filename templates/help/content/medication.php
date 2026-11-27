<?php
return [
    'summary' => 'Des rappels de prise de médicaments par notification, pour vous et pour vos enfants.',
    'who' => ['Chacun pour son traitement', 'Administrateurs pour les enfants'],
    'sections' => [
        ['id' => 'prive', 'title' => 'Confidentialité', 'html' => <<<'H'
<div class="hg-note"><strong>Votre traitement reste privé</strong> : aucun autre membre ne peut le voir. Les rappels arrivent en <strong>notification push</strong> sur votre appareil, <strong>pas par e-mail</strong>. Pensez à activer les notifications (voir <a href="{{app_url}}/aide/mon-compte">Mon compte</a>).</div>
H],
        ['id' => 'ajouter', 'title' => 'Ajouter un traitement', 'html' => <<<'H'
<ol>
<li>Dans <strong>Mon traitement</strong>, cliquez sur <span class="hg-ui">+ Ajouter un traitement</span>.</li>
<li>Indiquez le <strong>Médicament</strong>, la <strong>Posologie</strong> (ex. 1 comprimé) et des <strong>Notes</strong>.</li>
<li>Choisissez la <strong>Date de début</strong> et, si besoin, la <strong>Date de fin</strong>.</li>
<li>Réglez les <strong>Rappels</strong> : <em>Heures fixes dans la journée</em> (avec <span class="hg-ui">+ Ajouter une heure</span>) ou <em>Toutes les X heures</em> à partir d'une heure donnée.</li>
</ol>
<p>Un traitement dont la date de fin est passée est marqué <em>Terminé</em>. Vous pouvez le modifier ou le supprimer.</p>
H],
        ['id' => 'enfants', 'title' => 'Traitement des enfants', 'html' => <<<'H'
<p>La section <strong>Traitement des enfants</strong> est visible <strong>uniquement par les administrateurs</strong> de la famille. L'enfant n'ayant pas de compte, les rappels de prise sont envoyés à <strong>tous les administrateurs</strong>.</p>
H],
    ],
];
