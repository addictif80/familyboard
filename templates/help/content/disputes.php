<?php
return [
    'summary' => 'Un dossier par litige (voisin, opérateur, assurance…) : pièces jointes, traçabilité des échanges et lien de partage en lecture seule.',
    'who' => ['Administrateur et membres adultes', 'Non accessible aux ados'],
    'sections' => [
        ['id' => 'dossier', 'title' => 'Ouvrir un dossier', 'html' => <<<'H'
<p>Cliquez sur <span class="hg-ui">+ Nouveau dossier</span> : <strong>Titre</strong>, <strong>Partie adverse</strong> et <strong>Date de début du litige</strong> sont obligatoires ; <strong>Détails</strong> décrit le contexte. Un dossier est « 🟠 En cours » ; <span class="hg-ui">✅ Clore</span> le passe à « ⚪ Clos » et <span class="hg-ui">🔄 Rouvrir</span> le remet en cours. ✏️ Modifier et 🗑 Supprimer sont aussi disponibles.</p>
H],
        ['id' => 'pieces', 'title' => '📎 Pièces jointes', 'html' => <<<'H'
<p><span class="hg-ui">+ Ajouter</span> joint des PDF, fichiers Word ou Excel, images ou e-mails (.eml), 20 Mo maximum chacun. Chaque pièce indique qui l'a ajoutée et quand.</p>
H],
        ['id' => 'echanges', 'title' => '🗂️ Traçabilité des échanges', 'html' => <<<'H'
<p>Notez chaque contact : <strong>Canal</strong> (📞 Téléphone, ✉️ E-mail, 📮 Courrier), numéro de téléphone si besoin, <strong>Date</strong> et <strong>Résumé</strong> facultatif, puis <span class="hg-ui">+ Ajouter l'échange</span>.</p>
H],
        ['id' => 'partage', 'title' => '🔗 Partager le dossier', 'html' => <<<'H'
<p><span class="hg-ui">🔗 Partager</span> génère un lien : toute personne qui le possède peut consulter les <strong>détails et pièces jointes en lecture seule, sans compte</strong>. La traçabilité des échanges (numéros, e-mails, adresses) n'est <strong>jamais</strong> exposée sur ce lien. Vous pouvez <span class="hg-ui">📋 Copier le lien</span>, <span class="hg-ui">🔄 Régénérer</span> (l'ancien cesse de fonctionner) ou <span class="hg-ui">Révoquer</span>.</p>
<p>Chaque ouverture du lien est journalisée (date, heure, adresse IP) dans <strong>🕵️ Journal des ouvertures</strong>, visible uniquement par les administrateurs.</p>
H],
    ],
];
