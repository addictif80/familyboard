<?php
return [
    'summary' => 'Votre profil, la sécurité du compte, les notifications, l\'installation de l\'application et vos données personnelles.',
    'who' => ['Tous les membres'],
    'sections' => [
        ['id' => 'profil', 'title' => '👤 Mon profil', 'html' => <<<'H'
<p>Dans <strong>Réglages › Mon compte</strong> : changer l'<strong>avatar</strong>, le <strong>nom</strong>, la <strong>couleur</strong>, l'<strong>e-mail</strong>, la <strong>date de naissance</strong> (elle sert aux rappels d'anniversaire par la famille), le <strong>téléphone</strong> (montré à une baby-sitter pendant un accès actif, nulle part ailleurs) et le <strong>mot de passe</strong> (laisser vide pour ne pas changer ; le mot de passe actuel est exigé pour le modifier).</p>
<p><strong>Suivi de position pour les minuteurs</strong> : si vous le cochez, l'application sait si quelqu'un est à la maison quand un minuteur se termine (voir <a href="{{app_url}}/aide/kiosk">Écran mural</a>). La position est mise à jour pendant que l'application est ouverte, jamais conservée en historique, et désactivable à tout moment.</p>
<p><span class="hg-ui">🔌 Déconnecter tous les appareils</span> met fin à toutes vos connexions actives (navigateurs, applications installées), y compris celle-ci.</p>
H],
        ['id' => '2fa', 'title' => '🔐 Double authentification', 'html' => <<<'H'
<p>Deux méthodes :</p>
<ul>
<li><span class="hg-ui">📱 Utiliser une application</span> (recommandé) : scannez le QR code avec votre application d'authentification (Google Authenticator, Authy, Ente Auth…) ou saisissez la clé manuellement, puis entrez le code affiché et <span class="hg-ui">Confirmer et activer</span> ;</li>
<li><span class="hg-ui">✉️ Utiliser mon e-mail</span> : un code est envoyé à chaque connexion.</li>
</ul>
<p>Pour la désactiver, confirmez votre mot de passe. Vous pouvez passer de l'e-mail à une application à tout moment.</p>
H],
        ['id' => 'mdp', 'title' => '🔐 Coffre-fort de mots de passe', 'html' => <<<'H'
<p>Ce raccourci mène à <a href="{{app_url}}/aide/vault_passwords">Mots de passe</a>.</p>
H],
        ['id' => 'notifs', 'title' => '🔔 Notifications push', 'html' => <<<'H'
<p>Le bouton <span class="hg-ui">Activer les notifications push</span> abonne l'appareil en cours (ordinateur ou mobile) : vous êtes prévenu même quand l'application est fermée. À faire sur chaque appareil. Sur iPhone, il faut d'abord installer l'application (voir <a href="{{app_url}}/aide/appli-mobile">Application mobile</a>).</p>
H],
        ['id' => 'calendrier', 'title' => '📅 Synchroniser le calendrier avec mon mobile', 'html' => <<<'H'
<p>Le bouton génère un <strong>lien d'abonnement</strong> (<span class="hg-ui">Copier</span>). Abonnez-y l'application Calendrier de votre téléphone : elle affiche événements, garde alternée, congés et calendriers externes, et se met à jour toute seule. Vous ne voyez que les événements de garde des plannings auxquels vous avez accès.</p>
<ul>
<li><strong>iPhone</strong> : Réglages › Calendrier › Comptes › Ajouter un compte › Autre › Calendrier à abonnement, puis collez le lien.</li>
<li><strong>Android / Google Agenda</strong> : sur ordinateur, Google Agenda › Autres agendas › À partir de l'URL.</li>
</ul>
<p><span class="hg-ui">🔄 Régénérer le lien</span> à utiliser si le téléphone est perdu ou revendu : l'ancien lien cesse de fonctionner.</p>
H],
        ['id' => 'donnees', 'title' => '📁 Mes données', 'html' => <<<'H'
<p><span class="hg-ui">📥 Télécharger mes données</span> fournit une archive ZIP de vos données (profil, événements, tâches, documents…). Les administrateurs disposent aussi de <span class="hg-ui">📦 Télécharger les données de toute la famille</span>.</p>
H],
        ['id' => 'parrainage', 'title' => '🤝 Parrainage et 💬 avis', 'html' => <<<'H'
<p><strong>Parrainer une famille</strong> : copiez votre lien ; les familles qui s'inscrivent avec ce lien sont listées, avec une récompense si l'équipe en a configuré une. <strong>Partagez votre avis</strong> : un témoignage (avec rôle et note facultatifs) peut être publié sur la page d'accueil après validation ; son statut s'affiche (en attente, publié, non retenu).</p>
H],
        ['id' => 'suppression', 'title' => '⚠️ Supprimer mon compte', 'html' => <<<'H'
<p>La suppression de votre compte est <strong>définitive et irréversible</strong>. Si la famille compte d'autres membres, <strong>le contenu que vous avez créé</strong> (documents, événements, journal, photos, liens) <strong>reste dans la famille</strong> : il est détaché de votre compte, mais votre nom y reste affiché. Pour un effacement complet de ces contenus, il faut en faire la demande auprès du support.</p>
<p>Un administrateur doit soit <strong>transférer son rôle</strong> à un autre membre, soit <strong>supprimer toute la famille</strong> (en tapant SUPPRIMER pour confirmer). Si vous êtes le seul membre, la famille et ses données sont supprimées avec le compte. La page de connexion propose « Compte supprimé ? Redemander mes données ».</p>
H],
    ],
];
