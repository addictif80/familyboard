<?php
return [
    'summary' => 'Le suivi des heures de garde par une nounou, avec totaux mensuels et annuels et rapports.',
    'who' => ['Administrateur et membres adultes', 'Non accessible aux ados'],
    'sections' => [
        ['id' => 'entree', 'title' => 'Saisir des heures', 'html' => <<<'H'
<ol>
<li>Cliquez sur <span class="hg-ui">+ Nouvelle entrée</span>.</li>
<li>Renseignez la <strong>Date</strong> et les <strong>Heures</strong> (ex. 7,5), obligatoires.</li>
<li>Choisissez l'<strong>Enfant</strong> (ou « Non précisé », ou <em>+ Nouvel enfant…</em>), le <strong>Nom de la nounou</strong> et des <strong>Notes</strong>.</li>
</ol>
<p>✏️ modifie une entrée, 🗑 la supprime.</p>
H],
        ['id' => 'suivi', 'title' => 'Totaux et rapports', 'html' => <<<'H'
<p>Filtrez par <strong>Année</strong>, <strong>Mois</strong> et <strong>Enfant</strong>. Le tableau affiche Date, Enfant, Nounou, Heures et Notes ; le <strong>Total</strong> du mois, le <strong>Total année</strong> et la <strong>Répartition mensuelle</strong> sont calculés. <span class="hg-ui">📄 Rapport du mois</span> et <span class="hg-ui">📄 Rapport annuel</span> produisent un récapitulatif.</p>
H],
    ],
];
