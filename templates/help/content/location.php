<?php
return [
    'summary' => 'Partager sa position avec la famille, ponctuellement, pour une durée choisie, et mémoriser des lieux favoris.',
    'who' => ['Tous les membres'],
    'sections' => [
        ['id' => 'partager', 'title' => 'Partager ma position', 'html' => <<<'H'
<p>Le partage est <strong>ponctuel</strong> : aucun suivi en continu, vous décidez à chaque fois.</p>
<ol>
<li>Choisissez la <strong>Durée du partage</strong> : 1, 4, 12 ou 24 heures.</li>
<li>Ajoutez une <strong>Note</strong> (« de retour à 18h »).</li>
<li>Cliquez sur <span class="hg-ui">📍 Partager ma position maintenant</span> et acceptez la demande de localisation du navigateur.</li>
</ol>
<p>Votre position est visible par toute la famille pendant la durée choisie, puis disparaît automatiquement. <span class="hg-ui">Arrêter mon partage</span> la retire avant la fin.</p>
H],
        ['id' => 'famille', 'title' => 'Où est la famille ?', 'html' => <<<'H'
<p>La section liste les positions actuellement partagées par les autres membres.</p>
H],
        ['id' => 'lieux', 'title' => 'Lieux favoris', 'html' => <<<'H'
<p>Placez-vous à l'endroit voulu (maison, école, travail…), saisissez le nom du lieu, puis <span class="hg-ui">+ Ajouter ce lieu (position actuelle)</span>. Un rayon (en mètres) est associé ; un partage effectué à proximité est automatiquement rattaché à ce lieu. <span class="hg-ui">Retirer</span> supprime le lieu.</p>
H],
    ],
];
