<?php
return [
    'summary' => 'Un journal de communication entre parents : messages horodatés, impossibles à modifier ou à supprimer, avec export PDF utilisable comme trace.',
    'who' => ['Membres adultes concernés et co-parents'],
    'sections' => [
        ['id' => 'ecrire', 'title' => 'Écrire un message', 'html' => <<<'H'
<p>Tapez votre message (4 000 caractères maximum) puis envoyez-le avec ➤, ou enregistrez un <strong>message vocal</strong> avec 🎤 (15 Mo maximum). Une mention <em>lu par</em> indique qui a lu le message.</p>
<div class="hg-warn"><strong>Un message envoyé est définitif.</strong> Il est horodaté et ne peut jamais être modifié ni supprimé, pas même par un administrateur : c'est ce qui en fait une trace fiable.</div>
H],
        ['id' => 'vif', 'title' => 'L\'avertissement « Message un peu vif ? »', 'html' => <<<'H'
<p>Si votre message contient des éléments qui pourraient être perçus comme un reproche, une fenêtre vous le signale avant l'envoi. <strong>Rien n'est modifié automatiquement</strong> : vous pouvez l'envoyer tel quel ou le relire.</p>
H],
        ['id' => 'export', 'title' => '⚖️ Export judiciaire (PDF)', 'html' => <<<'H'
<p>Le bouton <span class="hg-ui">⚖️ Export judiciaire (PDF)</span> génère un PDF horodaté, numéroté, en ordre chronologique strict, avec les accusés de lecture, destiné à être produit devant un tiers (avocat, juge). Une empreinte SHA-256 du contenu est ajoutée en pied de document pour pouvoir vérifier qu'il n'a pas été modifié.</p>
H],
        ['id' => 'notif', 'title' => 'Notifications', 'html' => <<<'H'
<p>Quand vous écrivez, les autres membres concernés reçoivent une notification « a ajouté un message » ou « un message vocal ». Le journal est aussi accessible à l'autre parent depuis son <a href="{{app_url}}/aide/coparent-espace">espace co-parent</a>.</p>
H],
    ],
];
