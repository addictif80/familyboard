<?php
return [
    'summary' => 'Décidez ensemble en quelques clics : une question, plusieurs choix, chacun vote.',
    'who' => ['Tous les membres'],
    'sections' => [
        ['id' => 'creer', 'title' => 'Créer un sondage', 'html' => <<<'H'
<ol>
<li>Cliquez sur <span class="hg-ui">+ Nouveau sondage</span> (ou <span class="hg-ui">Créer le premier sondage</span> si la page est vide).</li>
<li>Écrivez votre <strong>Question</strong> : « On mange où ce soir ? ».</li>
<li>Renseignez au moins <strong>deux options</strong>. Le bouton <span class="hg-ui">+ Ajouter une option</span> en ajoute d'autres, jusqu'à 10 au total.</li>
<li>Cliquez sur <span class="hg-ui">Créer</span>.</li>
</ol>
H],
        ['id' => 'voter', 'title' => 'Voter', 'html' => <<<'H'
<p>Cliquez sur l'option de votre choix. Les résultats s'affichent en temps réel avec le nombre de votes. Chaque membre a <strong>un seul vote</strong> par sondage, mais peut le <strong>changer</strong> tant que le sondage est ouvert : un nouveau clic sur une autre option remplace le précédent.</p>
H],
        ['id' => 'clore', 'title' => 'Clore et agir sur le résultat', 'html' => <<<'H'
<p>L'auteur du sondage ou un administrateur peut le clore avec le bouton prévu à cet effet ; le même bouton le rouvre. Un sondage clos n'accepte plus de votes.</p>
<p>Une fois clos, l'option gagnante s'affiche avec 🏆 et deux boutons transforment la décision en action :</p>
<ul>
<li><span class="hg-ui">📅 Créer un événement</span> : crée un événement « journée entière » dans le calendrier, daté d'aujourd'hui, que vous ajusterez ensuite.</li>
<li><span class="hg-ui">✅ Créer une tâche</span> : ajoute la décision à une liste « Décisions » (créée automatiquement) dans Tâches & Courses.</li>
</ul>
<p>Une seule décision peut être actée par sondage : un message <em>✅ Décision actée</em> la rappelle ensuite.</p>
<p>Pour supprimer un sondage, utilisez l'icône 🗑️ (auteur ou administrateur).</p>
H],
    ],
];
