<?php
return [
    'summary' => 'Tous les liens qui s\'ouvrent sans compte : ce qu\'ils montrent, qui peut les créer et comment les couper.',
    'who' => ['Tous les membres (selon le module)'],
    'sections' => [
        ['id' => 'principe', 'title' => 'À savoir', 'html' => <<<'H'
<div class="hg-warn">Toute personne qui possède un lien public peut l'ouvrir, sans connexion. Transmettez-les avec soin ; en cas de doute, régénérez ou révoquez le lien.</div>
H],
        ['id' => 'liste', 'title' => 'La liste', 'html' => <<<'H'
<table class="hg-tbl">
<tr><th>Lien</th><th>Ce qu'il montre</th><th>Comment le couper</th></tr>
<tr><td>Album</td><td>L'album en lecture, éventuellement avec ajout de photos. <a href="{{app_url}}/aide/albums">Albums</a></td><td>Révoquer le lien</td></tr>
<tr><td>Liste de souhaits d'un enfant</td><td>La liste, avec réservation « Je le prends ». <a href="{{app_url}}/aide/wishlist">Souhaits</a></td><td>Régénérer le lien</td></tr>
<tr><td>Liste de naissance</td><td>Les articles, réservables sans compte. <a href="{{app_url}}/aide/baby">Bébé</a></td><td>Supprimer les articles / lien</td></tr>
<tr><td>Fiche urgence</td><td>Allergies, groupe sanguin, médecin, contact. <a href="{{app_url}}/aide/emergency">Urgence</a></td><td>Régénérer le lien</td></tr>
<tr><td>Carnet de santé</td><td>Le carnet d'une seule personne, temporaire (7, 14 ou 30 jours). <a href="{{app_url}}/aide/health">Santé</a></td><td>Expire automatiquement</td></tr>
<tr><td>Dossier de litige</td><td>Détails et pièces jointes, jamais les échanges. <a href="{{app_url}}/aide/disputes">Litiges</a></td><td>Révoquer ou régénérer</td></tr>
<tr><td>Accès baby-sitter</td><td>Contacts, consignes, suivi bébé, temporaire. <a href="{{app_url}}/aide/sitter">Baby-sitter</a></td><td>Révoquer (ou expiration)</td></tr>
<tr><td>Écran mural / kiosque</td><td>Tâches, courses, contacts, événements, repas. <a href="{{app_url}}/aide/kiosk">Écran mural</a></td><td>Révoquer</td></tr>
<tr><td>Personne de confiance (coffre)</td><td>Le coffre, uniquement après approbation d'un administrateur. <a href="{{app_url}}/aide/vault">Coffre-fort</a></td><td>Révoquer l'accès</td></tr>
<tr><td>Calendrier d'abonnement</td><td>Votre calendrier global, pour votre téléphone. <a href="{{app_url}}/aide/mon-compte">Mon compte</a></td><td>Régénérer le lien</td></tr>
</table>
H],
    ],
];
