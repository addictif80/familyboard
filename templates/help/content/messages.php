<?php
return [
    'summary' => 'Une conversation privée entre deux membres de votre famille, distincte du chat de groupe.',
    'who' => ['Membres qui se suivent mutuellement'],
    'sections' => [
        ['id' => 'condition', 'title' => 'À quelles conditions ?', 'html' => <<<'H'
<p>Les messages privés sont réservés à deux membres <strong>de la même famille</strong> qui se suivent <strong>mutuellement</strong> sur le <a href="{{app_url}}/aide/wall">mur familial</a>. Tant que l'abonnement n'est pas accepté dans les deux sens, la conversation est impossible et un message vous l'indique.</p>
<div class="hg-note">Le module Messages privés dépend du module <strong>Mur familial</strong> : si l'administrateur désactive le mur, les messages privés disparaissent aussi.</div>
H],
        ['id' => 'utiliser', 'title' => 'Démarrer et suivre une conversation', 'html' => <<<'H'
<ol>
<li>Depuis le mur, cliquez sur <span class="hg-ui">✉️ Messages privés</span> pour ouvrir votre boîte de réception, ou cliquez sur l'icône ✉️ du profil d'un membre.</li>
<li>Choisissez une conversation, ou démarrez-en une avec un membre que vous suivez mutuellement.</li>
<li>Écrivez dans <span class="hg-ui">Votre message…</span> puis appuyez sur <span class="hg-ui">➤</span>. Un message est limité à 2 000 caractères.</li>
</ol>
<p>La conversation se rafraîchit toute seule. Le destinataire reçoit une notification « Nouveau message de … », et le tableau de bord affiche un compteur de messages non lus. Un lien <span class="hg-ui">← Toutes les conversations</span> ramène à la liste.</p>
<div class="hg-warn">Ne confondez pas : le <a href="{{app_url}}/aide/chat">Chat familial</a> est lu par toute la famille, alors qu'ici seuls vous et votre correspondant voyez les messages.</div>
H],
    ],
];
