<?php
return [
    'summary' => 'La messagerie de groupe de toute la famille, avec messages écrits et messages vocaux.',
    'who' => ['Tous les membres'],
    'sections' => [
        ['id' => 'ecrire', 'title' => 'Écrire un message', 'html' => <<<'H'
<ol>
<li>Ouvrez <span class="hg-ui">💬 Chat familial</span> dans la barre de gauche.</li>
<li>Écrivez dans <span class="hg-ui">Votre message…</span>.</li>
<li>Appuyez sur <span class="hg-ui">➤</span> pour envoyer.</li>
</ol>
<p>Tous les membres de la famille voient la même conversation. Les nouveaux messages arrivent sans recharger la page, et une notification s'affiche quand l'onglet n'est pas actif.</p>
H],
        ['id' => 'vocal', 'title' => 'Envoyer un message vocal', 'html' => <<<'H'
<ol>
<li>Appuyez sur <span class="hg-ui">🎤</span> : le navigateur demande l'accès au micro la première fois, acceptez.</li>
<li>Parlez, puis arrêtez l'enregistrement pour l'envoyer.</li>
</ol>
<p>Le message vocal apparaît comme une bulle avec un lecteur et sa durée. Sa taille est limitée à 15 Mo.</p>
H],
        ['id' => 'supprimer', 'title' => 'Supprimer un message', 'html' => <<<'H'
<p>Vous pouvez supprimer un de vos messages avec la croix ✕ qui apparaît dessus ; il disparaît pour toute la famille. Un administrateur peut supprimer n'importe quel message de sa famille.</p>
<div class="hg-note">Le chat n'est visible que par votre famille. Pour une discussion à deux, utilisez les <a href="{{app_url}}/aide/messages">messages privés</a> ; pour échanger avec l'autre parent d'un enfant, utilisez le <a href="{{app_url}}/aide/comm_log">journal parental</a>.</div>
H],
    ],
];
