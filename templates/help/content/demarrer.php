<?php
return [
    'summary' => 'Créer votre espace, vous connecter, rejoindre une famille et faire les premiers réglages.',
    'who' => ['Tout le monde'],
    'sections' => [
        ['id' => 'inscription', 'title' => 'Créer un compte', 'html' => <<<'H'
<p>Sur la page d'inscription, renseignez votre <strong>prénom / nom</strong>, votre <strong>e-mail</strong> et un <strong>mot de passe</strong> (8 caractères minimum). Puis choisissez :</p>
<ul>
<li><strong>Créer une nouvelle famille</strong> : saisissez le <strong>nom de la famille</strong>. Vous en devenez l'administrateur fondateur ;</li>
<li><strong>Rejoindre une famille existante</strong> : saisissez le <strong>code d'invitation</strong> que vous a transmis l'administrateur (8 caractères).</li>
</ul>
<p>Cochez l'acceptation des conditions générales et de la politique de confidentialité, puis <span class="hg-ui">S'inscrire</span>.</p>
H],
        ['id' => 'invitation', 'title' => 'Rejoindre par invitation e-mail', 'html' => <<<'H'
<p>Un administrateur peut vous inviter par e-mail : vous recevez un lien valable <strong>7 jours</strong>. Sur la page d'invitation, saisissez votre nom et créez un mot de passe (ou utilisez votre mot de passe existant si vous avez déjà un compte), acceptez les conditions et validez. Une invitation de <strong>co-parent</strong> donne un accès restreint (voir <a href="{{app_url}}/aide/coparent-espace">Espace co-parent</a>).</p>
H],
        ['id' => 'connexion', 'title' => 'Se connecter', 'html' => <<<'H'
<p>Saisissez votre e-mail et votre mot de passe. <em>Mot de passe oublié ?</em> envoie un lien de réinitialisation. Si vous avez activé la <a href="{{app_url}}/aide/mon-compte">double authentification</a>, un code à 6 chiffres vous est demandé (celui de votre application, ou celui envoyé par e-mail, valable 10 minutes ; <em>Renvoyer le code</em> en demande un nouveau).</p>
H],
        ['id' => 'configurateur', 'title' => '🚀 Le configurateur de démarrage', 'html' => <<<'H'
<p>À la première connexion, un assistant vous guide en quelques étapes, toutes facultatives : <strong>🧒 Enfants</strong> (créés une seule fois, ils servent ensuite dans Scolarité, Garde alternée, Suivi nounou et Bébé), <strong>✉️ Membres</strong> (invitations par e-mail), <strong>🎓 Scolarité</strong>, <strong>💼 Activité pro</strong>, <strong>💰 Budget</strong>, puis <strong>✅ Terminé</strong>. Vous pouvez passer (<em>Terminer plus tard</em>) et tout modifier ensuite dans les modules. Il se relance depuis Famille avec <span class="hg-ui">🚀 Relancer le configurateur familial</span>.</p>
H],
        ['id' => 'premiers-pas', 'title' => 'Les premiers réglages utiles', 'html' => <<<'H'
<ol>
<li>Complétez votre <a href="{{app_url}}/aide/mon-compte">profil</a> (couleur, date de naissance pour les anniversaires).</li>
<li>Invitez les autres membres (voir <a href="{{app_url}}/aide/famille">Famille</a>).</li>
<li>Réglez la <strong>ville pour la météo</strong> et la <strong>zone scolaire</strong> pour voir les vacances dans le calendrier.</li>
<li>Installez l'<a href="{{app_url}}/aide/appli-mobile">application</a> et activez les notifications.</li>
</ol>
H],
    ],
];
