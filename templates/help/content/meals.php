<?php
return [
    'summary' => 'Planifiez les repas de la semaine, gardez vos recettes et envoyez les ingrédients dans la liste de courses.',
    'who' => ['Tous les membres'],
    'sections' => [
        ['id' => 'planning', 'title' => 'Planifier la semaine', 'html' => <<<'H'
<p>Le planning affiche une semaine avec, pour chaque jour, quatre créneaux : <strong>Petit-déj, Midi, Soir et Goûter</strong>. Naviguez avec <span class="hg-ui">← Semaine préc.</span> et <span class="hg-ui">Semaine suiv. →</span>.</p>
<ol>
<li>Cliquez sur un créneau pour ouvrir la fenêtre <strong>Repas</strong>.</li>
<li>Choisissez une <strong>Recette</strong> existante, ou écrivez un <strong>texte libre</strong> (« Restes d'hier »).</li>
<li><span class="hg-ui">Enregistrer</span>. <span class="hg-ui">Effacer</span> vide le créneau.</li>
</ol>
H],
        ['id' => 'recettes', 'title' => 'Vos recettes', 'html' => <<<'H'
<ol>
<li>Dans <strong>📖 Recettes</strong>, cliquez sur <span class="hg-ui">+ Nouvelle recette</span>.</li>
<li>Saisissez le <strong>Titre</strong> et le nombre de <strong>Portions</strong>.</li>
<li>Listez les <strong>Ingrédients</strong>, <strong>un par ligne</strong> (« 200g de pâtes », « 2 œufs »).</li>
<li>Ajoutez au besoin la <strong>Préparation</strong>, puis <span class="hg-ui">Enregistrer</span>.</li>
</ol>
<p>Chaque recette peut être modifiée (✏️ Modifier) ou supprimée. Les recettes sont partagées par toute la famille.</p>
H],
        ['id' => 'courses', 'title' => 'Envoyer les ingrédients dans les courses', 'html' => <<<'H'
<ul>
<li><span class="hg-ui">🛒 Ajouter à la liste de courses</span> sur une recette : ajoute ses ingrédients à une liste de courses.</li>
<li><span class="hg-ui">🛒 Ajouter les ingrédients à la liste de courses</span> en haut du planning : ajoute en un clic les ingrédients de <strong>toute la semaine</strong>.</li>
</ul>
<p>Les ingrédients déjà présents dans la liste ne sont pas dupliqués. Retrouvez ensuite la liste dans <a href="{{app_url}}/aide/tasks">Tâches & Courses</a>.</p>
H],
        ['id' => 'ecran', 'title' => 'Où les repas s\'affichent aussi', 'html' => <<<'H'
<p>Le planning des repas apparaît sur l'<a href="{{app_url}}/aide/kiosk">écran mural en mode kiosque</a>.</p>
H],
    ],
];
