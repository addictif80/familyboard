<?php
return [
    'summary' => 'Vos animaux et leur carnet de soins (vaccins, vermifuge, vétérinaire) avec rappels.',
    'who' => ['Tous les membres'],
    'sections' => [
        ['id' => 'ajouter', 'title' => 'Ajouter un animal', 'html' => <<<'H'
<p>Cliquez sur <span class="hg-ui">+</span> dans la colonne <strong>Animaux</strong> : <strong>Nom</strong> (obligatoire), <strong>Espèce</strong>, <strong>Race</strong>, <strong>Date de naissance</strong>, <strong>Vétérinaire</strong>, <strong>Téléphone vétérinaire</strong> et <strong>Notes</strong>. ✏️ Modifier et 🗑 Supprimer sont sur la fiche.</p>
H],
        ['id' => 'soins', 'title' => '💉 Carnet de soins', 'html' => <<<'H'
<p>Ajoutez un soin : <strong>Type</strong> (<em>Vaccin</em>, <em>Vermifuge</em>, <em>Vétérinaire</em>, <em>Autre</em>), <strong>Titre</strong> (« Rappel vaccin CHPPI… »), <strong>Date</strong> et <strong>Rappel</strong> (date). Un e-mail est envoyé aux membres 30 jours puis 7 jours avant la date de rappel.</p>
H],
    ],
];
