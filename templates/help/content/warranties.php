<?php
return [
    'summary' => 'Garanties et factures de vos appareils, avec la date de fin et des rappels avant l\'échéance.',
    'who' => ['Tous les membres'],
    'sections' => [
        ['id' => 'ajouter', 'title' => 'Ajouter une garantie', 'html' => <<<'H'
<ol>
<li>Cliquez sur <span class="hg-ui">+ Ajouter une garantie</span>.</li>
<li>Glissez la facture (JPEG, PNG ou PDF, 20 Mo maximum) : le texte est lu automatiquement.</li>
<li>Renseignez la <strong>Désignation</strong> (obligatoire), le <strong>Prix d'achat</strong>, la <strong>Marque</strong>, le <strong>Modèle</strong>, le <strong>N° de série</strong>, le <strong>Magasin / Vendeur</strong>, la <strong>Date d'achat</strong> et la <strong>Durée de garantie</strong> (de 6 mois à 10 ans, ou illimitée / inconnue), puis des <strong>Notes</strong>.</li>
</ol>
<p>La date de fin est calculée et affichée (📅 fin, « jours restants », « Expire aujourd'hui ! » ou « Expirée depuis … j »).</p>
H],
        ['id' => 'alertes', 'title' => 'Recherche et alertes', 'html' => <<<'H'
<p>La barre <em>Rechercher…</em> filtre la liste. Une bannière signale les garanties qui expirent dans les 90 jours, et un e-mail de rappel est envoyé 30 jours puis 7 jours avant la fin. ✏️ modifie, 🗑 supprime.</p>
H],
    ],
];
