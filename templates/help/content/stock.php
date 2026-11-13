<?php
return [
    'summary' => 'Ce qu\'il reste au frigo et au congélateur, avec dates de péremption et alertes.',
    'who' => ['Tous les membres'],
    'sections' => [
        ['id' => 'ajouter', 'title' => 'Ajouter un article', 'html' => <<<'H'
<ol>
<li>Cliquez sur <span class="hg-ui">+ Ajouter un article</span>.</li>
<li>Saisissez le <strong>Nom</strong> (« Lait », « steaks hachés », « épinards surgelés »).</li>
<li>Choisissez l'<strong>Emplacement</strong> : <span class="hg-ui">🧊 Frigo</span> ou <span class="hg-ui">🥶 Congélateur</span>.</li>
<li>Renseignez au besoin la <strong>Catégorie</strong> (« Produits laitiers »), la <strong>Quantité</strong>, l'<strong>Unité</strong> (« pièces », « g », « L »), la <strong>Date de péremption</strong> et des <strong>Notes</strong>.</li>
<li><span class="hg-ui">Enregistrer</span>.</li>
</ol>
H],
        ['id' => 'utiliser', 'title' => 'Utiliser le stock au quotidien', 'html' => <<<'H'
<ul>
<li>Les boutons <span class="hg-ui">−</span> et <span class="hg-ui">+</span> ajustent la quantité sans ouvrir le formulaire : pratique quand on sort un produit.</li>
<li>Les onglets en haut filtrent par <strong>Tout</strong>, Frigo ou Congélateur ; le champ <span class="hg-ui">🔍 Rechercher un article…</span> retrouve un produit par son nom.</li>
<li>✏️ modifie un article, 🗑 le supprime.</li>
<li>Un bandeau <strong>⚠️ article(s) périmé(s) ou à consommer rapidement</strong> signale ce qui urge.</li>
</ul>
H],
        ['id' => 'rappels', 'title' => 'Alertes de péremption', 'html' => <<<'H'
<p>Les membres reçoivent un rappel <strong>3 jours avant</strong> la date de péremption, puis <strong>le jour même</strong> : un produit alimentaire se périme en jours, pas en mois. Le stock peut aussi être affiché dans le widget correspondant du <a href="{{app_url}}/aide/tableau-de-bord">tableau de bord</a>.</p>
H],
    ],
];
