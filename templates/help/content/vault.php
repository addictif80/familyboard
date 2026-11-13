<?php
return [
    'summary' => 'Un coffre-fort numérique pour les informations vitales, accessible à vos personnes de confiance uniquement après validation d\'une demande d\'urgence.',
    'who' => ['Administrateur et membres adultes', 'Non accessible aux ados'],
    'sections' => [
        ['id' => 'entree', 'title' => 'Ajouter une entrée', 'html' => <<<'H'
<ol>
<li>Cliquez sur <span class="hg-ui">+ Nouvelle entrée</span>.</li>
<li>Saisissez le <strong>Titre</strong> (obligatoire) et choisissez la <strong>Catégorie</strong> : <em>Document</em>, <em>Compte</em>, <em>Contact utile</em>, <em>Souhaits</em> ou <em>Autre</em>.</li>
<li>Écrivez le <strong>Contenu</strong> (numéro de contrat, identifiants, instructions…).</li>
<li>Joignez un fichier (image ou PDF), soit en l'envoyant, soit en reprenant un document déjà présent dans <a href="{{app_url}}/aide/documents">Documents</a>. Dans ce cas, une <strong>copie</strong> est ajoutée au coffre : modifier ou supprimer l'original n'affecte pas cette copie.</li>
</ol>
<p>✏️ modifie une entrée, 🗑 la supprime. <span class="hg-ui">⬇️ Tout télécharger</span> récupère l'ensemble du coffre.</p>
H],
        ['id' => 'confiance', 'title' => '🤝 Personnes de confiance', 'html' => <<<'H'
<ol>
<li>Dans <strong>Personnes de confiance</strong>, cliquez sur <span class="hg-ui">+ Ajouter</span> : <strong>Nom</strong> et <strong>E-mail</strong> (obligatoires), <strong>Lien avec la famille</strong> (frère, notaire, ami proche…), <strong>Notes</strong>.</li>
<li>Chaque personne dispose d'un <strong>lien personnel</strong> (<span class="hg-ui">🔗 Copier le lien</span>) à lui transmettre.</li>
</ol>
<h3>Une demande d'accès d'urgence</h3>
<p>Si la personne ouvre son lien et fait une demande, les administrateurs sont notifiés et la demande apparaît « ⏳ en attente ». <strong>Rien ne s'ouvre automatiquement</strong> : un administrateur doit <span class="hg-ui">Approuver</span> ou <span class="hg-ui">Refuser</span> depuis l'application. Une fois « ✅ Accès approuvé », la personne consulte le coffre et peut télécharger les pièces ; <span class="hg-ui">Révoquer</span> retire l'accès.</p>
<div class="hg-warn">Ne désignez que des personnes de confiance : le contenu du coffre peut être sensible.</div>
H],
    ],
];
