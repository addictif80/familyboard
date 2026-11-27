<?php
return [
    'summary' => 'Tout ce que l\'administrateur règle pour la famille : membres, enfants, modules, familles amies, domicile, mode sombre, notifications.',
    'who' => ['Administrateurs (les membres voient les enfants et les modules, sans pouvoir les modifier)'],
    'sections' => [
        ['id' => 'general', 'title' => 'Réglages généraux', 'html' => <<<'H'
<p>Dans <strong>Réglages › Famille</strong> :</p>
<ul>
<li><strong>Nom de la famille</strong> ;</li>
<li><strong>🏠 Adresse postale du foyer</strong> : utilisée comme expéditeur des <a href="{{app_url}}/aide/letters">courriers</a> ;</li>
<li><strong>🌍 Fuseau horaire</strong> (l'heure actuelle dans ce fuseau est affichée) ;</li>
<li><strong>📺 Ville pour la météo</strong> : affichée dans le bandeau du tableau de bord ;</li>
<li><strong>🎓 Zone scolaire</strong> (A, B, C, Corse ou désactivé) : affiche les vacances scolaires dans le calendrier ; la zone est suggérée d'après votre ville ;</li>
<li><strong>🔄 Synchronisation automatique des calendriers CalDAV</strong> : de toutes les 15 minutes à une fois par jour, ou désactivée.</li>
</ul>
H],
        ['id' => 'membres', 'title' => 'Membres, code et e-mail de la famille', 'html' => <<<'H'
<ul>
<li><strong>Code d'invitation</strong> : à communiquer pour rejoindre la famille à l'inscription. <span class="hg-ui">🔄 Régénérer</span> invalide l'ancien.</li>
<li><strong>✉️ Inviter par e-mail</strong> : saisissez l'adresse puis <span class="hg-ui">Envoyer l'invitation</span> ; le lien est valable 7 jours.</li>
<li><strong>Adresse e-mail famille</strong> : tout message envoyé à cette adresse est transmis à chaque membre.</li>
<li><strong>Membres de la famille</strong> : chacun avec son rôle (voir <a href="{{app_url}}/aide/profils">Profils</a>), avec les actions Promouvoir, Rétrograder, Passer en ado, Rétablir l'accès complet, et le retrait d'un membre.</li>
</ul>
H],
        ['id' => 'enfants', 'title' => '🧒 Enfants de la famille', 'html' => <<<'H'
<p>Créez chaque enfant <strong>une seule fois</strong> (nom, date de naissance, couleur) : il devient disponible dans Suivi scolaire, Suivi nounou, Garde alternée et Bébé, sans ressaisie. ✏️ et 🗑 les modifient ou les suppriment.</p>
H],
        ['id' => 'modules', 'title' => '🧩 Modules actifs', 'html' => <<<'H'
<p>Tous les modules sont actifs par défaut. Décochez ceux que vous voulez <strong>masquer pour toute la famille</strong>, puis <span class="hg-ui">Enregistrer les modules</span>. Les données d'un module masqué ne sont pas supprimées.</p>
H],
        ['id' => 'amies', 'title' => '🤝 Familles amies', 'html' => <<<'H'
<p>Saisissez le <strong>code famille</strong> d'une autre famille puis <span class="hg-ui">Envoyer la demande</span>. Quand elle est acceptée, vous pouvez vous inviter mutuellement à des événements, partager des publications avec les « familles amies » et réserver des cadeaux dans leurs listes. Une demande reçue se <span class="hg-ui">Accepter</span> ou se <span class="hg-ui">Refuser</span> ; <span class="hg-ui">Retirer</span> met fin à l'amitié.</p>
H],
        ['id' => 'domicile', 'title' => '🏠 Domicile', 'html' => <<<'H'
<p>Sert uniquement à l'<strong>alerte différée des minuteurs</strong> : si un minuteur se termine et que personne n'est géolocalisé chez vous, une notification push est envoyée à la place de sonner dans le vide, et l'alarme se déclenche au retour de quelqu'un. Cliquez sur <span class="hg-ui">📍 Utiliser ma position actuelle</span> depuis le domicile (<span class="hg-ui">Effacer</span> pour le retirer). Sans domicile configuré, les minuteurs sonnent normalement.</p>
H],
        ['id' => 'sombre', 'title' => '🌙 Mode sombre de l\'écran mural', 'html' => <<<'H'
<p>Bascule automatiquement l'écran mural et le kiosque en sombre la nuit : <em>Désactivé</em>, <em>Horaires fixes</em> (début et fin) ou <em>Du coucher au lever du soleil</em> (nécessite le domicile). Sans effet sur l'application normale, où chacun garde son réglage clair / sombre.</p>
H],
        ['id' => 'notifier', 'title' => '📣 Envoyer une notification', 'html' => <<<'H'
<p>Saisissez un <strong>titre</strong> et un <strong>message</strong> : ils sont envoyés en push et dans l'application à tous les membres. Vous pouvez inclure le co-parent ; cet envoi est alors journalisé de façon immuable dans le journal d'activité de la garde partagée.</p>
H],
        ['id' => 'acces', 'title' => '🔗 Accès partagés', 'html' => <<<'H'
<p>L'onglet <strong>Accès partagés</strong> regroupe : l'<a href="{{app_url}}/aide/sitter">accès baby-sitter</a>, l'<a href="{{app_url}}/aide/kiosk">écran mural</a> et ses <strong>minuteurs</strong>, ainsi que la liste des liens actifs. Voir aussi <a href="{{app_url}}/aide/liens-publics">Liens publics</a>.</p>
H],
        ['id' => 'historique', 'title' => '📨 Historique des e-mails', 'html' => <<<'H'
<p>L'onglet <strong>Historique</strong> liste les e-mails envoyés par l'application (date, destinataire, sujet, type, statut) : pratique pour vérifier qu'un rappel ou une invitation est bien parti.</p>
H],
    ],
];
