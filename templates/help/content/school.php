<?php
return [
    'summary' => 'Le suivi scolaire de chaque élève : emploi du temps, notes, absences, matières, activités et documents.',
    'who' => ['Administrateur et membres adultes', 'Comptes liés en lecture seule'],
    'sections' => [
        ['id' => 'eleve', 'title' => 'Ajouter un élève', 'html' => <<<'H'
<p>Cliquez sur <span class="hg-ui">+</span> dans la colonne <strong>Élèves</strong>. Chaque fiche propose les onglets : <strong>📅 Emploi du temps</strong>, <strong>📊 Notes</strong>, <strong>🚫 Absences</strong>, <strong>🧑‍🏫 Matières & profs</strong>, <strong>🏅 Activités</strong> et <strong>📎 Documents</strong>.</p>
<p>✏️ Modifier et 🗑 Supprimer sont disponibles sur la fiche.</p>
H],
        ['id' => 'matieres', 'title' => 'Matières et professeurs', 'html' => <<<'H'
<p>Commencez par là : ajoutez les matières (le sport/EPS est une matière comme les autres) avec le <strong>Professeur</strong> et une <strong>Couleur</strong>. Elles sont nécessaires pour créer un créneau ou saisir une note.</p>
H],
        ['id' => 'edt', 'title' => 'Emploi du temps', 'html' => <<<'H'
<p><span class="hg-ui">+ Ajouter le créneau</span> : <strong>Jour</strong>, <strong>Matière</strong>, <strong>Début</strong>, <strong>Fin</strong> et <strong>Salle</strong>.</p>
H],
        ['id' => 'notes', 'title' => 'Notes', 'html' => <<<'H'
<p><span class="hg-ui">+ Ajouter la note</span> : <strong>Matière</strong>, <strong>Note</strong>, <strong>Sur</strong> (barème), <strong>Date</strong>, <strong>Intitulé</strong> et <strong>Commentaire</strong>. Une <strong>moyenne générale /20</strong> est calculée.</p>
H],
        ['id' => 'absences', 'title' => 'Absences', 'html' => <<<'H'
<p><span class="hg-ui">+ Ajouter l'absence</span> : <strong>Date</strong>, <strong>Matière</strong> (facultatif, sinon journée entière), <strong>Durée</strong> (journée, 2h…), <strong>Motif</strong> et la case <strong>Justifiée d'emblée</strong>. Une absence non justifiée peut être justifiée ensuite.</p>
H],
        ['id' => 'activites', 'title' => 'Activités extra-scolaires', 'html' => <<<'H'
<p><strong>Nom de l'activité</strong> (football, piano…), <strong>Horaires</strong>, <strong>Lieu</strong>, <strong>Contact</strong> et <strong>Notes</strong>.</p>
H],
        ['id' => 'liens', 'title' => '🔗 Liens de l\'élève', 'html' => <<<'H'
<p>Le bouton <span class="hg-ui">🔗 Liens</span> rattache la fiche à :</p>
<ul>
<li>un <strong>compte membre</strong> : il garde tous ses droits normaux et voit en plus ses notes, absences et bulletins en lecture seule ;</li>
<li>un <strong>co-parent</strong> : il ne voit que cette fiche, en lecture seule (badge « 👁 Lecture seule ») ;</li>
<li>une <strong>liste de tâches ou de courses</strong> (affichée en badge sur la fiche) ;</li>
<li>des <strong>documents</strong> du module Documents.</li>
</ul>
H],
    ],
];
