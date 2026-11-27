<?php
return [
    'summary' => 'Une fiche urgence par personne (allergies, groupe sanguin, médecin, contact), partageable par QR code ou lien.',
    'who' => ['Tous les membres'],
    'sections' => [
        ['id' => 'creer', 'title' => 'Remplir une fiche', 'html' => <<<'H'
<p>Créez une fiche par personne : <strong>Groupe sanguin</strong>, <strong>Allergies</strong>, <strong>Traitements en cours</strong>, <strong>Conditions médicales</strong>, <strong>Médecin traitant</strong> et son téléphone, <strong>Contact d'urgence</strong> et son téléphone, <strong>Notes</strong>, puis <span class="hg-ui">Enregistrer</span>.</p>
H],
        ['id' => 'partager', 'title' => 'Partager la fiche', 'html' => <<<'H'
<p><span class="hg-ui">📱 Voir le QR code</span> affiche un QR code et un lien à imprimer ou envoyer : utile pour une baby-sitter, l'école ou en cas d'urgence.</p>
<div class="hg-warn">La fiche est visible par <strong>quiconque a le lien</strong>, sans connexion. Ne la diffusez qu'aux personnes de confiance.</div>
<p><span class="hg-ui">🔄 Régénérer le lien</span> invalide immédiatement l'ancien lien (un QR code déjà imprimé ou envoyé cesse de fonctionner) : à utiliser si le lien a été diffusé trop largement.</p>
H],
        ['id' => 'journal', 'title' => '🕓 Journal d\'accès', 'html' => <<<'H'
<p>Chaque consultation du lien public est journalisée (date et adresse IP) ; les 50 dernières sont visibles.</p>
H],
    ],
];
