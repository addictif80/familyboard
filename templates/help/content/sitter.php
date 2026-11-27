<?php
return [
    'summary' => 'Un lien temporaire pour une baby-sitter : contacts, consignes et suivi bébé, sans compte et sans accès au reste de vos données.',
    'who' => ['Administrateur : création du lien', 'Baby-sitter : lecture seule'],
    'sections' => [
        ['id' => 'creer', 'title' => 'Créer un accès', 'html' => <<<'H'
<ol>
<li>Allez dans <em>Réglages › Accès partagés › Accès baby-sitter</em>.</li>
<li>Saisissez un <strong>Libellé</strong> (« Baby-sitter mardi soir »).</li>
<li>Choisissez la durée dans <strong>Valide pendant</strong> : 6 heures, 12 heures, 24 heures ou 3 jours.</li>
<li>Rédigez au besoin des <strong>Consignes</strong> (« coucher à 20h, pas d'écran après le dîner »).</li>
<li>Cliquez sur <span class="hg-ui">+ Générer un lien</span> et envoyez le lien à la personne.</li>
</ol>
<p>La liste affiche chaque accès avec sa date d'expiration et un bouton <span class="hg-ui">Révoquer</span> qui invalide immédiatement le lien.</p>
H],
        ['id' => 'voit', 'title' => 'Ce que voit la baby-sitter', 'html' => <<<'H'
<p>Une page en <strong>lecture seule</strong>, valable jusqu'à l'échéance, sans connexion :</p>
<ul>
<li><strong>📋 Consignes</strong> rédigées par les parents ;</li>
<li><strong>📞 Contacts des parents</strong> : le téléphone renseigné dans le profil de chaque parent (c'est le seul endroit où ce numéro est montré) ;</li>
<li><strong>🚑 Numéros de secours</strong> ;</li>
<li><strong>🍼 Suivi bébé</strong> : dernier repas, dernier change, dernier bain et sommeil en cours.</li>
</ul>
<div class="hg-warn">Elle ne voit rien d'autre de vos données. Une fois le lien expiré ou révoqué, la page affiche « Lien invalide ou expiré ».</div>
H],
        ['id' => 'ecran', 'title' => 'Effet sur l\'écran mural', 'html' => <<<'H'
<p>Tant qu'un accès baby-sitter est actif, l'<a href="{{app_url}}/aide/kiosk">écran mural en mode kiosque</a> masque les informations familiales et affiche un QR code.</p>
H],
    ],
];
