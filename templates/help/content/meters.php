<?php
return [
    'summary' => 'Les relevés de vos compteurs (eau, électricité, gaz) et la consommation calculée entre deux relevés.',
    'who' => ['Tous les membres'],
    'sections' => [
        ['id' => 'compteur', 'title' => 'Ajouter un compteur', 'html' => <<<'H'
<p>Cliquez sur <span class="hg-ui">+</span> : <strong>Nom</strong> (obligatoire), <strong>Type</strong> (<em>Eau</em>, <em>Électricité</em>, <em>Gaz</em>, <em>Autre</em>), <strong>Unité</strong> (kWh, m³…), <strong>Fournisseur</strong>, <strong>N° de contrat</strong>. Cochez <strong>Compteur à heures pleines / heures creuses</strong> pour suivre deux index.</p>
H],
        ['id' => 'releves', 'title' => 'Saisir un relevé', 'html' => <<<'H'
<p>Indiquez la <strong>Date du relevé</strong> et l'<strong>Index</strong> (ou <em>Index heures pleines</em> et <em>Index heures creuses</em>), des <strong>Notes</strong>, puis <span class="hg-ui">+ Ajouter un relevé</span>. Le tableau calcule la <strong>consommation de la période</strong> (par tarif et totale pour un compteur HP/HC). 🗑 supprime un relevé.</p>
H],
    ],
];
