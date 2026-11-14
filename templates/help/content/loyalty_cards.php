<?php
return [
    'summary' => 'Toutes vos cartes de fidélité dans l\'application : le code-barres s\'affiche en caisse, plus besoin des cartes en plastique.',
    'who' => ['Tous les membres'],
    'sections' => [
        ['id' => 'ajouter', 'title' => 'Ajouter une carte', 'html' => <<<'H'
<ol>
<li>Cliquez sur <span class="hg-ui">+</span> dans la colonne <strong>Mes cartes</strong>.</li>
<li>Saisissez le <strong>Magasin / enseigne</strong> (Carrefour, Fnac, Sephora…) et choisissez une <strong>Couleur</strong>.</li>
<li>Renseignez le <strong>Numéro de carte</strong> (celui inscrit sous le code-barres), ou cliquez sur 📷 pour <strong>scanner le code-barres</strong> avec la caméra : présentez-le devant l'appareil.</li>
<li>Choisissez le <strong>Type de code</strong> si besoin et ajoutez des <strong>Notes</strong> (numéro de cagnotte, avantages, conditions…).</li>
<li><span class="hg-ui">Enregistrer</span>. Le magasin et le numéro de carte sont obligatoires.</li>
</ol>
<div class="hg-note">Le scan demande l'autorisation d'utiliser la caméra ; en cas de refus, saisissez le numéro à la main.</div>
H],
        ['id' => 'utiliser', 'title' => 'Utiliser et gérer une carte', 'html' => <<<'H'
<p>Sélectionnez une carte dans la liste : son code-barres s'affiche, prêt à être présenté en caisse. <span class="hg-ui">✏️ Modifier</span> change ses informations ; <span class="hg-ui">🗑 Supprimer</span> la retire (confirmation demandée).</p>
H],
    ],
];
