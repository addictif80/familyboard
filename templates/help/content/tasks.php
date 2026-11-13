<?php
return [
    'summary' => 'Listes de tâches et de courses partagées : assignation, priorités, échéances, partage par lien et export PDF.',
    'who' => ['Tous les membres'],
    'sections' => [
        ['id' => 'listes', 'title' => 'Créer et gérer des listes', 'html' => <<<'H'
<ol>
<li>Cliquez sur <span class="hg-ui">+ Nouvelle liste</span> (ou le <span class="hg-ui">+</span> à côté de « Listes »).</li>
<li>Donnez un <strong>Nom</strong> (« Courses du samedi »).</li>
<li>Choisissez le <strong>Type</strong> : <span class="hg-ui">✅ Tâches</span> (corvées, to-do) ou <span class="hg-ui">🛒 Courses</span>.</li>
<li>Choisissez une <strong>Couleur</strong>, puis <span class="hg-ui">Créer</span>.</li>
</ol>
<p>La corbeille 🗑 à côté d'une liste la supprime avec ses éléments. Le type compte : il change les rappels automatiques (voir plus bas) et permet d'ajouter les ingrédients du <a href="{{app_url}}/aide/meals">planning des repas</a> à une liste de courses.</p>
H],
        ['id' => 'taches', 'title' => 'Ajouter et cocher des éléments', 'html' => <<<'H'
<ol>
<li>Dans une liste, cliquez sur <span class="hg-ui">+ Ajouter</span>.</li>
<li>Saisissez le <strong>Titre</strong> (obligatoire) et au besoin des <strong>Notes</strong>.</li>
<li><strong>Assigné à</strong> : choisissez un membre, ou « Personne ».</li>
<li><strong>Priorité</strong> : 🟢 Basse, 🟡 Moyenne ou 🔴 Haute.</li>
<li><strong>Échéance</strong> : une date facultative.</li>
<li><span class="hg-ui">Enregistrer</span>.</li>
</ol>
<p>Cochez la case d'un élément pour le marquer fait, recochez pour le remettre à faire. Chaque élément peut être modifié ou supprimé (🗑). Les changements sont visibles immédiatement par toute la famille.</p>
H],
        ['id' => 'import', 'title' => 'Importer plusieurs éléments d\'un coup', 'html' => <<<'H'
<p>Vous pouvez coller une liste (un élément par ligne) pour l'ajouter en une fois, par exemple en reprenant une liste tenue dans une autre application. Les lignes vides sont ignorées. Voir aussi la page <a href="{{app_url}}/migration">Migration</a>.</p>
H],
        ['id' => 'evenement', 'title' => 'Lier une liste à un événement (checklist réutilisable)', 'html' => <<<'H'
<p>Pour une checklist réutilisée d'une occasion à l'autre (valise des vacances, rentrée…), utilisez <span class="hg-ui">🔗 Lier à un événement (réinitialisation auto…)</span> et choisissez l'événement. À chaque fois que vous rattachez la liste à un <strong>nouvel événement</strong> (l'édition de l'an prochain), toutes les cases sont automatiquement décochées. <span class="hg-ui">Délier</span> supprime le lien.</p>
H],
        ['id' => 'pdf', 'title' => 'Exporter en PDF', 'html' => <<<'H'
<p>Le bouton <span class="hg-ui">📄 PDF</span> d'une liste génère un document à imprimer, pratique pour une liste de courses papier.</p>
H],
        ['id' => 'partage', 'title' => 'Partager une liste sans compte', 'html' => <<<'H'
<ol>
<li>Ouvrez la liste et cliquez sur <span class="hg-ui">🔗 Partager</span>.</li>
<li>Copiez le lien avec <span class="hg-ui">📋 Copier le lien</span> et envoyez-le (SMS, messagerie…).</li>
</ol>
<p>Toute personne qui possède le lien peut <strong>cocher et décocher</strong> les éléments, sans créer de compte : pratique pour un proche qui fait les courses. Elle ne peut ni ajouter, ni supprimer, ni modifier. <span class="hg-ui">🔄 Régénérer</span> crée un nouveau lien et invalide l'ancien ; <span class="hg-ui">Révoquer</span> coupe l'accès. Un lien révoqué affiche « Lien introuvable ».</p>
<div class="hg-warn">Quiconque a le lien voit le contenu de la liste : ne le publiez pas.</div>
H],
        ['id' => 'rappels', 'title' => 'Rappels automatiques', 'html' => <<<'H'
<ul>
<li><strong>Tâches</strong> : une tâche non terminée depuis plus de 7 jours déclenche un rappel par e-mail à la personne assignée ; sans assignation, à tous les membres (hors accès co-parent).</li>
<li><strong>Courses</strong> : une liste de courses contenant des éléments en attente depuis plus de 7 jours déclenche un rappel par e-mail aux membres.</li>
</ul>
H],
    ],
];
