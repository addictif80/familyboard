<?php
return [
    'summary' => 'Vos véhicules : kilométrage, assurance, contrôle technique et carnet d\'entretien.',
    'who' => ['Tous les membres'],
    'sections' => [
        ['id' => 'ajouter', 'title' => 'Ajouter un véhicule', 'html' => <<<'H'
<p>Cliquez sur <span class="hg-ui">+</span> dans la colonne <strong>Véhicules</strong> : <strong>Nom</strong> (« Clio de Maman », obligatoire), <strong>Immatriculation</strong>, <strong>Marque</strong>, <strong>Modèle</strong>, <strong>Kilométrage</strong>, <strong>Assureur</strong>, <strong>Échéance assurance</strong>, <strong>Contrôle technique</strong> (date) et <strong>Notes</strong>. ✏️ Modifier et 🗑 Supprimer sont sur la fiche.</p>
H],
        ['id' => 'rappels', 'title' => 'Rappels automatiques', 'html' => <<<'H'
<p>Un e-mail de rappel est envoyé aux membres 30 jours puis 7 jours avant l'échéance de l'assurance et du contrôle technique.</p>
H],
        ['id' => 'entretien', 'title' => '🔧 Entretien', 'html' => <<<'H'
<p>Pour chaque véhicule, ajoutez une intervention (vidange, pneus…) : <strong>Titre</strong>, <strong>Date</strong>, <strong>Kilométrage</strong>, <strong>Coût (€)</strong> et <strong>Notes</strong>. Le tableau liste Date, Intervention, Km et Coût ; 🗑 supprime une ligne.</p>
H],
    ],
];
