<?php
return [
    'summary' => 'Le coffre de vos papiers : pièces d\'identité, justificatifs, contrats, avec lecture automatique du texte, alertes d\'expiration et recherche.',
    'who' => ['Tous les membres'],
    'sections' => [
        ['id' => 'ajouter', 'title' => 'Ajouter un document', 'html' => <<<'H'
<ol>
<li>Cliquez sur <span class="hg-ui">+ Ajouter un document</span>.</li>
<li>Glissez le fichier dans la zone ou cliquez pour le choisir (JPEG, PNG ou PDF, 20 Mo maximum). Le texte est lu automatiquement (OCR) et le type du document est détecté.</li>
<li>Pour une pièce officielle (carte d'identité, avis d'imposition, carte grise…), <span class="hg-ui">🔲 Lire le 2D-Doc</span> ou <span class="hg-ui">📷 Scanner avec la caméra</span> lit le code 2D-Doc ; <span class="hg-ui">✅ Pré-remplir le formulaire</span> reporte les informations trouvées.</li>
<li>Renseignez l'<strong>Intitulé</strong> (obligatoire), à qui il <strong>appartient</strong> (un ou plusieurs membres), le <strong>Type de document</strong> (laissez « Détection automatique » ou choisissez), l'<strong>Organisme émetteur</strong>, la <strong>Date d'émission</strong>, la <strong>Date d'expiration</strong> (vide si illimité), des <strong>Tags</strong> et des <strong>Notes</strong>.</li>
<li><span class="hg-ui">Enregistrer</span>.</li>
</ol>
H],
        ['id' => 'doublon', 'title' => 'Document similaire détecté', 'html' => <<<'H'
<p>Si un document au titre proche existe déjà (même famille, même type), une fenêtre vous propose : <span class="hg-ui">Remplacer</span> (l'ancien est mis en archive : retiré de la liste mais consultable depuis l'historique du nouveau), <span class="hg-ui">Garder les deux</span> ou <span class="hg-ui">Annuler</span>.</p>
H],
        ['id' => 'retrouver', 'title' => 'Retrouver et filtrer', 'html' => <<<'H'
<p>La colonne <strong>Types</strong> filtre par catégorie, le sélecteur <strong>Membre</strong> par personne, et la barre <em>Rechercher dans vos documents…</em> cherche dans les titres et dans le texte extrait par OCR. ✏️ ouvre la fiche pour la modifier, 🗑 supprime, 📎 Voir ouvre le fichier.</p>
H],
        ['id' => 'expiration', 'title' => 'Alertes d\'expiration', 'html' => <<<'H'
<p>Une bannière signale les documents qui expirent dans les 90 jours ; chaque carte indique « expire bientôt » ou « (expirée) ». Un e-mail de rappel est envoyé 30 jours puis 7 jours avant l'échéance.</p>
H],
        ['id' => 'garde', 'title' => 'Garde alternée', 'html' => <<<'H'
<p>Pour un document concernant un enfant, l'option <strong>Garde alternée</strong> le rend visible aux co-parents à accès restreint des enfants sélectionnés (voir <a href="{{app_url}}/aide/coparent-espace">Espace co-parent</a>).</p>
H],
    ],
];
