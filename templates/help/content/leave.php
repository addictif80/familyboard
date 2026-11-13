<?php
return [
    'summary' => 'Poser et visualiser les congés de la famille.',
    'who' => ['Administrateur et membres adultes'],
    'sections' => [
        ['id' => 'poser', 'title' => 'Poser un congé', 'html' => <<<'H'
<ol>
<li>Cliquez sur <span class="hg-ui">+ Poser un congé</span>.</li>
<li>Choisissez le <strong>Membre</strong> et le <strong>Type</strong> : <em>Congés payés</em>, <em>RTT</em>, <em>Sans solde</em> ou <em>Autre</em>.</li>
<li>Indiquez les dates (<strong>Du</strong> / <strong>Au</strong>), un <strong>Titre</strong> facultatif (« Vacances d'été… ») et des <strong>Notes</strong>.</li>
</ol>
<p>Le tableau liste Membre, Type, Du, Au et Titre ; 🗑 supprime un congé. Pour le suivi détaillé des soldes d'un salarié, voir <a href="{{app_url}}/aide/employment">Suivi salarié</a>.</p>
H],
    ],
];
