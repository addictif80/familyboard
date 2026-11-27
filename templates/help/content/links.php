<?php
return [
    'summary' => 'Les liens utiles de la famille (école, mutuelle, impôts…) réunis dans de belles cartes, avec validation par un administrateur.',
    'who' => ['Tous les membres', 'Administrateur : validation', 'Accès co-parent : liens visibles'],
    'coparent' => [
        'summary' => 'Les liens que l\'administrateur de la famille a rendus visibles pour votre accès co-parent.',
        'sections' => [
            ['id' => 'voir', 'title' => 'Consulter', 'html' => <<<'H'
<p>Vous ne voyez que les liens marqués <em>Visible par un accès co-parent</em> pour l'enfant concerné.</p>
H],
            ['id' => 'proposer', 'title' => 'Proposer un lien utile', 'html' => <<<'H'
<p>Vous pouvez proposer un lien (école, mutuelle…) : il doit être <strong>validé par un administrateur</strong> de la famille avant d'apparaître. Suivez le statut de vos propositions dans <strong>📤 Mes propositions</strong>.</p>
H],
        ],
    ],
    'sections' => [
        ['id' => 'ajouter', 'title' => 'Ajouter ou proposer un lien', 'html' => <<<'H'
<ol>
<li>Cliquez sur le bouton d'ajout en haut de page (<span class="hg-ui">+ Ajouter un lien</span> pour un administrateur, <span class="hg-ui">+ Proposer un lien</span> pour un membre).</li>
<li>Saisissez l'<strong>Adresse du site</strong> (https://exemple.fr).</li>
<li>Indiquez un <strong>Titre</strong> — laissez vide pour utiliser le titre détecté automatiquement — et une <strong>Description</strong> facultative.</li>
<li>Cochez <strong>Visible par un accès co-parent</strong> si besoin, puis choisissez pour quel(s) enfant(s).</li>
<li>Validez.</li>
</ol>
<p>Chaque lien est vérifié automatiquement avant publication (adresse accessible et autorisée) et affiche, quand c'est possible, un aperçu du site. Le nombre de clics (👆) est compté.</p>
H],
        ['id' => 'validation', 'title' => 'Validation par un administrateur', 'html' => <<<'H'
<ul>
<li>Un lien <strong>ajouté par un administrateur</strong> est publié tout de suite.</li>
<li>Un lien <strong>proposé par un membre</strong> (ou par un accès co-parent) apparaît sous <strong>⏳ En attente de validation</strong>. L'administrateur le valide (✓) ou le refuse (✕), en indiquant un motif en cas de refus. Les liens validés passent sous <strong>✅ Publiés</strong>.</li>
<li>Le membre suit l'état de ses propositions dans <strong>📤 Mes propositions</strong>, y compris le motif d'un refus.</li>
</ul>
<p>Actions sur un lien publié : 🔄 rafraîchit l'aperçu, 🗑️ supprime.</p>
H],
        ['id' => 'certifies', 'title' => 'Liens « Certifiés »', 'html' => <<<'H'
<p>Les liens portant le badge <strong>✓ Certifié</strong> sont ajoutés par l'équipe du service à toutes les familles. Ils ne peuvent être ni modifiés ni supprimés au niveau d'une famille.</p>
H],
    ],
];
