<?php
return [
    'summary' => 'Vos voyages : dates, budget et réservations (transport, hébergement, activités).',
    'who' => ['Tous les membres'],
    'sections' => [
        ['id' => 'voyage', 'title' => 'Créer un voyage', 'html' => <<<'H'
<p>Cliquez sur <span class="hg-ui">+</span> : <strong>Titre</strong> (obligatoire), <strong>Destination</strong>, <strong>Du</strong> / <strong>Au</strong>, <strong>Budget (€)</strong> et <strong>Notes</strong>. ✏️ Modifier et 🗑 Supprimer sont sur la fiche.</p>
H],
        ['id' => 'resa', 'title' => '🧳 Réservations', 'html' => <<<'H'
<p>Pour chaque voyage : <strong>Titre</strong> (« Vol Paris-Lisbonne… »), <strong>Type</strong> (<em>Transport</em>, <em>Hébergement</em>, <em>Activité</em>, <em>Autre</em>), <strong>Date</strong>, <strong>N° de confirmation</strong>, <strong>Coût (€)</strong>, <strong>Notes</strong>. Le tableau liste Date, Type, Réservation et Coût ; 🗑 supprime une ligne.</p>
H],
    ],
];
