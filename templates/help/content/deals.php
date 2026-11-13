<?php
return [
    'summary' => 'Un carnet de bons plans, codes promo et réductions à partager avec la famille, avec leur date de validité.',
    'who' => ['Tous les membres'],
    'sections' => [
        ['id' => 'ajouter', 'title' => 'Ajouter un bon plan', 'html' => <<<'H'
<ol>
<li>Cliquez sur <span class="hg-ui">+ Nouveau bon plan</span>.</li>
<li>Saisissez le <strong>Titre</strong> (obligatoire), puis, si vous le souhaitez : <strong>Description</strong>, <strong>Catégorie</strong>, <strong>Date de validité</strong>, <strong>Réduction</strong> (« -20 % », « 2 achetés = 1 offert »), <strong>Code promo</strong> et <strong>Lien</strong>.</li>
<li>Joignez une <strong>Pièce jointe</strong> (image ou PDF) : un coupon, un bon de réduction…</li>
<li><span class="hg-ui">Enregistrer</span>.</li>
</ol>
H],
        ['id' => 'consulter', 'title' => 'Consulter et trier', 'html' => <<<'H'
<p>Filtrez par <strong>catégorie</strong>. Les bons plans dont la date de validité est dépassée sont marqués <em>Expiré</em> ; cochez <strong>Masquer les expirés</strong> pour les cacher. Chaque carte propose <span class="hg-ui">Voir l'offre ↗</span> (lien) et la pièce jointe ; ✏️ modifie et 🗑️ supprime.</p>
H],
    ],
];
