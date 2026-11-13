<?php
return [
    'summary' => 'Fini les cadeaux en double : chacun note ses envies, les autres réservent en secret, et la surprise reste entière.',
    'who' => ['Tous les membres'],
    'sections' => [
        ['id' => 'ajouter', 'title' => 'Ajouter un souhait', 'html' => <<<'H'
<ol>
<li>Cliquez sur <span class="hg-ui">+ Ajouter un souhait</span>.</li>
<li>Collez le <strong>Lien du produit</strong> : le titre, le prix, la description et l'image sont récupérés automatiquement si vous laissez les autres champs vides.</li>
<li>Ou remplissez à la main : <strong>Titre</strong>, <strong>Description</strong> (couleur, taille, référence…), <strong>Prix approximatif (€)</strong>, <strong>Image</strong> (si elle n'a pas pu être récupérée).</li>
<li>Choisissez la <strong>Priorité</strong> : <em>Envie</em>, <em>Souhaité</em> ou <em>Coup de cœur</em>.</li>
<li><span class="hg-ui">Enregistrer</span>.</li>
</ol>
<p>Sur un souhait : ✏️ modifie, 🗑️ supprime, <span class="hg-ui">🔄 Récupérer l'image</span> retente la récupération automatique, <span class="hg-ui">Retirer cette image</span> l'enlève.</p>
H],
        ['id' => 'reserver', 'title' => 'Réserver un cadeau pour quelqu\'un', 'html' => <<<'H'
<p>Dans la liste d'un autre membre, cliquez sur <span class="hg-ui">🎀 Réserver ce cadeau</span>. Les autres membres voient alors <em>🎀 Réservé par …</em> et évitent le doublon. Vous pouvez ensuite <span class="hg-ui">Marquer acheté</span> (✅ Acheté), <span class="hg-ui">Annuler</span> la réservation ou <span class="hg-ui">Annuler « acheté »</span>.</p>
<div class="hg-tip"><strong>La surprise est garantie :</strong> vous ne voyez jamais si un de vos propres souhaits est réservé ou déjà acheté.</div>
H],
        ['id' => 'amies', 'title' => 'Familles amies', 'html' => <<<'H'
<p>La section <strong>🤝 Familles amies</strong> affiche, en lecture seule, les listes des familles amies. Vous pouvez y réserver un cadeau pour éviter un doublon entre les deux foyers, mais pas modifier ni supprimer un souhait qui n'est pas le vôtre.</p>
H],
        ['id' => 'enfants', 'title' => 'Listes des enfants sans compte', 'html' => <<<'H'
<p>Pour un enfant qui n'a pas de compte :</p>
<ol>
<li>Dans <strong>🧒 Listes des enfants</strong>, cliquez sur <span class="hg-ui">+ Ajouter un enfant</span> et saisissez son prénom.</li>
<li>Ajoutez ses souhaits avec <span class="hg-ui">+ Ajouter un souhait</span>.</li>
<li>Cliquez sur <span class="hg-ui">🔗 Copier le lien</span> et envoyez-le à la famille et aux proches.</li>
</ol>
<p>Les visiteurs voient la liste de l'enfant sans compte et cliquent sur <span class="hg-ui">Je le prends</span> pour réserver ; les autres visiteurs voient immédiatement que l'article n'est plus disponible. Vous ne voyez jamais ce qui a été réservé. Vous pouvez régénérer le lien d'un enfant si besoin.</p>
H],
        ['id' => 'naissance', 'title' => 'Liste de naissance', 'html' => <<<'H'
<p>Une liste de naissance fonctionne de la même manière, mais se gère depuis le module <a href="{{app_url}}/aide/baby">Bébé & grossesse</a> (onglet <em>🎁 Liste de naissance</em>).</p>
H],
    ],
];
