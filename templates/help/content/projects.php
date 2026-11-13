<?php
return [
    'summary' => 'Les projets familiaux (rénovation, voyage…) avec tâches, dépenses, budget et liste de matériaux à acheter.',
    'who' => ['Tous les membres'],
    'sections' => [
        ['id' => 'creer', 'title' => 'Créer un projet', 'html' => <<<'H'
<p>Cliquez sur <span class="hg-ui">+ Nouveau projet</span> (ou <span class="hg-ui">Créer le premier projet</span>) : <strong>Nom du projet</strong> (obligatoire), <strong>Description</strong>, <strong>Budget (€)</strong> facultatif, <strong>Échéance</strong> et <strong>Couleur</strong>. Basculez entre <span class="hg-ui">≡ Liste</span> et <span class="hg-ui">📅 Calendrier</span> ; chaque projet affiche l'avancement des tâches (« ✅ x / y tâches »).</p>
H],
        ['id' => 'fiche', 'title' => 'La fiche projet', 'html' => <<<'H'
<p><span class="hg-ui">✏️ Modifier</span> change nom, description, <strong>statut</strong>, couleur, budget et échéance ; 🗑 Supprimer supprime le projet. Le budget affiche le montant <strong>dépensé</strong> et le pourcentage utilisé. Un <strong>📅 Calendrier du projet</strong> montre les échéances.</p>
<ul>
<li><strong>Tâches</strong> : <span class="hg-ui">+ Tâche</span> avec titre, description, <strong>statut</strong>, <strong>priorité</strong>, <strong>assigné à</strong> et échéance.</li>
<li><strong>Dépenses</strong> : <span class="hg-ui">+ Dépense</span> (description, montant, date, notes).</li>
<li><strong>🔩 Matériaux à acheter</strong> : <span class="hg-ui">+ Matériau</span> avec nom, lien vers l'article et prix unitaire ; la liste indique ce qui est acheté et le total estimé.</li>
</ul>
H],
    ],
];
