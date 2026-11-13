<?php
return [
    'summary' => 'Un assistant auto-hébergé qui ajoute vos tâches, articles de courses et événements à partir d\'une simple phrase.',
    'who' => ['Tous les membres (si l\'assistant est activé sur votre plateforme)'],
    'sections' => [
        ['id' => 'ouvrir', 'title' => 'Parler à l\'assistant', 'html' => <<<'H'
<p>L'assistant est une bulle <strong>🤖</strong> dans le menu, accessible depuis n'importe quelle page. Sur mobile, un bouton <strong>🎤</strong> dans la barre du bas permet de le dicter à voix haute. La page <em>Historique de l'assistant</em> n'est qu'une vue en lecture seule de l'historique complet.</p>
<ol>
<li>Ouvrez la bulle 🤖.</li>
<li>Écrivez votre demande, ou appuyez sur le micro et parlez (« Je vous écoute… »). La dictée repose sur la reconnaissance vocale de votre navigateur : elle n'est proposée que si celui-ci la prend en charge.</li>
<li>L'assistant répond et, si votre demande est une action, l'exécute et vous le confirme.</li>
</ol>
H],
        ['id' => 'actions', 'title' => 'Ce qu\'il sait faire', 'html' => <<<'H'
<table class="hg-tbl">
<tr><th>Demande type</th><th>Action réalisée</th></tr>
<tr><td>« Ajoute une tâche : appeler le plombier, haute priorité, pour vendredi »</td><td>Crée la tâche dans la liste de tâches de la famille (titre, échéance et priorité s'ils sont mentionnés)</td></tr>
<tr><td>« Ajoute du lait à la liste de courses »</td><td>Ajoute l'article à la liste de courses de la famille</td></tr>
<tr><td>« Rendez-vous chez le dentiste mardi à 14h »</td><td>Crée l'événement dans le calendrier (fin par défaut : une heure après le début)</td></tr>
</table>
<p>L'assistant peut aussi répondre à une question simple. La confirmation d'une action est toujours le texte généré par l'application elle-même, pas le texte libre du modèle : ce que vous lisez correspond à ce qui a réellement été fait.</p>
H],
        ['id' => 'traces', 'title' => 'Traçabilité et confidentialité', 'html' => <<<'H'
<ul>
<li>Toutes les actions effectuées sont listées (action, demandeur, date) dans <strong>📋 Actions effectuées</strong> et visibles de toute la famille.</li>
<li>Selon la configuration de votre plateforme, l'assistant fonctionne sur un serveur auto-hébergé : aucune donnée n'est alors envoyée hors de l'infrastructure.</li>
</ul>
<div class="hg-note">L'assistant n'apparaît que si l'administrateur de la plateforme a configuré un modèle d'IA. L'administrateur de la famille peut aussi masquer le module.</div>
H],
    ],
];
