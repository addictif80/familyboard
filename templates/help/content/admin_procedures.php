<?php
return [
    'summary' => 'Le suivi des démarches administratives (carte d\'identité, passeport, CAF, mutuelle…) avec échéance et rappel.',
    'who' => ['Administrateur et membres adultes', 'Non accessible aux ados'],
    'sections' => [
        ['id' => 'ajouter', 'title' => 'Ajouter une démarche', 'html' => <<<'H'
<ol>
<li>Cliquez sur <span class="hg-ui">+ Nouvelle démarche</span> (il faut qu'un enfant ou un membre existe).</li>
<li>Choisissez la personne (<strong>Concerne</strong>) et le <strong>Type</strong> : <em>Carte d'identité</em>, <em>Passeport</em>, <em>Permis de conduire</em>, <em>Carte vitale</em>, <em>CAF</em>, <em>Mutuelle</em> ou <em>Autre</em>.</li>
<li>Saisissez le <strong>Titre</strong> (« Renouvellement CNI »), l'<strong>Échéance</strong> et des <strong>Notes</strong>.</li>
</ol>
<p>Le tableau liste Échéance, Concerne, Type, Titre et Notes ; 🗑 supprime une démarche.</p>
H],
        ['id' => 'rappels', 'title' => 'Rappels', 'html' => <<<'H'
<p>Pour toute démarche non terminée, un e-mail de rappel est envoyé aux membres 30 jours puis 7 jours avant l'échéance.</p>
H],
    ],
];
