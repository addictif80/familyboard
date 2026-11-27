<?php
return [
    'summary' => 'Un coffre-fort chiffré pour vos mots de passe, hébergé séparément de {{app}} : votre mot de passe maître n\'est jamais connu de l\'application.',
    'who' => ['Administrateur et membres adultes', 'Non accessible aux ados'],
    'sections' => [
        ['id' => 'principe', 'title' => 'Principe', 'html' => <<<'H'
<p>Le coffre est <strong>chiffré</strong> et hébergé séparément de {{app}} (comme Bitwarden). Le <strong>mot de passe maître</strong> n'est jamais envoyé à {{app}} : tout le déchiffrement se fait dans votre navigateur.</p>
<div class="hg-warn">Si vous perdez votre mot de passe maître, {{app}} ne peut pas le récupérer.</div>
H],
        ['id' => 'creer', 'title' => 'Créer mon coffre-fort', 'html' => <<<'H'
<ol>
<li>Cliquez sur <span class="hg-ui">Créer mon coffre-fort</span>.</li>
<li>Une invitation est envoyée par e-mail : suivez le lien pour finaliser la création et choisir votre mot de passe maître.</li>
<li>Ensuite, <span class="hg-ui">🔗 Ouvrir mon coffre-fort</span> mène directement à votre coffre.</li>
</ol>
H],
        ['id' => 'integre', 'title' => '🔓 Gérer mes identifiants dans {{app}}', 'html' => <<<'H'
<p>Vous pouvez aussi consulter vos identifiants depuis la page. Cliquez sur <span class="hg-ui">🔓 Gérer mes identifiants</span>, saisissez le <strong>mot de passe maître</strong> puis <span class="hg-ui">Déverrouiller</span>. Vous pouvez :</p>
<ul>
<li><strong>rechercher</strong> un identifiant (nom, identifiant…) ;</li>
<li><strong>copier le mot de passe</strong> d'une entrée ;</li>
<li><span class="hg-ui">+ Ajouter</span> un identifiant (<strong>Nom</strong> obligatoire, identifiant / e-mail, mot de passe) ou supprimer une entrée ;</li>
<li><span class="hg-ui">🔒 Verrouiller</span> le coffre à tout moment.</li>
</ul>
<div class="hg-note">Cette vue ne gère ni la double authentification du compte ni le type de dérivation de clé Argon2id : dans ces cas, utilisez <span class="hg-ui">🔗 Ouvrir mon coffre-fort</span>.</div>
H],
        ['id' => 'auto', 'title' => 'Remplissage automatique', 'html' => <<<'H'
<p>Pour remplir automatiquement vos mots de passe, installez l'application ou l'extension <strong>Bitwarden</strong> et connectez-la à votre coffre avec l'adresse de l'instance auto-hébergée affichée sur la page.</p>
H],
    ],
];
