<?php
return [
    'summary' => 'Un diaporama PowerPoint qui résume l\'année de la famille, généré en un clic.',
    'who' => ['Tous les membres'],
    'sections' => [
        ['id' => 'generer', 'title' => 'Générer le diaporama', 'html' => <<<'H'
<ol>
<li>Ouvrez <strong>Récap annuel</strong> (rubrique Souvenirs & famille).</li>
<li>Choisissez l'<strong>Année</strong>.</li>
<li>Cliquez sur <span class="hg-ui">🎬 Générer le PowerPoint</span>.</li>
</ol>
<p>Le fichier <code>.pptx</code> est assemblé directement dans votre navigateur puis téléchargé : rien n'est envoyé ailleurs que vers votre propre ordinateur.</p>
H],
        ['id' => 'contenu', 'title' => 'Ce qu\'il contient', 'html' => <<<'H'
<ul>
<li><strong>Calendrier</strong> : le nombre d'événements de l'année et le mois le plus chargé ;</li>
<li><strong>✅ Tâches & 💰 Budget</strong> : tâches menées à bien, revenus, dépenses et solde de l'année ;</li>
<li><strong>📸 Vie de famille</strong> : publications du mur (dont celles avec photo), photos marquantes ;</li>
<li><strong>Anniversaires</strong> suivis.</li>
</ul>
<p>Les données utilisées sont celles que vous avez le droit de voir dans l'application.</p>
H],
    ],
];
