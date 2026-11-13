<?php
return [
    'summary' => 'Partager une dépense (restaurant, cadeau commun, activité) ou lancer une cagnotte, et suivre qui a payé sa part.',
    'who' => ['Administrateur et membres adultes', 'Co-parents (si activé)', 'Non accessible aux ados'],
    'coparent' => [
        'summary' => 'Suivre et régler votre part des dépenses communes liées à l\'enfant, si l\'autre parent l\'a activé.',
        'sections' => [
            ['id' => 'part', 'title' => 'Votre part', 'html' => <<<'H'
<p>Depuis <span class="hg-ui">🧾 Additions</span>, vous voyez les additions partagées avec vous. Vous pouvez <span class="hg-ui">Accepter</span> ou <span class="hg-ui">Refuser</span> votre part, puis <span class="hg-ui">Marquer ma part payée</span> une fois le règlement effectué (↺ annule ce marquage).</p>
H],
        ],
    ],
    'sections' => [
        ['id' => 'principe', 'title' => 'Principe', 'html' => <<<'H'
<p>Une addition partage une dépense entre des membres de la famille, un co-parent, une famille amie ou une personne invitée par e-mail <strong>sans compte</strong>. <strong>Chaque participant doit accepter individuellement</strong> sa part.</p>
<div class="hg-note">La cagnotte est gratuite et il n'y a <strong>aucun paiement en ligne</strong> : l'application tient seulement un registre que vous remplissez à la main.</div>
H],
        ['id' => 'creer', 'title' => 'Créer une addition ou une cagnotte', 'html' => <<<'H'
<ol>
<li>Cliquez sur <span class="hg-ui">🧾+</span> (addition) ou <span class="hg-ui">🪙+</span> (cagnotte), ou sur <span class="hg-ui">+ Créer une addition</span> si la liste est vide.</li>
<li>Saisissez le <strong>Titre</strong> (obligatoire), une <strong>Description</strong> facultative et le <strong>Montant total (€)</strong>.</li>
<li>Choisissez la <strong>Répartition</strong> : <em>En pourcentage</em> ou <em>En montant libre</em>.</li>
<li>Pour une cagnotte, indiquez un <strong>Objectif (€)</strong> facultatif.</li>
<li>Cochez les <strong>Moyens de paiement acceptés</strong> : <em>Espèces</em>, <em>Virement</em> (avec votre IBAN) ou <em>Wero</em> (avec votre numéro).</li>
<li><span class="hg-ui">Créer</span>.</li>
</ol>
H],
        ['id' => 'participants', 'title' => 'Inviter des participants', 'html' => <<<'H'
<p><span class="hg-ui">+ Inviter un participant</span> : choisissez un <strong>Membre existant</strong> ou saisissez l'<strong>E-mail d'une personne sans compte</strong> (avec son nom), puis sa <strong>Part (%)</strong>. Chacun peut <span class="hg-ui">Accepter</span> ou <span class="hg-ui">Refuser</span> ; une part acceptée se marque <span class="hg-ui">Marquer payé</span> (✅ Payé, ↺ pour annuler).</p>
<p>Les additions que <strong>d'autres familles partagent avec vous</strong> sont regroupées en haut de page, avec « votre part ».</p>
H],
        ['id' => 'percevoir', 'title' => 'Cagnotte : enregistrer une perception', 'html' => <<<'H'
<p>Quand quelqu'un vous règle, cliquez sur <span class="hg-ui">+ Enregistrer une perception</span> : <strong>Qui a payé</strong>, <strong>Montant</strong>, <strong>Moyen de paiement</strong>, <strong>Date</strong>, <strong>Note</strong>. Un compteur indique le total collecté et le pourcentage de l'objectif. La croix ✕ supprime une perception ; <span class="hg-ui">🗑️ Supprimer</span> supprime l'addition entière, <strong>définitivement</strong>.</p>
H],
        ['id' => 'garde', 'title' => 'Depuis la garde partagée', 'html' => <<<'H'
<p>Dans <a href="{{app_url}}/aide/custody">Garde alternée</a>, le raccourci « Partager une dépense avec … » crée une addition avec le co-parent.</p>
H],
    ],
];
